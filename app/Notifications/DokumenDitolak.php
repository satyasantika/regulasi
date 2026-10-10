<?php

namespace App\Notifications;

use App\Models\Dokumen;
use App\Models\User;
use App\Support\TautanAdmin;

/**
 * Dokumen ditolak (US-NOT-02, BR-04) → pembuat draf, memuat catatan penolakan.
 */
class DokumenDitolak extends NotifikasiRegulasi
{
    public readonly string $dokumenId;

    public readonly string $sebutan;

    public readonly string $judulDokumen;

    public readonly string $penolak;

    public function __construct(Dokumen $dokumen, User $oleh, public readonly string $catatan)
    {
        $this->dokumenId = (string) $dokumen->getKey();
        $this->sebutan = $dokumen->sebutan_singkat;
        $this->judulDokumen = $dokumen->judul;
        $this->penolak = $oleh->name;
        $this->siapkanAntrean();
    }

    public function judul(): string
    {
        return "Dokumen ditolak: {$this->sebutan}";
    }

    public function isi(): string
    {
        return "\"{$this->sebutan} tentang {$this->judulDokumen}\" ditolak oleh {$this->penolak}. Perbaiki lalu ajukan kembali.";
    }

    public function barisTambahan(): array
    {
        return ["Catatan: {$this->catatan}"];
    }

    public function url(): ?string
    {
        return TautanAdmin::dokumen($this->dokumenId);
    }

    public function warna(): string
    {
        return 'danger';
    }
}
