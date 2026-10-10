<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Mencatat waktu masuk terakhir pengguna (03-SKEMA §3.1).
 */
class CatatMasukTerakhir
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $event->user->forceFill(['terakhir_masuk_pada' => now()])->saveQuietly();
        }
    }
}
