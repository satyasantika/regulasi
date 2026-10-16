<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * GET /api/health — pemeriksaan kesehatan untuk pemantauan dan sistem FKIP lain (STANDAR-TEKNIS §1b dan §6).
 *
 * 503 hanya bila basis data gagal. Pencarian (Meilisearch, selama masih dipakai), job tertunda > 10 menit,
 * job gagal 24 jam, dan detak penjadwal (> 5 menit) bersifat informatif agar healthcheck kontainer tidak memicu
 * restart berantai. Tanpa Redis.
 */
class KesehatanController extends Controller
{
    private const DETAK_MAKS_MENIT = 5;

    public function __invoke(): JsonResponse
    {
        $layanan = [
            'database' => $this->periksa(fn () => DB::select('select 1')),
            'pencarian' => $this->periksaPencarian(),
            'job_tertunda' => $this->hitung(fn () => DB::table('jobs')
                ->whereNull('reserved_at')
                ->where('available_at', '<=', now()->subMinutes(10)->getTimestamp())
                ->count()),
            'job_gagal_24j' => $this->hitung(fn () => DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count()),
            'detak_penjadwal' => $this->detakPenjadwal(),
        ];

        $sehat = $layanan['database'] === 'ok';

        return response()->json([
            'status' => $sehat ? 'ok' : 'terganggu',
            'versi' => config('regulasi.versi'),
            'waktu' => now()->toIso8601String(),
            'layanan' => $layanan,
        ], $sehat ? 200 : 503);
    }

    private function detakPenjadwal(): string
    {
        try {
            $detak = Cache::get(config('app.kode').':detak-penjadwal');
        } catch (Throwable) {
            return 'gagal';
        }

        if (! $detak instanceof CarbonInterface) {
            return 'belum_ada';
        }

        return $detak->diffInMinutes(now(), true) > self::DETAK_MAKS_MENIT ? 'terlambat' : 'ok';
    }

    private function hitung(callable $hitung): int|string
    {
        try {
            return (int) $hitung();
        } catch (Throwable) {
            return 'gagal';
        }
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
