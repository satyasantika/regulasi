<?php

namespace App\Actions\Dokumen;

use App\Enums\StatusPublikasi;
use App\Models\Dokumen;
use App\Models\User;
use App\Support\CacheRegulasi;
use Illuminate\Support\Facades\Gate;

/**
 * Memulihkan dokumen diarsipkan menjadi terbit kembali (PRD §6.1, BR-04).
 */
class PulihkanDokumen extends TransisiPublikasi
{
    public function handle(Dokumen $dokumen, User $oleh, ?string $catatan = null): Dokumen
    {
        Gate::forUser($oleh)->authorize('arsipkan', $dokumen);

        return $this->jalankan(
            $dokumen,
            $oleh,
            dari: [StatusPublikasi::Diarsipkan],
            ke: StatusPublikasi::Terbit,
            catatan: $catatan,
        );
    }

    protected function setelahCommit(Dokumen $dokumen, StatusPublikasi $asal): void
    {
        CacheRegulasi::lupakanPublik();
    }
}
