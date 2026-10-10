<?php

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use App\Support\Laporan\StatistikDasbor;
use Filament\Widgets\ChartWidget;

/**
 * Jumlah dokumen terbit per tahun dokumen, 10 tahun terakhir (F11.1).
 */
class DokumenPerTahunChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Dokumen terbit per tahun (10 tahun terakhir)';

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
        $perTahun = StatistikDasbor::untuk($pengguna)['per_tahun'];

        return [
            'labels' => array_map('strval', array_keys($perTahun)),
            'datasets' => [
                ['label' => 'Dokumen terbit', 'data' => array_values($perTahun)],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
