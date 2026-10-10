<?php

namespace App\Support\Laporan;

use App\Enums\SaluranBuka;
use App\Models\KlienApi;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Penggunaan API per klien (F11.3, PRD §8 "Penggunaan API"): tautan dibuka via API pada periode,
 * terakhir dipakai, jumlah token aktif, dan kedaluwarsa token aktif terdekat.
 */
final class PenggunaanApi
{
    /**
     * @return Builder<KlienApi>
     */
    public static function query(CarbonImmutable $dari, CarbonImmutable $sampai): Builder
    {
        $aktif = fn (Builder $q) => $q->where(fn (Builder $w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));

        return KlienApi::query()
            ->withTrashed()
            ->with('kategoriSistem')
            ->select('klien_api.*')
            ->selectSub(fn ($sub) => $sub->from('log_buka_tautan')
                ->selectRaw('COUNT(*)')
                ->whereColumn('log_buka_tautan.klien_api_id', 'klien_api.id')
                ->where('log_buka_tautan.saluran', SaluranBuka::Api->value)
                ->where('log_buka_tautan.created_at', '>=', $dari->startOfDay())
                ->where('log_buka_tautan.created_at', '<=', $sampai->endOfDay()), 'dibuka_api')
            ->withCount(['tokens as token_aktif' => $aktif])
            ->withMin(['tokens as kedaluwarsa_terdekat' => fn (Builder $q) => $q->where('expires_at', '>', now())], 'expires_at');
    }
}
