<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Jenis relasi antardokumen (03-SKEMA §3.11), dilihat dari sisi `dokumen_id`. Setiap relasi disimpan sebagai
 * dua baris simetris (BR-07): arah aktif (mencabut/mengubah/melaksanakan) dan kebalikannya.
 */
enum JenisRelasi: string implements HasColor, HasLabel
{
    case Mencabut = 'mencabut';
    case DicabutOleh = 'dicabut_oleh';
    case Mengubah = 'mengubah';
    case DiubahOleh = 'diubah_oleh';
    case Melaksanakan = 'melaksanakan';
    case DilaksanakanOleh = 'dilaksanakan_oleh';

    public function label(): string
    {
        return match ($this) {
            self::Mencabut => 'Mencabut',
            self::DicabutOleh => 'Dicabut oleh',
            self::Mengubah => 'Mengubah',
            self::DiubahOleh => 'Diubah oleh',
            self::Melaksanakan => 'Melaksanakan',
            self::DilaksanakanOleh => 'Dilaksanakan oleh',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * Label ramah dari sisi dokumen yang sedang dibuka, mis. "Dokumen ini mencabut …".
     */
    public function labelSisiIni(): string
    {
        return 'Dokumen ini '.mb_strtolower($this->label()).' …';
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Mencabut, self::DicabutOleh => 'danger',
            self::Mengubah, self::DiubahOleh => 'warning',
            self::Melaksanakan, self::DilaksanakanOleh => 'info',
        };
    }

    /**
     * Jenis relasi pada baris pasangan (BR-07).
     */
    public function kebalikan(): self
    {
        return match ($this) {
            self::Mencabut => self::DicabutOleh,
            self::DicabutOleh => self::Mencabut,
            self::Mengubah => self::DiubahOleh,
            self::DiubahOleh => self::Mengubah,
            self::Melaksanakan => self::DilaksanakanOleh,
            self::DilaksanakanOleh => self::Melaksanakan,
        };
    }

    /**
     * Arah aktif = baris milik dokumen sumber (pencabut/pengubah/pelaksana).
     */
    public function adalahArahAktif(): bool
    {
        return in_array($this, self::arahAktif(), true);
    }

    /**
     * Dampak relasi efektif terhadap status berlaku pemilik baris (BR-08).
     */
    public function dampakStatus(): ?StatusBerlaku
    {
        return match ($this) {
            self::DicabutOleh => StatusBerlaku::Dicabut,
            self::DiubahOleh => StatusBerlaku::Diubah,
            default => null,
        };
    }

    /**
     * @return list<self>
     */
    public static function arahAktif(): array
    {
        return [self::Mencabut, self::Mengubah, self::Melaksanakan];
    }

    /**
     * Jenis yang memengaruhi status berlaku pemilik baris (BR-09).
     *
     * @return list<self>
     */
    public static function berdampakStatus(): array
    {
        return [self::DicabutOleh, self::DiubahOleh];
    }
}
