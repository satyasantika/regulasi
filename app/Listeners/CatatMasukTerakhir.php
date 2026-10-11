<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\Impersonasi;
use Illuminate\Auth\Events\Login;

/**
 * Mencatat waktu masuk terakhir pengguna (03-SKEMA §3.1).
 */
class CatatMasukTerakhir
{
    public function handle(Login $event): void
    {
        // Masuk lewat impersonasi bukan login pengguna itu sendiri.
        if ($event->user instanceof User && ! Impersonasi::aktif()) {
            $event->user->forceFill(['terakhir_masuk_pada' => now()])->saveQuietly();
        }
    }
}
