<?php

namespace App\Notifications;

/**
 * Ekspor Excel gagal setelah seluruh percobaan → peminta, hanya database.
 */
class EksporLaporanGagal extends NotifikasiRegulasi
{
    public function __construct(public readonly string $jenis)
    {
        $this->siapkanAntrean();
    }

    public function kanal(): array
    {
        return ['database'];
    }

    public function judul(): string
    {
        return 'Ekspor laporan gagal';
    }

    public function isi(): string
    {
        return "Ekspor laporan \"{$this->jenis}\" gagal dibuat. Silakan coba lagi atau hubungi admin.";
    }

    public function warna(): string
    {
        return 'danger';
    }
}
