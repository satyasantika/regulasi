<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use App\Jobs\BuatEksporLaporan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Unduh berkas ekspor Excel sementara (F11.2). URL bertanda tangan (24 jam) dan hanya untuk pengguna yang
 * memintanya serta masih memegang laporan.ekspor; berkas di luar storage/app/tmp/ekspor tidak dapat dicapai.
 */
class UnduhEksporController extends Controller
{
    public function __invoke(Request $request, string $berkas): BinaryFileResponse
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}\.xlsx$/', $berkas) === 1, 404);

        $pengguna = $request->user();
        abort_unless($pengguna !== null && (string) $request->query('pemilik') === (string) $pengguna->getAuthIdentifier(), 403);
        abort_unless($pengguna->can('laporan.ekspor'), 403);

        $jalur = BuatEksporLaporan::jalur($berkas);
        abort_unless(File::isFile($jalur), 404, 'Berkas ekspor sudah kedaluwarsa. Silakan ekspor ulang.');

        $nama = Str::of((string) $request->query('nama', 'ekspor.xlsx'))->replaceMatches('/[^A-Za-z0-9._-]/', '-')->toString();

        return response()->download($jalur, str_ends_with($nama, '.xlsx') ? $nama : 'ekspor.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
