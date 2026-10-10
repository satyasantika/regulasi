<?php

namespace App\Services\Pencarian;

use App\Enums\StatusPublikasi;
use App\Enums\Visibilitas;
use App\Models\BadanPenetap;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Cadangan pencarian MariaDB FULLTEXT (02-ARSITEKTUR §5.2, 03-SKEMA §5): dipakai bila Meilisearch tidak sehat.
 *
 * - Kata kunci: MATCH ft_dokumen (judul, sebutan_singkat, abstrak) IN BOOLEAN MODE dengan +kata* per token
 *   ≥ 3 huruf (innodb_ft_min_token_size), ATAU nomor LIKE "q%" (nomor seperti 181/PMK.06/2016 terpecah
 *   tokenizer), ATAU MATCH ft_ekstraksi_isi pada teks tautan berstatus apa pun yang berisi teks.
 * - Faset dihitung dengan GROUP BY terpisah memakai semua filter kecuali dimensi faset itu sendiri.
 * - Tanpa toleransi salah ketik (alasan Meilisearch disarankan).
 */
class PencarianDatabase implements PencarianDokumen
{
    private const array RELASI = ['jenisDokumen', 'badanPenetap', 'kategoriSistem'];

    public function cari(KriteriaPencarian $kriteria): HasilPencarian
    {
        $boolean = self::ekspresiBoolean($kriteria->q);
        $query = $this->kueri($kriteria, $boolean)->with(self::RELASI);

        if ($kriteria->urut === 'relevan' && $boolean !== null) {
            $query->select('dokumen.*')
                ->selectRaw('MATCH (judul, sebutan_singkat, abstrak) AGAINST (? IN BOOLEAN MODE) AS skor_relevansi', [$boolean])
                ->orderByDesc('skor_relevansi');
        }

        $this->urutkan($query, $kriteria->urut);

        /** @var LengthAwarePaginator<int, Dokumen> $dokumen */
        $dokumen = $query->paginate(perPage: $kriteria->perHalaman, pageName: 'halaman', page: $kriteria->halaman);

        return new HasilPencarian($dokumen, $this->faset($kriteria, $boolean), 'database');
    }

    /**
     * Ekspresi BOOLEAN MODE: setiap token huruf/angka ≥ 3 karakter menjadi +token*; null bila tak ada token.
     */
    public static function ekspresiBoolean(string $q): ?string
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($q), $cocok);
        $token = array_values(array_unique(array_filter($cocok[0], fn (string $t): bool => mb_strlen($t) >= 3)));

        return $token === [] ? null : implode(' ', array_map(fn (string $t): string => "+{$t}*", $token));
    }

    /**
     * Dokumen terbit (BR-01), publik bila tidak menyertakan internal (BR-02), dengan semua filter kecuali $kecuali.
     *
     * @return Builder<Dokumen>
     */
    private function kueri(KriteriaPencarian $kriteria, ?string $boolean, ?string $kecuali = null): Builder
    {
        $query = Dokumen::query()
            ->where('dokumen.status_publikasi', StatusPublikasi::Terbit->value)
            ->when(! $kriteria->sertakanInternal, fn (Builder $q) => $q->where('dokumen.visibilitas', Visibilitas::Publik->value));

        if ($kriteria->adaKataKunci()) {
            $query->where(function (Builder $q) use ($kriteria, $boolean): void {
                $q->where('dokumen.nomor', 'like', self::escapeLike($kriteria->q).'%');

                if ($boolean !== null) {
                    $q->orWhereFullText(['judul', 'sebutan_singkat', 'abstrak'], $boolean, ['mode' => 'boolean'])
                        ->orWhereHas('tautanBerkas.ekstraksi', fn (Builder $e) => $e->whereFullText('isi_teks', $boolean, ['mode' => 'boolean']));
                }
            });
        }

        $filter = $kriteria->filterFaset();

        if ($kecuali !== 'jenis' && $filter['jenis'] !== []) {
            $query->whereIn('dokumen.jenis_dokumen_id', JenisDokumen::withTrashed()->whereIn('kode', $filter['jenis'])->select('id'));
        }

        if ($kriteria->kelompok !== []) {
            $query->whereIn('dokumen.jenis_dokumen_id', JenisDokumen::withTrashed()->whereIn('kelompok', $kriteria->kelompok)->select('id'));
        }

        if ($kecuali !== 'tahun' && $filter['tahun'] !== []) {
            $query->whereIn('dokumen.tahun', $filter['tahun']);
        }

        if ($kecuali !== 'status_berlaku' && $filter['status_berlaku'] !== []) {
            $query->whereIn('dokumen.status_berlaku', $filter['status_berlaku']);
        }

        if ($kecuali !== 'kategori' && $filter['kategori'] !== []) {
            $query->whereHas('kategoriSistem', fn (Builder $q) => $q->whereIn('kategori_sistem.kode', $filter['kategori']));
        }

        if ($kecuali !== 'badan_penetap' && $filter['badan_penetap'] !== []) {
            $query->whereIn('dokumen.badan_penetap_id', BadanPenetap::withTrashed()->whereIn('slug', $filter['badan_penetap'])->select('id'));
        }

        return $query;
    }

    /**
     * @param  Builder<Dokumen>  $query
     */
    private function urutkan(Builder $query, string $urut): void
    {
        match ($urut) {
            'tahun_desc' => $query->orderByDesc('dokumen.tahun'),
            'tahun_asc' => $query->orderBy('dokumen.tahun'),
            'dibuka' => $query->orderByDesc('dokumen.jumlah_dibuka'),
            default => $query->orderByDesc('dokumen.dipublikasikan_pada'),
        };

        $query->orderByDesc('dokumen.dipublikasikan_pada')->orderBy('dokumen.id');
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function faset(KriteriaPencarian $kriteria, ?string $boolean): array
    {
        $faset = [];

        foreach (HasilPencarian::DIMENSI_FASET as $dimensi) {
            $dasar = $this->kueri($kriteria, $boolean, $dimensi)->toBase();

            $baris = match ($dimensi) {
                'jenis' => $this->kelompokkan($dasar->join('jenis_dokumen', 'jenis_dokumen.id', '=', 'dokumen.jenis_dokumen_id'), 'jenis_dokumen.kode'),
                'badan_penetap' => $this->kelompokkan($dasar->join('badan_penetap', 'badan_penetap.id', '=', 'dokumen.badan_penetap_id'), 'badan_penetap.slug'),
                'kategori' => $this->kelompokkan(
                    $dasar->join('dokumen_kategori_sistem', 'dokumen_kategori_sistem.dokumen_id', '=', 'dokumen.id')
                        ->join('kategori_sistem', 'kategori_sistem.id', '=', 'dokumen_kategori_sistem.kategori_sistem_id'),
                    'kategori_sistem.kode',
                ),
                'tahun' => $this->kelompokkan($dasar, 'dokumen.tahun'),
                default => $this->kelompokkan($dasar, 'dokumen.status_berlaku'),
            };

            $dimensi === 'tahun' ? krsort($baris) : arsort($baris);
            $faset[$dimensi] = $baris;
        }

        return $faset;
    }

    /**
     * @return array<string, int>
     */
    private function kelompokkan(QueryBuilder $query, string $kolom): array
    {
        return $query
            ->selectRaw("{$kolom} AS kunci, COUNT(DISTINCT dokumen.id) AS jumlah")
            ->groupBy($kolom)
            ->pluck('jumlah', 'kunci')
            ->mapWithKeys(fn (mixed $jumlah, mixed $kunci): array => [(string) $kunci => (int) $jumlah])
            ->all();
    }

    private static function escapeLike(string $nilai): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $nilai);
    }
}
