<?php

namespace App\Services\Berkas;

use Illuminate\Support\Facades\Process;
use Spatie\PdfToText\Pdf;

/**
 * Pembungkus poppler-utils: jumlah halaman (pdfinfo) dan teks UTF-8 (pdftotext via spatie/pdf-to-text).
 */
class PengekstrakTeksPdf
{
    private const int TIMEOUT_DETIK = 120;

    public function jumlahHalaman(string $path): ?int
    {
        $hasil = Process::timeout(30)->run([(string) config('regulasi.pdfinfo_bin'), $path]);

        if (! $hasil->successful() || preg_match('/^Pages:\s+(\d+)/m', $hasil->output(), $cocok) !== 1) {
            return null;
        }

        return (int) $cocok[1];
    }

    public function teks(string $path): string
    {
        $teks = (new Pdf((string) config('regulasi.pdftotext_bin')))
            ->setPdf($path)
            ->setOptions(['enc UTF-8'])
            ->setTimeout(self::TIMEOUT_DETIK)
            ->text();

        return self::normalisasi($teks);
    }

    /**
     * UTF-8 sah, tanpa karakter kontrol (kecuali baris baru), spasi beruntun dirapatkan, maksimal satu baris kosong.
     */
    public static function normalisasi(string $teks): string
    {
        $teks = mb_scrub($teks, 'UTF-8');
        $teks = str_replace(["\r\n", "\r", "\f"], "\n", $teks);
        $teks = (string) preg_replace('/[^\P{C}\n\t]+/u', ' ', $teks);
        $teks = (string) preg_replace('/[\t \x{00A0}\x{2000}-\x{200B}\x{3000}]+/u', ' ', $teks);
        $teks = (string) preg_replace('/ *\n */', "\n", $teks);
        $teks = (string) preg_replace("/\n{3,}/", "\n\n", $teks);

        return trim($teks);
    }
}
