<?php

namespace App\Policies;

use App\Models\RiwayatStatusDokumen;
use App\Models\User;

/**
 * Riwayat status bersifat baca saja (append-only): hanya ditulis Action alur publikasi/status berlaku.
 * Melihat riwayat mengikuti hak melihat dokumennya.
 */
class RiwayatStatusDokumenPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('dokumen.lihat-semua') || $user->can('dokumen.lihat-kategori');
    }

    public function view(User $user, RiwayatStatusDokumen $riwayat): bool
    {
        return $user->can('view', $riwayat->dokumen);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, RiwayatStatusDokumen $riwayat): bool
    {
        return false;
    }

    public function delete(User $user, RiwayatStatusDokumen $riwayat): bool
    {
        return false;
    }
}
