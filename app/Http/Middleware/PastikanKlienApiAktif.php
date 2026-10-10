<?php

namespace App\Http\Middleware;

use App\Models\KlienApi;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * API hanya untuk klien API aktif (BR-18): pemilik token harus KlienApi yang aktif dan belum dihapus, selain itu
 * 401 berformat JSON:API. terakhir_dipakai_pada diperbarui maksimal sekali per menit per klien (Cache::add).
 */
class PastikanKlienApiAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var mixed $klien Pemilik token Sanctum (bukan selalu User). */
        $klien = $request->user();

        if (! $klien instanceof KlienApi || ! $klien->aktif || $klien->trashed()) {
            return self::tolak();
        }

        if (Cache::add('api:dipakai:'.$klien->getKey(), true, 60)) {
            // Tanpa event model: tidak menyentuh updated_at maupun log aktivitas.
            KlienApi::query()->whereKey($klien->getKey())->toBase()->update(['terakhir_dipakai_pada' => now()]);
        }

        return $next($request);
    }

    public static function tolak(): JsonResponse
    {
        return response()->json(
            ['errors' => [['status' => '401', 'title' => 'Klien API tidak aktif']]],
            401,
            ['Content-Type' => 'application/vnd.api+json'],
        );
    }
}
