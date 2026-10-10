<?php

namespace App\Services\Pencarian;

use App\Enums\StatusPublikasi;
use App\Enums\Visibilitas;
use App\Models\Dokumen;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Meilisearch\Endpoints\Indexes;

/**
 * Pencarian Meilisearch lewat Scout (02-ARSITEKTUR §5.1–§5.2): filter IN [...] per dimensi, visibilitas = publik bila
 * internal tidak disertakan, sort, dan facetDistribution. Faset dimensi yang sedang difilter dihitung dengan
 * pencarian tambahan tanpa filter dimensi itu (sama dengan cadangan database). Dokumen dimuat dari basis data
 * mempertahankan urutan relevansi, tetap dibatasi terbit (+ publik) sebagai pengaman BR-01/BR-02.
 */
class PencarianMeilisearch implements PencarianDokumen
{
    private const array RELASI = ['jenisDokumen', 'badanPenetap', 'kategoriSistem'];

    public function cari(KriteriaPencarian $kriteria): HasilPencarian
    {
        $mentah = $this->telusuri($kriteria, [
            'filter' => $this->filter($kriteria),
            'facets' => HasilPencarian::DIMENSI_FASET,
            'sort' => $this->urutan($kriteria),
            'page' => $kriteria->halaman,
            'hitsPerPage' => $kriteria->perHalaman,
            'attributesToRetrieve' => ['id'],
        ]);

        $id = array_values(array_map(fn (array $hit): string => (string) $hit['id'], (array) ($mentah['hits'] ?? [])));
        $posisi = array_flip($id);

        $dokumen = Dokumen::query()
            ->with(self::RELASI)
            ->whereKey($id)
            ->where('status_publikasi', StatusPublikasi::Terbit->value)
            ->when(! $kriteria->sertakanInternal, fn ($q) => $q->where('visibilitas', Visibilitas::Publik->value))
            ->get()
            ->sortBy(fn (Dokumen $d): int => $posisi[$d->getKey()] ?? PHP_INT_MAX)
            ->values();

        $paginator = new LengthAwarePaginator(
            $dokumen,
            (int) ($mentah['totalHits'] ?? $mentah['estimatedTotalHits'] ?? $dokumen->count()),
            $kriteria->perHalaman,
            $kriteria->halaman,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'halaman'],
        );

        return new HasilPencarian($paginator, $this->faset($kriteria, (array) ($mentah['facetDistribution'] ?? [])), 'meilisearch');
    }

    /**
     * @param  array<string, mixed>  $parameter
     * @return array<string, mixed>
     */
    private function telusuri(KriteriaPencarian $kriteria, array $parameter): array
    {
        /** @var array<string, mixed> $hasil */
        $hasil = Dokumen::search(
            $kriteria->q,
            fn (Indexes $indeks, ?string $q, array $opsi): array => $indeks->rawSearch($q, array_merge($opsi, $parameter)),
        )->raw();

        return $hasil;
    }

    /**
     * Ekspresi filter Meilisearch; dimensi $kecuali tidak disertakan (untuk faset disjungtif).
     */
    private function filter(KriteriaPencarian $kriteria, ?string $kecuali = null): string
    {
        $bagian = [];

        foreach ($kriteria->filterFaset() as $dimensi => $nilai) {
            if ($dimensi !== $kecuali && $nilai !== []) {
                $bagian[] = $dimensi.' IN ['.implode(', ', array_map(self::literal(...), $nilai)).']';
            }
        }

        if ($kriteria->kelompok !== []) {
            $bagian[] = 'kelompok IN ['.implode(', ', array_map(self::literal(...), $kriteria->kelompok)).']';
        }

        if (! $kriteria->sertakanInternal) {
            $bagian[] = 'visibilitas = '.self::literal(Visibilitas::Publik->value);
        }

        return implode(' AND ', $bagian);
    }

    /**
     * @return list<string>
     */
    private function urutan(KriteriaPencarian $kriteria): array
    {
        return match ($kriteria->urut) {
            'terbaru' => ['dipublikasikan_pada_ts:desc'],
            'tahun_desc' => ['tahun:desc'],
            'tahun_asc' => ['tahun:asc'],
            'penetapan_asc' => ['tanggal_penetapan_ts:asc'],
            'dibuka' => ['jumlah_dibuka:desc'],
            default => $kriteria->adaKataKunci() ? [] : ['dipublikasikan_pada_ts:desc'],
        };
    }

    /**
     * @param  array<mixed>  $distribusi
     * @return array<string, array<string, int>>
     */
    private function faset(KriteriaPencarian $kriteria, array $distribusi): array
    {
        $faset = [];

        foreach ($kriteria->filterFaset() as $dimensi => $nilai) {
            if ($nilai !== []) {
                $tambahan = $this->telusuri($kriteria, [
                    'filter' => $this->filter($kriteria, $dimensi),
                    'facets' => [$dimensi],
                    'limit' => 0,
                ]);
                $distribusi[$dimensi] = $tambahan['facetDistribution'][$dimensi] ?? [];
            }

            $baris = array_map('intval', (array) ($distribusi[$dimensi] ?? []));
            $baris = array_combine(array_map('strval', array_keys($baris)), array_values($baris));
            $dimensi === 'tahun' ? krsort($baris) : arsort($baris);
            $faset[$dimensi] = $baris;
        }

        return $faset;
    }

    private static function literal(string|int $nilai): string
    {
        return is_int($nilai) ? (string) $nilai : (string) json_encode($nilai, JSON_UNESCAPED_UNICODE);
    }
}
