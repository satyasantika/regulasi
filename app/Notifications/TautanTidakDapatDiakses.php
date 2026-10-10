<?php

namespace App\Notifications;

use App\Models\Dokumen;
use App\Models\TautanBerkas;
use App\Support\TautanAdmin;

/**
 * Pemeriksaan tautan berubah menjadi tidak_dapat_diakses (US-TAU-03) → penambah tautan + admin-regulasi.
 * URL mentah tidak dicantumkan (dokumen internal, BR-17); cukup tombol ke halaman admin dokumen.
 */
class TautanTidakDapatDiakses extends NotifikasiRegulasi
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

    public function judul(): string
    {
        return "Tautan tidak dapat diakses: {$this->sebutan}";
    }

    public function isi(): string
    {
        return "Tautan \"{$this->namaTautan}\" pada {$this->sebutan} tidak dapat diakses saat diperiksa. Periksa izin berbagi atau ganti tautannya.";
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
