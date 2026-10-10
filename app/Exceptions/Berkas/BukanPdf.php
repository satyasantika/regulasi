<?php

namespace App\Exceptions\Berkas;

use App\Enums\StatusEkstraksi;

/**
 * Isi unduhan tidak diawali magic bytes %PDF-.
 */
class BukanPdf extends PengunduhanDitolak
{
    public function status(): StatusEkstraksi
    {
        return StatusEkstraksi::BukanPdf;
    }
}
