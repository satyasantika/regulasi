<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Dasar policy master ber-soft-delete (PRD §3.2 baris Master): lihat → master.lihat; kelola → master.kelola.
 * Hapus permanen tidak tersedia (super-admin lolos lewat Gate::before, tetapi resource tidak menyediakan aksinya).
 */
abstract class KebijakanMaster
{
    public function viewAny(User $user): bool
    {
        return $user->can('master.lihat');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('master.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('master.kelola');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('master.kelola');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('master.kelola');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('master.kelola');
    }

    public function restore(User $user, Model $model): bool
    {
        return $user->can('master.kelola');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('master.kelola');
    }

    public function reorder(User $user): bool
    {
        return $user->can('master.kelola');
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
