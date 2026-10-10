<?php

namespace App\Services\Pencarian;

use App\Support\CacheRegulasi;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

/**
 * Memilih mesin pencarian (02-ARSITEKTUR §5.2): Meilisearch bila SCOUT_DRIVER=meilisearch dan cek kesehatan
 * (cache `pencarian:sehat`, 60 dtk) lolos; selain itu cadangan database, dengan peringatan log saat turun.
 */
class PemilihMesinPencarian
{
    public function __construct(private readonly Container $app) {}

    public function pilih(): PencarianDokumen
    {
        if (config('scout.driver') !== 'meilisearch') {
            return $this->app->make(PencarianDatabase::class);
        }

        if (CacheRegulasi::pencarianSehat()) {
            return $this->app->make(PencarianMeilisearch::class);
        }

        Log::warning('Meilisearch tidak sehat; pencarian memakai cadangan database (FULLTEXT).', [
            'host' => config('scout.meilisearch.host'),
        ]);

        return $this->app->make(PencarianDatabase::class);
    }
}
