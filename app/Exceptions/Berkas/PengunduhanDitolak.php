<?php

namespace App\Exceptions\Berkas;

use App\Enums\StatusEkstraksi;
use RuntimeException;

/**
 * Penolakan terkendali oleh PengunduhAman (02-ARSITEKTUR §4.3): tidak diulang otomatis, langsung menjadi
 * status ekstraksi yang sesuai.
 */
abstract class PengunduhanDitolak extends RuntimeException
{
    abstract public function status(): StatusEkstraksi;
}
