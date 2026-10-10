<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Visibilitas dokumen terbit (BR-02): internal hanya untuk pengguna login.
 */
enum Visibilitas: string implements HasColor, HasIcon, HasLabel
{
    case Publik = 'publik';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Publik => 'Publik',
            self::Internal => 'Internal (login)',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Publik => 'success',
            self::Internal => 'warning',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Publik => Heroicon::OutlinedGlobeAlt,
            self::Internal => Heroicon::OutlinedLockClosed,
        };
    }
}
