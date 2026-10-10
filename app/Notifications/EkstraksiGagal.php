<?php

namespace App\Notifications;

use App\Models\Dokumen;
use App\Models\TautanBerkas;
use App\Support\TautanAdmin;

/**
 * Ekstraksi teks gagal setelah seluruh percobaan (EkstrakTeksTautan::failed()) → admin-regulasi, hanya database.
 */
class EkstraksiGagal extends NotifikasiRegulasi
{
    public readonly string $dokumenId;

    public readonly string $sebutan;

    public readonly string $namaTautan;

    public function __construct(TautanBerkas $tautan, Dokumen $dokumen, public readonly string $pesan)
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
        return "Ekstraksi teks gagal: {$this->sebutan}";
    }

    public function isi(): string
    {
        return "Teks PDF \"{$this->namaTautan}\" pada {$this->sebutan} gagal diekstraksi. Penjadwal akan mencoba ulang otomatis.";
    }

    public function barisTambahan(): array
    {
        return ['Pesan: '.mb_substr($this->pesan, 0, 200)];
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
