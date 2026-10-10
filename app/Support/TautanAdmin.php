<?php

namespace App\Support;

use App\Filament\Admin\Resources\DokumenResource;

/**
 * URL halaman panel admin untuk notifikasi (berlaku juga di luar request panel, mis. di antrean/penjadwal).
 */
final class TautanAdmin
{
    private const string PANEL = 'admin';

    public static function dokumen(string $id): string
    {
        return DokumenResource::getUrl('view', ['record' => $id], panel: self::PANEL);
    }
}
