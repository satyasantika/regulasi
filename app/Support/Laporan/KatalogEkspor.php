<?php

namespace App\Support\Laporan;

use App\Enums\StatusPublikasi;
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
    ];

    /**
     * @param  array<string, mixed>  $parameter
     */
    public static function bangun(string $jenis, array $parameter, User $pengguna): DataEkspor
    {
        $lingkup = LingkupLaporan::untuk($pengguna);

        return match ($jenis) {
            'statistik' => self::statistik($parameter, $lingkup),
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

    private static function tahun(mixed $nilai): ?int
    {
        return is_numeric($nilai) ? (int) $nilai : null;
    }
}
