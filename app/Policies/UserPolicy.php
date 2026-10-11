<?php

namespace App\Policies;

use App\Enums\Peran;
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
     * Penugasan kontributor ke kategori sistem (US-MAS-03): pemilik penugasan.kelola, hanya untuk pengguna berperan kontributor.
     * Sengaja terpisah dari update agar admin-regulasi dapat menugaskan tanpa mengubah data lain pengguna.
     */
    public function aturPenugasan(User $user, User $model): bool
    {
        return $user->can('penugasan.kelola') && $model->hasRole(Peran::Kontributor->value);
    }

    /**
     * Impersonasi: hanya super-admin, bukan diri sendiri, bukan super-admin lain, dan akun sasaran aktif.
     */
    public function impersonate(User $user, User $model): bool
    {
        return $user->hasRole(Peran::SuperAdmin->value)
            && $user->isNot($model)
            && ! $model->hasRole(Peran::SuperAdmin->value)
            && $model->aktif;
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
