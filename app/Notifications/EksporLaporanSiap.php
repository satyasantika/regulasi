<?php

namespace App\Notifications;

use Illuminate\Support\Facades\URL;

/**
 * Berkas ekspor Excel siap (F11.2) → peminta, hanya database. Tautan bertanda tangan 24 jam dan terikat ke
 * peminta (pengguna lain ditolak meskipun memegang URL-nya).
 */
class EksporLaporanSiap extends NotifikasiRegulasi
{
    public readonly string $urlUnduh;

    public function __construct(public readonly string $judulLaporan, string $berkas, string $namaUnduhan, string $pemilikId)
    {
        $this->urlUnduh = URL::temporarySignedRoute('ekspor.unduh', now()->addHours(24), [
            'berkas' => $berkas,
            'pemilik' => $pemilikId,
            'nama' => $namaUnduhan,
        ]);
        $this->siapkanAntrean();
    }

    public function kanal(): array
    {
        return ['database'];
    }

    public function judul(): string
    {
        return "Ekspor siap: {$this->judulLaporan}";
    }

    public function isi(): string
    {
        return 'Berkas Excel dapat diunduh selama 24 jam, setelah itu dihapus otomatis.';
    }

    public function url(): ?string
    {
        return $this->urlUnduh;
    }

    public function labelTombol(): string
    {
        return 'Unduh Excel';
    }

    public function warna(): string
    {
        return 'success';
    }
}
