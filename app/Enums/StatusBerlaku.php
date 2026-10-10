<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status keberlakuan dokumen (03-SKEMA §2, diagram PRD §6.2). Perubahan hanya lewat Action (F6).
 */
enum StatusBerlaku: string implements HasColor, HasLabel
{
    case Berlaku = 'berlaku';
    case Diubah = 'diubah';
    case Dicabut = 'dicabut';
    case TidakBerlaku = 'tidak_berlaku';

    public function label(): string
    {
        return match ($this) {
            self::Berlaku => 'Berlaku',
            self::Diubah => 'Berlaku dengan Perubahan',
            self::Dicabut => 'Dicabut',
            self::TidakBerlaku => 'Tidak Berlaku',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function color(): string
    {
        return match ($this) {
            self::Berlaku => 'success',
            self::Diubah => 'warning',
            self::Dicabut => 'danger',
            self::TidakBerlaku => 'gray',
        };
    }

    public function getColor(): string
    {
        return $this->color();
    }
}
