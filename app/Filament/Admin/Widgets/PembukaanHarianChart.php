<?php

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use App\Support\Laporan\LingkupLaporan;
use App\Support\Laporan\PembukaanTautan;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;

/**
 * Grafik pembukaan tautan harian di halaman LaporanPemanfaatan (F11.3); mengikuti filter tabel halaman.
 */
class PembukaanHarianChart extends ChartWidget
{
    /** @var array<string, mixed>|null */
    #[Reactive]
    public ?array $tableFilters = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('laporan.lihat') ?? false;
    }

    public function getHeading(): string
    {
        $saringan = PembukaanTautan::saringan($this->tableFilters ?? []);

        return 'Pembukaan tautan harian ('.PembukaanTautan::labelPeriode($saringan).')';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        $harian = PembukaanTautan::harian(LingkupLaporan::untuk($pengguna), PembukaanTautan::saringan($this->tableFilters ?? []));

        return [
            'labels' => array_map(fn (string $hari): string => CarbonImmutable::parse($hari)->translatedFormat('d M'), array_keys($harian)),
            'datasets' => [
                ['label' => 'Dibuka', 'data' => array_values($harian), 'fill' => true],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
