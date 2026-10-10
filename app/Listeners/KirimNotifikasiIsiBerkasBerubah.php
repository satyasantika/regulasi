<?php

namespace App\Listeners;

use App\Events\IsiBerkasBerubah;
use App\Notifications\IsiBerkasBerubah as NotifikasiIsiBerkasBerubah;
use App\Support\PenerimaNotifikasi;

/**
 * Hash isi PDF berubah (event setelah commit dari EkstrakTeksTautan) → notifikasi database ke admin-regulasi
 * (US-EKS-02, BR-14).
 */
class KirimNotifikasiIsiBerkasBerubah
{
    public function handle(IsiBerkasBerubah $event): void
    {
        $dokumen = $event->tautan->dokumen();

        if ($dokumen === null) {
            return;
        }

        PenerimaNotifikasi::kirim(PenerimaNotifikasi::admin(), new NotifikasiIsiBerkasBerubah($event->tautan, $dokumen));
    }
}
