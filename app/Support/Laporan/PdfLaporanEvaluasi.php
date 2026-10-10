<?php

namespace App\Support\Laporan;

use App\Models\LaporanEvaluasi;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * Merender PDF laporan evaluasi dari snapshot ringkasan (A4 portrait). Hasil hanya di-stream, tidak pernah
 * disimpan (STANDAR-TEKNIS §1a.6).
 */
final class PdfLaporanEvaluasi
{
    public static function buat(LaporanEvaluasi $laporan): DomPdf
    {
        return Pdf::loadView('laporan.evaluasi', [
            'laporan' => $laporan,
            'r' => $laporan->ringkasan,
            'dicetakPada' => now(),
        ])->setPaper('a4', 'portrait')->setOption('isFontSubsettingEnabled', true);
    }

    public static function namaBerkas(LaporanEvaluasi $laporan): string
    {
        return 'laporan-evaluasi-'.$laporan->periode_mulai->format('Ymd').'-'.$laporan->periode_selesai->format('Ymd').'.pdf';
    }

    /**
     * Baris teks bebas (analisis/tindak lanjut) dipecah per baris agar dirender aman tanpa {!! !!}.
     *
     * @return list<string>
     */
    public static function paragraf(?string $teks): array
    {
        if ($teks === null || trim($teks) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $teks) ?: []), fn (string $b): bool => $b !== ''));
    }
}
