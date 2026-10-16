<?php

namespace App\Actions\Dokumen;

use App\Actions\Relasi\TerapkanRelasiDokumen;
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

    protected function setelahCommit(Dokumen $dokumen, StatusPublikasi $asal, User $oleh): void
    {
        CacheRegulasi::lupakanPublik();

        $this->setelahArsip($dokumen, $oleh);
    }

    /**
     * Titik kait setelah diarsipkan (di luar transaksi): relasi yang dimiliki dokumen sebagai sumber menjadi tidak
     * efektif dan status target dihitung ulang (BR-08, BR-09, F6.2); dokumen keluar dari indeks pencarian (F7.2).
     */
    protected function setelahArsip(Dokumen $dokumen, User $oleh): void
    {
        app(TerapkanRelasiDokumen::class)->handle($dokumen, $oleh);

        $dokumen->sinkronkanIndeks();
    }
}
