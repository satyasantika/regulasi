<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Jenis tautan berkas pemilik Dokumen (03-SKEMA §2). Satu naskah_utama aktif per dokumen.
 */
enum JenisTautan: string implements HasColor, HasLabel
{
    case NaskahUtama = 'naskah_utama';
    case Lampiran = 'lampiran';
    case Abstrak = 'abstrak';
    case Terjemahan = 'terjemahan';
    case HalamanSumber = 'halaman_sumber';
    case Lainnya = 'lainnya';

    public function getLabel(): string
    {
        return match ($this) {
            self::NaskahUtama => 'Naskah Utama',
            self::Lampiran => 'Lampiran',
            self::Abstrak => 'Abstrak',
            self::Terjemahan => 'Terjemahan',
            self::HalamanSumber => 'Halaman Sumber Resmi',
            self::Lainnya => 'Lainnya',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NaskahUtama => 'primary',
            self::HalamanSumber => 'info',
            default => 'gray',
        };
    }

    /**
     * Jenis yang isinya diekstraksi teksnya (02-ARSITEKTUR §4.4); halaman_sumber & lainnya tidak.
     */
    public function diekstraksi(): bool
    {
        return ! in_array($this, [self::HalamanSumber, self::Lainnya], true);
    }
}
