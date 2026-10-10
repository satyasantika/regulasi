<?php

namespace App\Events;

use App\Models\EkstraksiTeks;
use App\Models\TautanBerkas;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Hash isi PDF berbeda dari ekstraksi sebelumnya (US-EKS-02, BR-14): dokumen hukum final semestinya tidak berubah.
 * Notifikasi IsiBerkasBerubah ke admin-regulasi dibangun di F10.
 */
class IsiBerkasBerubah implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TautanBerkas $tautan,
        public EkstraksiTeks $ekstraksi,
        public string $hashLama,
    ) {}
}
