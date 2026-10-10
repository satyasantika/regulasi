<?php

namespace App\Policies;

use App\Models\Ekspor;
use App\Models\User;

/**
 * Berkas ekspor Filament hanya dapat diunduh oleh peminta yang masih memegang laporan.ekspor (F11.2).
 * Super-admin tetap lolos lewat Gate::before.
 */
class EksporPolicy
{
    public function view(User $user, Ekspor $ekspor): bool
    {
        return $ekspor->user_id === $user->getKey() && $user->can('laporan.ekspor');
    }
}
