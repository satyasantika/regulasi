<?php

namespace App\Notifications;

use App\Models\Dokumen;
use App\Models\TautanBerkas;
use App\Support\TautanAdmin;

/**
 * Hash isi PDF berbeda dari ekstraksi sebelumnya (US-EKS-02, BR-14) → admin-regulasi, hanya database.
 */
class IsiBerkasBerubah extends NotifikasiRegulasi
{
    public readonly string $dokumenId;

    public readonly string $sebutan;

    public readonly string $namaTautan;

    public function __construct(TautanBerkas $tautan, Dokumen $dokumen)
    {
        $this->dokumenId = (string) $dokumen->getKey();
        $this->sebutan = $dokumen->sebutan_singkat;
        $this->namaTautan = $tautan->label ?: $tautan->jenis->getLabel();
        $this->siapkanAntrean();
    }

    public function kanal(): array
    {
        return ['database'];
    }

    public function judul(): string
    {
        return "Isi berkas berubah: {$this->sebutan}";
    }

    public function isi(): string
    {
        return "Isi PDF \"{$this->namaTautan}\" pada {$this->sebutan} berbeda dari ekstraksi sebelumnya. Pastikan berkas di balik tautan masih naskah yang benar.";
    }

    public function url(): ?string
    {
        return TautanAdmin::dokumen($this->dokumenId);
    }

    public function warna(): string
    {
        return 'warning';
    }
}
