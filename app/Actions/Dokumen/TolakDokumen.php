<?php

namespace App\Actions\Dokumen;

use App\Enums\StatusPublikasi;
use App\Models\Dokumen;
use App\Models\User;
use App\Notifications\DokumenDitolak;
use App\Support\PenerimaNotifikasi;
use Illuminate\Support\Facades\Gate;

/**
 * Menolak dokumen yang menunggu verifikasi (US-VER-02, BR-04): catatan wajib (≥ 10 karakter), disimpan
 * di catatan_verifikasi agar terlihat oleh kontributor.
 */
class TolakDokumen extends TransisiPublikasi
{
    public const int MINIMUM_CATATAN = 10;

    public function handle(Dokumen $dokumen, User $oleh, string $catatan): Dokumen
    {
        Gate::forUser($oleh)->authorize('verifikasi', $dokumen);

        $catatan = $this->wajibCatatan($catatan, self::MINIMUM_CATATAN, 'penolakan');

        return $this->jalankan(
            $dokumen,
            $oleh,
            dari: [StatusPublikasi::MenungguVerifikasi],
            ke: StatusPublikasi::Ditolak,
            catatan: $catatan,
            ubah: function (Dokumen $baris) use ($catatan): void {
                $baris->catatan_verifikasi = $catatan;
            },
        );
    }

    /**
     * Beri tahu pembuat draf beserta catatan penolakan (US-NOT-02).
     */
    protected function setelahCommit(Dokumen $dokumen, StatusPublikasi $asal, User $oleh): void
    {
        PenerimaNotifikasi::kirim(
            PenerimaNotifikasi::pengguna($dokumen->dibuat_oleh),
            new DokumenDitolak($dokumen, $oleh, (string) $dokumen->catatan_verifikasi),
            kecualiId: $oleh->getKey(),
        );
    }
}
