<?php

namespace App\Policies;

use App\Models\User;

/**
 * Pengguna & peran (PRD §3.2): super-admin kelola; admin-regulasi hanya lihat. Tidak ada hapus permanen.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pengguna.kelola') || $user->can('audit.lihat');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('pengguna.kelola') || $user->can('audit.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('pengguna.kelola');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('pengguna.kelola');
    }

    /**
     * Tidak ada hapus permanen; gunakan aktif = false.
     */
    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
