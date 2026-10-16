<?php

namespace App\Jobs;

use App\Services\Pencarian\BangunKamus;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Membangun ulang kamus istilah pencarian (koneksi `database-berat`, antrean `berat`). Dipicu saat dokumen
 * terbit/diubah/diarsipkan, teks diekstraksi, atau status berlaku berubah; pemicu beruntun digabung (unik sampai
 * job mulai diproses) sehingga perubahan yang datang saat job berjalan tetap memicu satu pembangunan ulang lagi.
 */
class BangunKamusIstilah implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct()
    {
        $this->onConnection('database-berat')->onQueue('berat');
    }

    public function uniqueId(): string
    {
        return 'bangun-kamus';
    }

    public function handle(BangunKamus $bangun): void
    {
        $bangun->handle();
    }
}
