<?php

namespace App\Policies;

use App\Models\DokumenRelasi;
use App\Models\User;

/**
 * Hak atas relasi dokumen (PRD §3.2): kelola hanya pemilik relasi.kelola (admin-regulasi; super-admin via
 * Gate::before). Relasi tidak pernah diubah — hapus lalu tambah.
 */
class DokumenRelasiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('dokumen.lihat-semua') || $user->can('dokumen.lihat-kategori');
    }

    public function view(User $user, DokumenRelasi $relasi): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('relasi.kelola');
    }

    public function update(User $user, DokumenRelasi $relasi): bool
    {
        return false;
    }

    public function delete(User $user, DokumenRelasi $relasi): bool
    {
        return $user->can('relasi.kelola');
    }
}
