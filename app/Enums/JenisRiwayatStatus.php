<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Jenis baris riwayat status dokumen (03-SKEMA §3.12): perubahan status publikasi atau status berlaku.
 */
enum JenisRiwayatStatus: string implements HasColor, HasLabel
{
    case Publikasi = 'publikasi';
    case Berlaku = 'berlaku';

    public function getLabel(): string
    {
        return match ($this) {
            self::Publikasi => 'Publikasi',
            self::Berlaku => 'Berlaku',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Publikasi => 'primary',
            self::Berlaku => 'warning',
        };
    }

    /**
     * Label status (dari/ke) menurut enum yang sesuai dengan jenis riwayat.
     */
    public function labelStatus(?string $nilai): ?string
    {
        if ($nilai === null) {
            return null;
        }

        $status = match ($this) {
            self::Publikasi => StatusPublikasi::tryFrom($nilai),
            self::Berlaku => StatusBerlaku::tryFrom($nilai),
        };

        return $status?->getLabel() ?? $nilai;
    }
}
