<?php

namespace App\Notifications;

use App\Models\Dokumen;
use App\Models\User;
use App\Support\TautanAdmin;

/**
 * Dokumen diajukan untuk diverifikasi (US-NOT-01) → verifikator (admin-regulasi).
 */
class DokumenDiajukan extends NotifikasiRegulasi
{
    public readonly string $dokumenId;

    public readonly string $sebutan;

    public readonly string $judulDokumen;

    public readonly string $pengaju;

    public function __construct(Dokumen $dokumen, User $oleh)
    {
        $this->dokumenId = (string) $dokumen->getKey();
        $this->sebutan = $dokumen->sebutan_singkat;
        $this->judulDokumen = $dokumen->judul;
        $this->pengaju = $oleh->name;
        $this->siapkanAntrean();
    }

    public function judul(): string
    {
        return "Dokumen menunggu verifikasi: {$this->sebutan}";
    }

    public function isi(): string
    {
        return "{$this->pengaju} mengajukan \"{$this->sebutan} tentang {$this->judulDokumen}\" untuk diverifikasi.";
    }

    public function url(): ?string
    {
        return TautanAdmin::dokumen($this->dokumenId);
    }

    public function labelTombol(): string
    {
        return 'Verifikasi dokumen';
    }

    public function warna(): string
    {
        return 'warning';
    }
}
