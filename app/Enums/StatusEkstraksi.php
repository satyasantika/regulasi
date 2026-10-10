<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status ekstraksi teks PDF di balik tautan (02-ARSITEKTUR §4.4). Gagal/terbatas tidak menghalangi terbit (BR-14).
 */
enum StatusEkstraksi: string implements HasColor, HasLabel
{
    case Belum = 'belum';
    case Diproses = 'diproses';
    case Berhasil = 'berhasil';
    case TanpaTeks = 'tanpa_teks';
    case TidakDapatDiunduh = 'tidak_dapat_diunduh';
    case TerlaluBesar = 'terlalu_besar';
    case BukanPdf = 'bukan_pdf';
    case Gagal = 'gagal';
    case TidakBerlaku = 'tidak_berlaku';

    public function getLabel(): string
    {
        return match ($this) {
            self::Belum => 'Belum',
            self::Diproses => 'Diproses',
            self::Berhasil => 'Berhasil',
            self::TanpaTeks => 'Tanpa teks (pindaian)',
            self::TidakDapatDiunduh => 'Tidak dapat diunduh',
            self::TerlaluBesar => 'Terlalu besar',
            self::BukanPdf => 'Bukan PDF',
            self::Gagal => 'Gagal',
            self::TidakBerlaku => 'Tidak berlaku',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Belum, self::TidakBerlaku => 'gray',
            self::Diproses => 'info',
            self::Berhasil => 'success',
            self::TanpaTeks, self::TerlaluBesar, self::BukanPdf, self::TidakDapatDiunduh => 'warning',
            self::Gagal => 'danger',
        };
    }

    /**
     * Status yang ditampilkan di halaman "Tautan Bermasalah" (US-EKS-01).
     *
     * @return list<self>
     */
    public static function bermasalah(): array
    {
        return [self::Gagal, self::TidakDapatDiunduh, self::TerlaluBesar, self::TanpaTeks, self::BukanPdf];
    }

    /**
     * @return list<string>
     */
    public static function nilaiBermasalah(): array
    {
        return array_map(fn (self $status): string => $status->value, self::bermasalah());
    }
}
