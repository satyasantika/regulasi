<?php

namespace App\Listeners;

use App\Events\StatusBerlakuBerubah;
use App\Events\TeksTautanDiekstraksi;
use App\Models\Dokumen;

/**
 * Picu pembangunan ulang kamus pencarian untuk dokumen pemilik saat teks tautannya selesai diekstraksi atau status berlakunya berubah (F7.2).
 * Kedua event dikirim setelah commit; pembangunan ulang digabung (BangunKamusIstilah unik).
 */
class PerbaruiIndeksDokumen
{
    public function handle(TeksTautanDiekstraksi|StatusBerlakuBerubah $event): void
    {
        $dokumen = $event instanceof TeksTautanDiekstraksi
            ? Dokumen::withTrashed()->find($event->tautan->pemilik_id)
            : Dokumen::withTrashed()->find($event->dokumen->getKey());

        $dokumen?->sinkronkanIndeks();
    }
}
