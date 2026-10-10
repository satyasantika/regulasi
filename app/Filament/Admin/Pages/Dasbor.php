<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\AntreanVerifikasiWidget;
use App\Filament\Admin\Widgets\DibukaTerpopulerWidget;
use App\Filament\Admin\Widgets\DokumenPerJenisChart;
use App\Filament\Admin\Widgets\DokumenPerTahunChart;
use App\Filament\Admin\Widgets\DrafSayaWidget;
use App\Filament\Admin\Widgets\StatistikDokumenOverview;
use Filament\Pages\Dashboard;

/**
 * Dasbor panel admin (F11.1, US-LAP-01; 02-ARSITEKTUR §6). Admin melihat angka global; kontributor hanya
 * kategori yang ditugaskan dan daftar draf/ditolak miliknya sebagai pengganti antrean verifikasi.
 */
class Dasbor extends Dashboard
{
    protected static ?string $title = 'Dasbor';

    public static function getNavigationLabel(): string
    {
        return 'Dasbor';
    }

    public function getWidgets(): array
    {
        return [
            StatistikDokumenOverview::class,
            DokumenPerJenisChart::class,
            DokumenPerTahunChart::class,
            AntreanVerifikasiWidget::class,
            DrafSayaWidget::class,
            DibukaTerpopulerWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 2];
    }
}
