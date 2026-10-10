<?php

namespace App\Policies;

use App\Models\LaporanEvaluasi;
use App\Models\User;

/**
 * Laporan evaluasi periode (US-LAP-03): berisi angka global, sehingga lihat/unduh memerlukan laporan.lihat dan
 * lingkup global (dokumen.lihat-semua) — kontributor hanya melihat statistik kategori sendiri. Buat/ubah/hitung/
 * finalkan: laporan.ekspor, dan hanya selama berstatus draf.
 */
class LaporanEvaluasiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('laporan.lihat') && $user->can('dokumen.lihat-semua');
    }

    public function view(User $user, LaporanEvaluasi $laporan): bool
    {
        return $this->viewAny($user);
    }

    public function unduh(User $user, LaporanEvaluasi $laporan): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->can('laporan.ekspor');
    }

    public function update(User $user, LaporanEvaluasi $laporan): bool
    {
        return ! $laporan->adalahFinal() && $this->create($user);
    }

    public function finalkan(User $user, LaporanEvaluasi $laporan): bool
    {
        return $this->update($user, $laporan);
    }

    public function delete(User $user, LaporanEvaluasi $laporan): bool
    {
        return $this->update($user, $laporan);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
