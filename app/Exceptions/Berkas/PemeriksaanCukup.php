<?php

namespace App\Exceptions\Berkas;

use RuntimeException;

/**
 * Penanda internal PengunduhAman::periksa(): header 2xx sudah diterima sehingga transfer dihentikan tanpa membaca isi.
 */
class PemeriksaanCukup extends RuntimeException {}
