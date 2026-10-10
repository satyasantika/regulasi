<?php

namespace App\Jobs;

use App\Models\TautanBerkas;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pemeriksaan keteraksesan tautan (antrean `tautan`). Isi pemeriksaan dibuat di F7.1;
 * untuk sementara job ini kosong agar SimpanTautanBerkas sudah mengantrekannya.
 */
class PeriksaTautanBerkas implements ShouldQueue
{
    use Queueable;

    public function __construct(public TautanBerkas $tautan)
    {
        $this->onQueue('tautan');
    }

    public function handle(): void
    {
        // Diisi di F7.1 (PengunduhAman + status_cek).
    }
}
