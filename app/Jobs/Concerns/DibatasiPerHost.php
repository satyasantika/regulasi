<?php

namespace App\Jobs\Concerns;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Pembatas laju `unduh-host:{host}` (02-ARSITEKTUR §3.4): melindungi Drive/situs pemerintah dari permintaan
 * beruntun. Job yang terkena dilepas ulang 60 detik.
 */
trait DibatasiPerHost
{
    protected function melebihiBatasHost(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $kunci = "unduh-host:{$host}";

        if (RateLimiter::tooManyAttempts($kunci, (int) config('regulasi.unduh_per_menit_per_host', 30))) {
            return true;
        }

        RateLimiter::hit($kunci, 60);

        return false;
    }
}
