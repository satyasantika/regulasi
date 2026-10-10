<?php

namespace App\Actions\Dokumen;

use App\Enums\StatusPublikasi;
use App\Models\Dokumen;
use App\Models\User;
use App\Notifications\DokumenDiajukan;
use App\Support\PenerimaNotifikasi;
use Illuminate\Support\Facades\Gate;

/**
 * Mengajukan draf/dokumen ditolak untuk diverifikasi (US-VER-01). Kelengkapan BR-05 wajib; kekurangan
 * dilaporkan sebagai ValidationException (kunci `kelengkapan`). Setelah diajukan, kontributor tidak dapat
 * mengubah dokumen (BR-16, lewat DokumenPolicy::update).
 */
class AjukanVerifikasi extends TransisiPublikasi
{
    public function handle(Dokumen $dokumen, User $oleh): Dokumen
    {
        Gate::forUser($oleh)->authorize('ajukan', $dokumen);

        return $this->jalankan(
            $dokumen,
            $oleh,
            dari: [StatusPublikasi::Draf, StatusPublikasi::Ditolak],
            ke: StatusPublikasi::MenungguVerifikasi,
            wajibLengkap: true,
        );
    }

    /**
     * Beri tahu verifikator setelah commit (US-NOT-01); transaksi gagal → tidak ada notifikasi.
     */
    protected function setelahCommit(Dokumen $dokumen, StatusPublikasi $asal, User $oleh): void
    {
        PenerimaNotifikasi::kirim(PenerimaNotifikasi::admin(), new DokumenDiajukan($dokumen, $oleh), kecualiId: $oleh->getKey());
    }
}
