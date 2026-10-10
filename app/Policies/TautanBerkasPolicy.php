<?php

namespace App\Policies;

use App\Models\Dokumen;
use App\Models\TautanBerkas;
use App\Models\User;

/**
 * Hak atas tautan berkas (PRD §3.2 baris Tautan): kelola = tautan.kelola DAN boleh mengubah dokumen induk
 * (kontributor hanya pada draf/ditolak miliknya, BR-03/BR-16). Lihat mengikuti hak lihat dokumen induk.
 */
class TautanBerkasPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('dokumen.lihat-semua') || $user->can('dokumen.lihat-kategori');
    }

    public function view(User $user, TautanBerkas $tautan): bool
    {
        $dokumen = $tautan->dokumen();

        return $dokumen !== null && $user->can('view', $dokumen);
    }

    public function create(User $user, ?Dokumen $dokumen = null): bool
    {
        return $dokumen !== null && $user->can('tautan.kelola') && $user->can('update', $dokumen);
    }

    public function update(User $user, TautanBerkas $tautan): bool
    {
        $dokumen = $tautan->dokumen();

        return $dokumen !== null && $user->can('tautan.kelola') && $user->can('update', $dokumen);
    }

    public function delete(User $user, TautanBerkas $tautan): bool
    {
        return $this->update($user, $tautan);
    }

    public function restore(User $user, TautanBerkas $tautan): bool
    {
        return false;
    }

    public function forceDelete(User $user, TautanBerkas $tautan): bool
    {
        return false;
    }
}
