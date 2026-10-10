<?php

namespace App\Actions\Dokumen;

use App\Enums\StatusPublikasi;
use App\Models\Dokumen;
use App\Models\User;
use App\Support\CacheRegulasi;
use Illuminate\Support\Facades\Gate;

/**
 * Mengarsipkan dokumen terbit tanpa menghapusnya (US-VER-03, BR-15); catatan wajib.
 */
class ArsipkanDokumen extends TransisiPublikasi
{
    public const int MINIMUM_CATATAN = 10;

    public function handle(Dokumen $dokumen, User $oleh, string $catatan): Dokumen
    {
        Gate::forUser($oleh)->authorize('arsipkan', $dokumen);

        $catatan = $this->wajibCatatan($catatan, self::MINIMUM_CATATAN, 'pengarsipan');

        return $this->jalankan(
            $dokumen,
            $oleh,
            dari: [StatusPublikasi::Terbit],
            ke: StatusPublikasi::Diarsipkan,
            catatan: $catatan,
        );
    }

    protected function setelahCommit(Dokumen $dokumen, StatusPublikasi $asal): void
    {
        CacheRegulasi::lupakanPublik();

        $this->setelahArsip($dokumen);
    }

    /**
     * Titik kait setelah diarsipkan (di luar transaksi): diisi fase berikutnya (indeks pencarian).
     */
    protected function setelahArsip(Dokumen $dokumen): void {}
}
