<?php

namespace App\Exceptions\Berkas;

use App\Enums\StatusEkstraksi;

/**
 * Content-Length atau jumlah byte yang diterima melewati batas config('berkas.ukuran_maks_unduhan_mb').
 */
class BerkasTerlaluBesar extends PengunduhanDitolak
{
    public function status(): StatusEkstraksi
    {
        return StatusEkstraksi::TerlaluBesar;
    }
}
