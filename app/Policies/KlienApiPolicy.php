<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\KlienApi;
use App\Models\User;

/**
 * Klien API & token (US-API-02, 02-ARSITEKTUR §7.1): semua aksi → klien-api.kelola (super-admin);
 * admin-regulasi hanya melihat. Hapus permanen tidak tersedia.
 */
class KlienApiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('klien-api.kelola') || $user->hasRole(Peran::AdminRegulasi->value);
    }

    public function view(User $user, KlienApi $klien): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('klien-api.kelola');
    }

    public function update(User $user, KlienApi $klien): bool
    {
        return $user->can('klien-api.kelola');
    }

    public function delete(User $user, KlienApi $klien): bool
    {
        return $user->can('klien-api.kelola');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('klien-api.kelola');
    }

    public function restore(User $user, KlienApi $klien): bool
    {
        return $user->can('klien-api.kelola');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('klien-api.kelola');
    }

    /**
     * Membuat dan mencabut token klien.
     */
    public function kelolaToken(User $user, KlienApi $klien): bool
    {
        return $user->can('klien-api.kelola') && ! $klien->trashed();
    }

    public function forceDelete(User $user, KlienApi $klien): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
