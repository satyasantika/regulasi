<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Kelompok jenis dokumen (03-SKEMA §2). Peraturan dan keputusan wajib bernomor (BR-05).
 */
enum KelompokJenisDokumen: string implements HasColor, HasLabel
{
    case Peraturan = 'peraturan';
    case Keputusan = 'keputusan';
    case Kebijakan = 'kebijakan';
    case Pendukung = 'pendukung';

    public function label(): string
    {
        return match ($this) {
            self::Peraturan => 'Peraturan',
            self::Keputusan => 'Keputusan',
            self::Kebijakan => 'Kebijakan (SE/Instruksi)',
            self::Pendukung => 'Dokumen Pendukung',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Peraturan => 'primary',
            self::Keputusan => 'info',
            self::Kebijakan => 'warning',
            self::Pendukung => 'gray',
        };
    }

    public function wajibNomor(): bool
    {
        return match ($this) {
            self::Peraturan, self::Keputusan => true,
            self::Kebijakan, self::Pendukung => false,
        };
    }
}
