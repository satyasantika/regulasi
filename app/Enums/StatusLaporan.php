<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status laporan evaluasi periode (03-SKEMA §3.15): draf dapat diubah & dihitung ulang; final terkunci.
 */
enum StatusLaporan: string implements HasColor, HasLabel
{
    case Draf = 'draf';
    case Final = 'final';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Final => 'Final',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draf => 'gray',
            self::Final => 'success',
        };
    }
}
