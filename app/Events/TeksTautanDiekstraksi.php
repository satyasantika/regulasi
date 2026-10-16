<?php

namespace App\Events;

use App\Models\EkstraksiTeks;
use App\Models\TautanBerkas;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Ekstraksi teks sebuah tautan selesai (berhasil atau tanpa teks). Dipakai untuk membangun ulang kamus pencarian (F7.2).
 */
class TeksTautanDiekstraksi implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TautanBerkas $tautan,
        public EkstraksiTeks $ekstraksi,
    ) {}
}
