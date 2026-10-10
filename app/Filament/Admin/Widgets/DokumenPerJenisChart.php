<?php

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use App\Support\Laporan\StatistikDasbor;
use Filament\Widgets\ChartWidget;

/**
 * Jumlah dokumen terbit per jenis (F11.1).
 */
class DokumenPerJenisChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Dokumen terbit per jenis';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('laporan.lihat') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        $perJenis = StatistikDasbor::untuk($pengguna)['per_jenis'];

        return [
            'labels' => array_column($perJenis, 'nama'),
            'datasets' => [
                ['label' => 'Dokumen terbit', 'data' => array_column($perJenis, 'jumlah')],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
