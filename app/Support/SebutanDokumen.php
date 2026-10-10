<?php

namespace App\Support;

use App\Models\Dokumen;
use Illuminate\Support\Str;

/**
 * Pembentuk sebutan resmi dan sebutan singkat dokumen (F4.1 butir 4).
 *
 * Contoh: "Peraturan Menteri Keuangan Nomor 181/PMK.06/2016" / "PMK 181/PMK.06/2016";
 * "Peraturan BAN-PT Nomor 13 Tahun 2025" / "BAN-PT 13/2025"; tanpa nomor: "Pedoman/Panduan Tahun 2025".
 * Kolom tetap dapat diubah manual oleh admin; SimpanDokumen hanya memakai hasil ini bila kolom kosong.
 */
class SebutanDokumen
{
    /** Jenis yang namanya dilengkapi nama menteri dari badan penetap ("Kementerian X" → "Menteri X"). */
    public const array JENIS_KEMENTERIAN = ['PERMEN', 'KEPMEN'];

    /** Jenis lembaga yang namanya memakai singkatan badan ("Peraturan BAN-PT", "Peraturan LAMDIK"). */
    public const array JENIS_LEMBAGA = ['PERLEMBAGA'];

    /**
     * @return array{sebutan: string, sebutan_singkat: string}
     */
    public static function buat(Dokumen $dokumen): array
    {
        $dokumen->loadMissing(['jenisDokumen', 'badanPenetap']);

        $nomor = trim((string) $dokumen->nomor);
        $tahun = (int) $dokumen->tahun;
        $namaJenis = self::namaJenisLengkap($dokumen);
        $singkatan = self::singkatan($dokumen, $nomor);

        if ($nomor === '') {
            return [
                'sebutan' => self::potong("{$namaJenis} Tahun {$tahun}", 500),
                'sebutan_singkat' => self::potong("{$singkatan} {$tahun}", 150),
            ];
        }

        $memuatTahun = self::nomorMemuatTahun($nomor, $tahun);

        return [
            'sebutan' => self::potong($memuatTahun ? "{$namaJenis} Nomor {$nomor}" : "{$namaJenis} Nomor {$nomor} Tahun {$tahun}", 500),
            'sebutan_singkat' => self::potong($memuatTahun ? "{$singkatan} {$nomor}" : "{$singkatan} {$nomor}/{$tahun}", 150),
        ];
    }

    /**
     * Nomor seperti "181/PMK.06/2016" sudah memuat tahun sebagai segmen tersendiri.
     */
    public static function nomorMemuatTahun(string $nomor, int $tahun): bool
    {
        return $tahun > 0 && preg_match('/(?<!\d)'.$tahun.'(?!\d)/', $nomor) === 1 && preg_match('/[^0-9]/', $nomor) === 1;
    }

    private static function namaJenisLengkap(Dokumen $dokumen): string
    {
        $jenis = $dokumen->jenisDokumen;
        $badan = $dokumen->badanPenetap;

        if (in_array($jenis->kode, self::JENIS_KEMENTERIAN, true) && Str::startsWith($badan->nama, 'Kementerian ')) {
            $menteri = 'Menteri '.Str::after($badan->nama, 'Kementerian ');

            return Str::contains($jenis->nama, 'Menteri') ? Str::replaceFirst('Menteri', $menteri, $jenis->nama) : "{$jenis->nama} {$menteri}";
        }

        if (in_array($jenis->kode, self::JENIS_LEMBAGA, true)) {
            return 'Peraturan '.($badan->singkatan ?: $badan->nama);
        }

        return $jenis->nama;
    }

    private static function singkatan(Dokumen $dokumen, string $nomor): string
    {
        $jenis = $dokumen->jenisDokumen;
        $badan = $dokumen->badanPenetap;

        // Kode yang tertanam di nomor, mis. "181/PMK.06/2016" → "PMK".
        foreach (array_slice(explode('/', $nomor), 1) as $segmen) {
            if (preg_match('/^([A-Z][A-Z\-]{1,15})(?:[.\-]\d+)*$/', trim($segmen), $cocok) === 1) {
                return $cocok[1];
            }
        }

        if (in_array($jenis->kode, self::JENIS_KEMENTERIAN, true) && filled($jenis->singkatan) && filled($badan->singkatan) && Str::startsWith($badan->singkatan, 'Kemen')) {
            return $jenis->singkatan.Str::lower(Str::after($badan->singkatan, 'Kemen'));
        }

        return $jenis->singkatan ?: ($badan->singkatan ?: $jenis->nama);
    }

    private static function potong(string $teks, int $maks): string
    {
        return rtrim(mb_substr(preg_replace('/\s+/', ' ', trim($teks)) ?? $teks, 0, $maks));
    }
}
