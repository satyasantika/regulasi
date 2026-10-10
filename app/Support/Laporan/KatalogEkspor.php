<?php

namespace App\Support\Laporan;

use App\Enums\StatusPublikasi;
use App\Models\Dokumen;
use App\Models\User;
use InvalidArgumentException;

/**
 * Daftar laporan yang dapat diekspor ke Excel lewat antrean `ekspor` (F11.2–F11.3). Data selalu dibangun ulang
 * untuk peminta (lingkup kategori miliknya), bukan dari isian mentah browser.
 */
final class KatalogEkspor
{
    /** @var array<string, string> */
    public const array JENIS = [
        'statistik' => 'statistik-koleksi',
        'pembukaan-tautan' => 'pembukaan-tautan',
        'relasi-status' => 'relasi-status',
    ];

    /**
     * @param  array<string, mixed>  $parameter
     */
    public static function bangun(string $jenis, array $parameter, User $pengguna): DataEkspor
    {
        $lingkup = LingkupLaporan::untuk($pengguna);

        return match ($jenis) {
            'statistik' => self::statistik($parameter, $lingkup),
            'pembukaan-tautan' => self::pembukaanTautan($parameter, $lingkup),
            'relasi-status' => RelasiStatus::keDataEkspor($lingkup, is_string($parameter['keadaan'] ?? null) ? $parameter['keadaan'] : null),
            default => throw new InvalidArgumentException("Jenis ekspor [{$jenis}] tidak dikenal."),
        };
    }

    public static function namaBerkas(string $jenis): string
    {
        return (self::JENIS[$jenis] ?? $jenis).'-'.now()->format('Ymd-His').'.xlsx';
    }

    /**
     * @param  array<string, mixed>  $parameter
     */
    private static function statistik(array $parameter, LingkupLaporan $lingkup): DataEkspor
    {
        $dimensi = (string) ($parameter['dimensi'] ?? 'jenis');
        $status = (string) ($parameter['status_publikasi'] ?? StatusPublikasi::Terbit->value);
        $dari = self::tahun($parameter['tahun_dari'] ?? null);
        $sampai = self::tahun($parameter['tahun_sampai'] ?? null);
        $baris = StatistikKoleksi::pivot($lingkup, $dimensi, $status, $dari, $sampai);

        $subjudul = collect([
            'Per '.mb_strtolower(StatistikKoleksi::DIMENSI[$dimensi] ?? $dimensi),
            'status publikasi: '.($status === StatistikKoleksi::SEMUA_STATUS ? 'semua' : StatusPublikasi::from($status)->getLabel()),
            $dari !== null || $sampai !== null ? 'tahun '.($dari ?? '…').'–'.($sampai ?? '…') : null,
            $lingkup->adalahGlobal() ? null : 'kategori yang ditugaskan',
        ])->filter()->implode(', ');

        return StatistikKoleksi::keDataEkspor($baris, $dimensi, $subjudul);
    }

    /**
     * @param  array<string, mixed>  $parameter
     */
    private static function pembukaanTautan(array $parameter, LingkupLaporan $lingkup): DataEkspor
    {
        $saringan = PembukaanTautan::saringan([
            'periode' => ['dari' => $parameter['dari'] ?? null, 'sampai' => $parameter['sampai'] ?? null],
            'saluran' => ['value' => $parameter['saluran'] ?? null],
            'kategori' => ['value' => $parameter['kategori'] ?? null],
        ]);

        $baris = PembukaanTautan::perDokumen($lingkup, $saringan)
            ->orderByDesc('dibuka_total')
            ->orderBy('dokumen.sebutan_singkat')
            ->get()
            ->map(fn (Dokumen $d): array => [
                $d->sebutan_singkat,
                $d->judul,
                $d->jenisDokumen->nama,
                (int) $d->getAttribute('dibuka_web'),
                (int) $d->getAttribute('dibuka_api'),
                (int) $d->getAttribute('dibuka_total'),
            ])
            ->values()
            ->all();

        $subjudul = PembukaanTautan::labelPeriode($saringan)
            .($saringan['saluran'] !== null ? ', saluran '.$saringan['saluran'] : '')
            .($lingkup->adalahGlobal() ? '' : ', kategori yang ditugaskan');

        return new DataEkspor('Pembukaan Tautan Berkas', $subjudul, ['Sebutan', 'Judul', 'Jenis dokumen', 'Dibuka via web', 'Dibuka via API', 'Total'], $baris);
    }

    private static function tahun(mixed $nilai): ?int
    {
        return is_numeric($nilai) ? (int) $nilai : null;
    }
}
