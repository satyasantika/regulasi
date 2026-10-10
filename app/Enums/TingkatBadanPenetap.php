<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Tingkat badan penetap / T.E.U. badan (03-SKEMA §2).
 */
enum TingkatBadanPenetap: string implements HasLabel
{
    case Negara = 'negara';
    case Kementerian = 'kementerian';
    case Lembaga = 'lembaga';
    case Universitas = 'universitas';
    case Fakultas = 'fakultas';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Negara => 'Negara',
            self::Kementerian => 'Kementerian',
            self::Lembaga => 'Lembaga',
            self::Universitas => 'Universitas',
            self::Fakultas => 'Fakultas',
            self::Lainnya => 'Lainnya',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
