<?php

namespace App\Events;

use App\Enums\StatusBerlaku;
use App\Models\Dokumen;
use App\Models\DokumenRelasi;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Status berlaku dokumen berubah (BR-08, BR-09, BR-12). Dikirim setelah transaksi di-commit; pendengar indeks
 * pencarian (F7) dan notifikasi StatusDokumenBerubah (F10) ditambahkan kemudian.
 */
class StatusBerlakuBerubah implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Dokumen $dokumen,
        public StatusBerlaku $dari,
        public StatusBerlaku $ke,
        public ?DokumenRelasi $pemicu = null,
        public ?string $olehId = null,
    ) {}
}
