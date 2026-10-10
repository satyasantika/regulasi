<?php

namespace App\Support\Laporan;

use App\Enums\StatusBerlaku;
use App\Enums\StatusPublikasi;
use App\Models\Dokumen;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;

/**
 * Statistik koleksi berbentuk pivot sederhana (PRD §8 "Statistik koleksi", US-LAP-01): baris = nilai dimensi
 * (jenis, tahun, status berlaku, kategori sistem, badan penetap), kolom = jumlah per status berlaku + total.
 */
final class StatistikKoleksi
{
    /** @var array<string, string> */
    public const array DIMENSI = [
        'jenis' => 'Jenis dokumen',
        'tahun' => 'Tahun',
        'status_berlaku' => 'Status berlaku',
        'kategori' => 'Kategori sistem',
        'badan' => 'Badan penetap (T.E.U.)',
    ];

    public const string SEMUA_STATUS = 'semua';

    /**
     * @return list<array{__key: string, label: string, berlaku: int, diubah: int, dicabut: int, tidak_berlaku: int, total: int}>
     */
    public static function pivot(
        LingkupLaporan $lingkup,
        string $dimensi = 'jenis',
        string $statusPublikasi = 'terbit',
        ?int $tahunDari = null,
        ?int $tahunSampai = null,
    ): array {
        if (! array_key_exists($dimensi, self::DIMENSI)) {
            throw new InvalidArgumentException("Dimensi [{$dimensi}] tidak dikenal.");
        }

        $query = $lingkup->terapkan(Dokumen::query())->toBase();

        if ($statusPublikasi !== self::SEMUA_STATUS) {
            $query->where('dokumen.status_publikasi', StatusPublikasi::from($statusPublikasi)->value);
        }

        if ($tahunDari !== null) {
            $query->where('dokumen.tahun', '>=', $tahunDari);
        }

        if ($tahunSampai !== null) {
            $query->where('dokumen.tahun', '<=', $tahunSampai);
        }

        self::kelompokkan($query, $dimensi, $lingkup);

        foreach (StatusBerlaku::cases() as $status) {
            $query->selectRaw("SUM(CASE WHEN dokumen.status_berlaku = ? THEN 1 ELSE 0 END) AS {$status->value}", [$status->value]);
        }

        return $query->selectRaw('COUNT(*) AS total')
            ->get()
            ->map(fn (object $b): array => [
                '__key' => (string) $b->kunci,
                'label' => $dimensi === 'status_berlaku'
                    ? (StatusBerlaku::tryFrom((string) $b->label)?->label() ?? (string) $b->label)
                    : (string) $b->label,
                'berlaku' => (int) $b->berlaku,
                'diubah' => (int) $b->diubah,
                'dicabut' => (int) $b->dicabut,
                'tidak_berlaku' => (int) $b->tidak_berlaku,
                'total' => (int) $b->total,
            ])
            ->values()
            ->all();
    }

    /**
     * Ubah pivot menjadi data ekspor berkolom bahasa Indonesia, dengan baris total.
     *
     * @param  list<array{__key: string, label: string, berlaku: int, diubah: int, dicabut: int, tidak_berlaku: int, total: int}>  $baris
     */
    public static function keDataEkspor(array $baris, string $dimensi, string $subjudul): DataEkspor
    {
        $kolom = [self::DIMENSI[$dimensi] ?? 'Dimensi'];

        foreach (StatusBerlaku::cases() as $status) {
            $kolom[] = $status->label();
        }

        $kolom[] = 'Total';
        $isi = array_map(fn (array $b): array => [$b['label'], $b['berlaku'], $b['diubah'], $b['dicabut'], $b['tidak_berlaku'], $b['total']], $baris);
        $isi[] = [
            'Total',
            array_sum(array_column($baris, 'berlaku')),
            array_sum(array_column($baris, 'diubah')),
            array_sum(array_column($baris, 'dicabut')),
            array_sum(array_column($baris, 'tidak_berlaku')),
            array_sum(array_column($baris, 'total')),
        ];

        return new DataEkspor('Statistik Koleksi Dokumen', $subjudul, $kolom, $isi);
    }

    private static function kelompokkan(Builder $query, string $dimensi, LingkupLaporan $lingkup): void
    {
        match ($dimensi) {
            'jenis' => $query->join('jenis_dokumen', 'jenis_dokumen.id', '=', 'dokumen.jenis_dokumen_id')
                ->groupBy('jenis_dokumen.id', 'jenis_dokumen.nama', 'jenis_dokumen.urutan')
                ->orderBy('jenis_dokumen.urutan')
                ->orderBy('jenis_dokumen.nama')
                ->selectRaw('jenis_dokumen.id AS kunci, jenis_dokumen.nama AS label'),
            'tahun' => $query->groupBy('dokumen.tahun')
                ->orderByDesc('dokumen.tahun')
                ->selectRaw('dokumen.tahun AS kunci, dokumen.tahun AS label'),
            'status_berlaku' => $query->groupBy('dokumen.status_berlaku')
                ->orderBy('dokumen.status_berlaku')
                ->selectRaw('dokumen.status_berlaku AS kunci, dokumen.status_berlaku AS label'),
            'kategori' => $query->join('dokumen_kategori_sistem', 'dokumen_kategori_sistem.dokumen_id', '=', 'dokumen.id')
                ->join('kategori_sistem', 'kategori_sistem.id', '=', 'dokumen_kategori_sistem.kategori_sistem_id')
                ->when($lingkup->kategoriIds !== null, fn (Builder $q) => $q->whereIn('kategori_sistem.id', $lingkup->kategoriIds === [] ? ['00000000-0000-0000-0000-000000000000'] : (array) $lingkup->kategoriIds))
                ->groupBy('kategori_sistem.id', 'kategori_sistem.nama', 'kategori_sistem.urutan')
                ->orderBy('kategori_sistem.urutan')
                ->orderBy('kategori_sistem.nama')
                ->selectRaw('kategori_sistem.id AS kunci, kategori_sistem.nama AS label'),
            'badan' => $query->join('badan_penetap', 'badan_penetap.id', '=', 'dokumen.badan_penetap_id')
                ->groupBy('badan_penetap.id', 'badan_penetap.nama', 'badan_penetap.urutan')
                ->orderBy('badan_penetap.urutan')
                ->orderBy('badan_penetap.nama')
                ->selectRaw('badan_penetap.id AS kunci, badan_penetap.nama AS label'),
            default => throw new InvalidArgumentException("Dimensi [{$dimensi}] tidak dikenal."),
        };
    }
}
