<?php

namespace App\Policies;

use App\Enums\StatusPublikasi;
use App\Enums\Visibilitas;
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

    /**
     * Buka tautan (BR-01, BR-02, BR-17): dokumen induk terbit dan (publik atau pengguna login);
     * atau pengguna staf yang berhak melihat dokumen di panel admin (mis. draf miliknya).
     */
    public function buka(?User $user, TautanBerkas $tautan): bool
    {
        $dokumen = $tautan->dokumen();

        if ($dokumen === null || $dokumen->trashed()) {
            return false;
        }

        if ($dokumen->status_publikasi === StatusPublikasi::Terbit && ($dokumen->visibilitas === Visibilitas::Publik || $user !== null)) {
            return true;
        }

        return $user !== null && $user->can('view', $dokumen);
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
