<?php

namespace App\Policies;

use App\Models\User;

/**
 * Hak atas relasi dokumen (PRD §3.2): kelola hanya pemilik relasi.kelola (admin-regulasi; super-admin via
 * Gate::before). Relasi tidak pernah diubah — hapus lalu tambah.
 */
class DokumenRelasiPolicy
{
    public function create(User $user): bool
    {
        return $user->can('relasi.kelola');
    }
}
