<?php

namespace App\Notifications;

use App\Enums\StatusBerlaku;
use App\Models\Dokumen;
use App\Models\DokumenRelasi;
use App\Support\TautanAdmin;

/**
 * Status berlaku dokumen berubah (US-REL-04) → kontributor kategori dokumen + admin-regulasi.
 * Contoh isi: "Permendikbud 3/2021 kini Dicabut oleh Permendiktisaintek 42/2025".
 */
class StatusDokumenBerubah extends NotifikasiRegulasi
{
    public readonly string $dokumenId;

    public readonly string $sebutan;

    public readonly string $statusLama;

    public readonly string $statusBaru;

    public readonly ?string $pemicu;

    public readonly string $warnaStatus;

    public function __construct(Dokumen $dokumen, StatusBerlaku $dari, StatusBerlaku $ke, ?DokumenRelasi $relasi = null)
    {
        $this->dokumenId = (string) $dokumen->getKey();
        $this->sebutan = $dokumen->sebutan_singkat;
        $this->statusLama = $dari->label();
        $this->statusBaru = $ke->label();
        $this->warnaStatus = $ke->color();
        $this->pemicu = $relasi !== null && $relasi->jenis_relasi->dampakStatus() !== null
            ? $relasi->jenis_relasi->label().' '.$relasi->dokumenTerkait->sebutan_singkat
            : null;
        $this->siapkanAntrean();
    }

    public function judul(): string
    {
        return "Status berlaku berubah: {$this->sebutan}";
    }

    public function isi(): string
    {
        $kalimat = $this->pemicu !== null
            ? "{$this->sebutan} kini {$this->pemicu}."
            : "{$this->sebutan} kini berstatus {$this->statusBaru}.";

        return $kalimat." Status: {$this->statusLama} → {$this->statusBaru}.";
    }

    public function url(): ?string
    {
        return TautanAdmin::dokumen($this->dokumenId);
    }

    public function warna(): string
    {
        return $this->warnaStatus === 'gray' ? 'info' : $this->warnaStatus;
    }
}
