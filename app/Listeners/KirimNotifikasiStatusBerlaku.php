<?php

namespace App\Listeners;

use App\Events\StatusBerlakuBerubah;
use App\Models\Dokumen;
use App\Notifications\StatusDokumenBerubah;
use App\Support\PenerimaNotifikasi;

/**
 * Status berlaku berubah (dikirim setelah commit) → StatusDokumenBerubah ke kontributor yang ditugaskan pada
 * kategori dokumen + admin-regulasi (US-REL-04, 02-ARSITEKTUR §10). Pelaku perubahan tidak ikut diberi tahu.
 */
class KirimNotifikasiStatusBerlaku
{
    public function handle(StatusBerlakuBerubah $event): void
    {
        $dokumen = Dokumen::query()->find($event->dokumen->getKey());

        if ($dokumen === null) {
            return;
        }

        $event->pemicu?->loadMissing('dokumenTerkait');

        PenerimaNotifikasi::kirim(
            PenerimaNotifikasi::kontributorKategori($dokumen)->merge(PenerimaNotifikasi::admin()),
            new StatusDokumenBerubah($dokumen, $event->dari, $event->ke, $event->pemicu),
            kecualiId: $event->olehId,
        );
    }
}
