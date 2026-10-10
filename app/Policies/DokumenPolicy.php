<?php

namespace App\Policies;

use App\Enums\StatusPublikasi;
use App\Models\Dokumen;
use App\Models\User;

/**
 * Hak atas dokumen (PRD §3.2, BR-03, BR-15, BR-16). Admin = pemilik dokumen.lihat-semua;
 * kontributor = dokumen.lihat-kategori (dibatasi kategori yang ditugaskan dan kepemilikan).
 * Super-admin lolos lewat Gate::before (termasuk restore).
 */
class DokumenPolicy
{
    /** Status yang masih boleh diubah/dihapus kontributor pemiliknya. */
    public const array STATUS_DRAF = [StatusPublikasi::Draf, StatusPublikasi::Ditolak];

    public function viewAny(User $user): bool
    {
        return $user->can('dokumen.lihat-semua') || $user->can('dokumen.lihat-kategori');
    }

    public function view(User $user, Dokumen $dokumen): bool
    {
        if ($user->can('dokumen.lihat-semua')) {
            return true;
        }

        return $user->can('dokumen.lihat-kategori') && $this->dalamKategori($user, $dokumen);
    }

    public function create(User $user): bool
    {
        return $user->can('dokumen.buat');
    }

    public function update(User $user, Dokumen $dokumen): bool
    {
        if (! $user->can('dokumen.ubah')) {
            return false;
        }

        if ($user->can('dokumen.lihat-semua')) {
            return $dokumen->status_publikasi !== StatusPublikasi::Diarsipkan;
        }

        return $dokumen->milik($user) && in_array($dokumen->status_publikasi, self::STATUS_DRAF, true);
    }

    public function delete(User $user, Dokumen $dokumen): bool
    {
        return $user->can('dokumen.hapus')
            && in_array($dokumen->status_publikasi, self::STATUS_DRAF, true)
            && ($user->can('dokumen.lihat-semua') || $dokumen->milik($user));
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Dokumen $dokumen): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Dokumen $dokumen): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    private function dalamKategori(User $user, Dokumen $dokumen): bool
    {
        return Dokumen::query()->whereKey($dokumen->getKey())->dalamKategoriPengguna($user)->exists();
    }
}
