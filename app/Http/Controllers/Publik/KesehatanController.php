<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * GET /api/health — pemeriksaan kesehatan untuk pemantauan dan sistem FKIP lain (STANDAR-TEKNIS §6).
 *
 * 200 bila basis data & Redis sehat walau pencarian (Meilisearch) gagal; 503 bila basis data atau Redis gagal.
 */
class KesehatanController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $layanan = [
            'database' => $this->periksa(fn () => DB::select('select 1')),
            'redis' => $this->periksa(fn () => Redis::connection()->ping()),
            'pencarian' => $this->periksaPencarian(),
        ];

        $sehat = $layanan['database'] === 'ok' && $layanan['redis'] === 'ok';

        return response()->json([
            'status' => $sehat ? 'ok' : 'terganggu',
            'versi' => config('regulasi.versi'),
            'waktu' => now()->toIso8601String(),
            'layanan' => $layanan,
        ], $sehat ? 200 : 503);
    }

    private function periksa(callable $cek): string
    {
        try {
            $cek();

            return 'ok';
        } catch (Throwable) {
            return 'gagal';
        }
    }

    private function periksaPencarian(): string
    {
        $host = config('services.meilisearch.host');

        if (! is_string($host) || $host === '') {
            return 'tidak_dikonfigurasi';
        }

        try {
            return Http::timeout(2)->get(rtrim($host, '/').'/health')->successful() ? 'ok' : 'gagal';
        } catch (Throwable) {
            return 'gagal';
        }
    }
}
