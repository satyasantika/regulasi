<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Penyedia tautan, terdeteksi otomatis oleh PenguraiTautan (03-SKEMA §2).
 * `sumber_resmi` khusus regulasi: peraturan.go.id, jdih.*.go.id, peraturan.bpk.go.id.
 */
enum PenyediaTautan: string implements HasColor, HasLabel
{
    case GoogleDrive = 'google_drive';
    case GoogleDocs = 'google_docs';
    case Onedrive = 'onedrive';
    case Unsil = 'unsil';
    case SumberResmi = 'sumber_resmi';
    case Lainnya = 'lainnya';

    public function getLabel(): string
    {
        return match ($this) {
            self::GoogleDrive => 'Google Drive',
            self::GoogleDocs => 'Google Docs',
            self::Onedrive => 'OneDrive',
            self::Unsil => 'Unsil',
            self::SumberResmi => 'Sumber Resmi Pemerintah',
            self::Lainnya => 'Lainnya',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::GoogleDrive, self::GoogleDocs => 'info',
            self::SumberResmi => 'success',
            self::Unsil => 'primary',
            default => 'gray',
        };
    }

    public function dapatDipratinjau(): bool
    {
        return in_array($this, [self::GoogleDrive, self::GoogleDocs], true);
    }
}
