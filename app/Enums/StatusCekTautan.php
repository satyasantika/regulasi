<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Hasil pemeriksaan keteraksesan tautan (informatif, tidak memblokir simpan/terbit).
 */
enum StatusCekTautan: string implements HasColor, HasLabel
{
    case Belum = 'belum';
    case DapatDiakses = 'dapat_diakses';
    case TidakDapatDiakses = 'tidak_dapat_diakses';

    public function getLabel(): string
    {
        return match ($this) {
            self::Belum => 'Belum diperiksa',
            self::DapatDiakses => 'Dapat diakses',
            self::TidakDapatDiakses => 'Tidak dapat diakses',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Belum => 'gray',
            self::DapatDiakses => 'success',
            self::TidakDapatDiakses => 'danger',
        };
    }
}
