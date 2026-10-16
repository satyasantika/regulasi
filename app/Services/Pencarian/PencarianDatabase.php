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
 * Pencarian MariaDB FULLTEXT (02-ARSITEKTUR §5.1, 03-SKEMA §5), mesin bawaan Profil Ringan.
 *
 * - Kata kunci: MATCH ft_dokumen (judul, sebutan_singkat, abstrak) IN BOOLEAN MODE dengan +kata* per token
 *   ≥ regulasi.ft_min_token huruf (= innodb_ft_min_token_size; bawaan 3, produksi 2), ATAU nomor LIKE "q%" (nomor seperti 181/PMK.06/2016 terpecah
 *   tokenizer), ATAU MATCH ft_ekstraksi_isi pada teks tautan berstatus apa pun yang berisi teks.
 * - Faset dihitung dengan GROUP BY terpisah memakai semua filter kecuali dimensi faset itu sendiri.
 * - Sinonim dua arah dari kamus (`sinonim_pencarian`): `+(token* "padanan")`, juga untuk frasa (mis. "barang milik
 *   negara" ↔ bmn).
 * - Salah ketik: bila hasil kosong, kueri diulang dengan token terkoreksi kamus istilah (KoreksiIstilah) dan
 *   HasilPencarian memuat kueri asli (`dikoreksiDari`) untuk pesan UI. Kueri `persis` tidak dikoreksi.
 */
class PencarianDatabase implements PencarianDokumen
{
    private const array RELASI = ['jenisDokumen', 'badanPenetap', 'kategoriSistem'];

    public function __construct(
        private readonly KoreksiIstilah $koreksi,
        private readonly KamusPencarian $kamus,
    ) {}

    public function cari(KriteriaPencarian $kriteria): HasilPencarian
    {
        $hasil = $this->jalankan($kriteria);

        if ($kriteria->adaKataKunci() && ! $kriteria->persis && $hasil->dokumen->total() === 0) {
            $terkoreksi = $this->koreksi->koreksi($kriteria->q);

            if ($terkoreksi !== null && $terkoreksi !== mb_strtolower($kriteria->q)) {
                $ulang = $this->jalankan($kriteria->denganKueri($terkoreksi));

                if ($ulang->dokumen->total() > 0) {
                    return new HasilPencarian($ulang->dokumen, $ulang->faset, 'database', $terkoreksi, $kriteria->q);
                }
            }
        }

        return $hasil;
    }

    private function jalankan(KriteriaPencarian $kriteria): HasilPencarian
    {
        $boolean = self::ekspresiBoolean($kriteria->q, $kriteria->adaKataKunci() ? $this->kamus->sinonim() : []);
        $query = $this->kueri($kriteria, $boolean)->with(self::RELASI);

        if ($kriteria->urut === 'relevan' && $boolean !== null) {
            $query->select('dokumen.*')
                ->selectRaw('MATCH (judul, sebutan_singkat, abstrak) AGAINST (? IN BOOLEAN MODE) AS skor_relevansi', [$boolean])
                ->orderByDesc('skor_relevansi');
        }

        $this->urutkan($query, $kriteria->urut);

        /** @var LengthAwarePaginator<int, Dokumen> $dokumen */
        $dokumen = $query->paginate(perPage: $kriteria->perHalaman, pageName: 'halaman', page: $kriteria->halaman);

        return new HasilPencarian($dokumen, $this->faset($kriteria, $boolean), 'database', $kriteria->adaKataKunci() ? $kriteria->q : null);
    }

    /**
     * Ekspresi BOOLEAN MODE: setiap token huruf/angka ≥ regulasi.ft_min_token karakter menjadi +token*; null bila tak
     * ada token. Token lebih pendek dari innodb_ft_min_token_size tidak pernah cocok sehingga harus dibuang.
     * Token (atau frasa) yang punya sinonim menjadi `+(token* "padanan")` / `+((+a* +b*) "padanan")`.
     *
     * @param  array<string, list<string>>  $sinonim  istilah => padanan (dua arah)
     */
    public static function ekspresiBoolean(string $q, array $sinonim = []): ?string
    {
        $minimum = max(1, (int) config('regulasi.ft_min_token', 3));
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($q), $cocok);
        $semua = $cocok[0];

        $bagian = [];
        $i = 0;
        $n = count($semua);

        while ($i < $n) {
            $panjang = 1;
            $padanan = [];

            // Frasa bersinonim terpanjang yang diawali token ke-$i (jendela maks. 6 kata).
            for ($w = min(6, $n - $i); $w >= 1; $w--) {
                $frasa = implode(' ', array_slice($semua, $i, $w));

                if (isset($sinonim[$frasa])) {
                    $panjang = $w;
                    $padanan = $sinonim[$frasa];
                    break;
                }
            }

            $kata = array_slice($semua, $i, $panjang);
            $i += $panjang;
            $syarat = array_values(array_filter($kata, fn (string $t): bool => mb_strlen($t) >= $minimum));
            $alternatif = implode(' ', array_map(
                fn (string $p): string => '"'.str_replace('"', '', $p).'"',
                array_values(array_unique($padanan)),
            ));

            if ($syarat === [] && $alternatif === '') {
                continue;
            }

            $inti = $syarat === [] ? '' : implode(' ', array_map(fn (string $t): string => "+{$t}*", $syarat));

            if ($alternatif === '') {
                $bagian[] = $inti;
            } elseif ($inti === '') {
                continue; // kata pendek tanpa syarat: alternatif saja tidak boleh mewajibkan apa pun
            } else {
                $bagian[] = count($syarat) === 1
                    ? '+('.substr($inti, 1).' '.$alternatif.')'
                    : '+(('.$inti.') '.$alternatif.')';
            }
        }

        $bagian = array_values(array_unique($bagian));

        return $bagian === [] ? null : implode(' ', $bagian);
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
            'penetapan_asc' => $query->orderByRaw('dokumen.tanggal_penetapan IS NULL')->orderBy('dokumen.tanggal_penetapan'),
            'dibuka' => $query->orderByDesc('dokumen.jumlah_dibuka'),
            default => $query->orderByDesc('dokumen.dipublikasikan_pada'),
        };

        // Pemutus seri id DESC (UUIDv7 = terbaru) agar urutan bawaan terbaca dari idx_dokumen_publik_terbaru tanpa filesort.
        $query->orderByDesc('dokumen.dipublikasikan_pada')->orderByDesc('dokumen.id');
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
