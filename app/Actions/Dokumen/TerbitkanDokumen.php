<?php

namespace App\Actions\Dokumen;

use App\Enums\StatusPublikasi;
use App\Models\Dokumen;
use App\Models\User;
use App\Support\CacheRegulasi;
use Illuminate\Support\Facades\Gate;

/**
 * Menerbitkan dokumen (US-VER-02, BR-04, BR-05): dari menunggu_verifikasi, atau langsung dari draf oleh admin.
 * Mengisi diverifikasi_oleh/pada, dipublikasikan_pada (hanya pertama kali), dan mengosongkan catatan_verifikasi.
 */
class TerbitkanDokumen extends TransisiPublikasi
{
    public function handle(Dokumen $dokumen, User $oleh, ?string $catatan = null): Dokumen
    {
        Gate::forUser($oleh)->authorize('verifikasi', $dokumen);

        return $this->jalankan(
            $dokumen,
            $oleh,
            dari: [StatusPublikasi::MenungguVerifikasi, StatusPublikasi::Draf],
            ke: StatusPublikasi::Terbit,
            catatan: $catatan,
            wajibLengkap: true,
            ubah: function (Dokumen $baris) use ($oleh): void {
                $baris->diverifikasi_oleh = $oleh->getKey();
                $baris->diverifikasi_pada = now();
                $baris->dipublikasikan_pada ??= now();
                $baris->catatan_verifikasi = null;
            },
        );
    }

    protected function setelahCommit(Dokumen $dokumen, StatusPublikasi $asal): void
    {
        CacheRegulasi::lupakanPublik();

        $this->setelahTerbit($dokumen);
    }

    /**
     * Titik kait setelah terbit (di luar transaksi): diisi F6.2 (penerapan status relasi) dan F7.2 (indeks).
     */
    protected function setelahTerbit(Dokumen $dokumen): void {}
}
