<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use App\Models\LaporanEvaluasi;
use App\Support\Laporan\PdfLaporanEvaluasi;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduh PDF laporan evaluasi (F11.4): dirender ulang dari snapshot ringkasan lalu di-stream; tidak ada berkas
 * yang disimpan (STANDAR-TEKNIS §1a.6). Hanya untuk yang berhak (LaporanEvaluasiPolicy::unduh).
 */
class UnduhLaporanEvaluasiController extends Controller
{
    public function __invoke(LaporanEvaluasi $laporanEvaluasi): StreamedResponse
    {
        Gate::authorize('unduh', $laporanEvaluasi);

        $pdf = PdfLaporanEvaluasi::buat($laporanEvaluasi);

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf->output();
        }, PdfLaporanEvaluasi::namaBerkas($laporanEvaluasi), [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
