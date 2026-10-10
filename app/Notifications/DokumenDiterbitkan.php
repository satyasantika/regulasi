<?php

namespace App\Notifications;

use App\Models\Dokumen;
use App\Models\User;

/**
 * Dokumen diterbitkan (US-NOT-02) → pembuat draf, dengan tautan halaman publik.
 */
class DokumenDiterbitkan extends NotifikasiRegulasi
{
    public readonly string $dokumenId;

    public readonly string $sebutan;

    public readonly string $judulDokumen;

    public readonly string $penerbit;

    public readonly string $urlPublik;

    public function __construct(Dokumen $dokumen, User $oleh)
    {
        $this->dokumenId = (string) $dokumen->getKey();
        $this->sebutan = $dokumen->sebutan_singkat;
        $this->judulDokumen = $dokumen->judul;
        $this->penerbit = $oleh->name;
        $this->urlPublik = route('dokumen.show', ['dokumen' => $dokumen->getKey(), 'slug' => $dokumen->slug]);
        $this->siapkanAntrean();
    }

    public function judul(): string
    {
        return "Dokumen diterbitkan: {$this->sebutan}";
    }

    public function isi(): string
    {
        return "\"{$this->sebutan} tentang {$this->judulDokumen}\" telah diterbitkan oleh {$this->penerbit} dan tampil di portal.";
    }

    public function url(): ?string
    {
        return $this->urlPublik;
    }

    public function labelTombol(): string
    {
        return 'Lihat di portal';
    }

    public function warna(): string
    {
        return 'success';
    }
}
