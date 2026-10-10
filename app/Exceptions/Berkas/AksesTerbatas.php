<?php

namespace App\Exceptions\Berkas;

/**
 * Sumber mengalihkan ke halaman masuk (mis. accounts.google.com): berkas ada tetapi dibagikan terbatas.
 * Untuk ekstraksi berarti tidak dapat diunduh; untuk pemeriksaan tautan berarti dapat diakses.
 */
class AksesTerbatas extends TautanTidakDapatDiunduh {}
