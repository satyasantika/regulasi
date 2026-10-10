<?php

namespace App\Exceptions\Berkas;

use App\Enums\StatusEkstraksi;

/**
 * Host/IP tidak diizinkan, redirect berlebih, 401/403/404, atau Drive meminta login/konfirmasi (respons HTML).
 */
class TautanTidakDapatDiunduh extends PengunduhanDitolak
{
    public function status(): StatusEkstraksi
    {
        return StatusEkstraksi::TidakDapatDiunduh;
    }
}
