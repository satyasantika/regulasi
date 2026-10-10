<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status publikasi dokumen (03-SKEMA §2, diagram PRD §6.1). Perubahan hanya lewat Action (F5).
 */
enum StatusPublikasi: string implements HasColor, HasLabel
{
    case Draf = 'draf';
    case MenungguVerifikasi = 'menunggu_verifikasi';
    case Terbit = 'terbit';
    case Ditolak = 'ditolak';
    case Diarsipkan = 'diarsipkan';

    public function label(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::MenungguVerifikasi => 'Menunggu Verifikasi',
            self::Terbit => 'Terbit',
            self::Ditolak => 'Ditolak',
            self::Diarsipkan => 'Diarsipkan',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function color(): string
    {
        return match ($this) {
            self::Draf => 'gray',
            self::MenungguVerifikasi => 'warning',
            self::Terbit => 'success',
            self::Ditolak => 'danger',
            self::Diarsipkan => 'info',
        };
    }

    public function getColor(): string
    {
        return $this->color();
    }

    /**
     * Transisi yang sah menurut diagram PRD §6.1.
     */
    public function bolehTransisiKe(self $ke): bool
    {
        return in_array($ke, match ($this) {
            self::Draf => [self::MenungguVerifikasi, self::Terbit],
            self::MenungguVerifikasi => [self::Terbit, self::Ditolak],
            self::Ditolak => [self::Draf],
            self::Terbit => [self::Diarsipkan],
            self::Diarsipkan => [self::Terbit],
        }, true);
    }
}
