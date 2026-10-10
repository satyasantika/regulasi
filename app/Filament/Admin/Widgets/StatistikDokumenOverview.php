<?php

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use App\Support\Laporan\StatistikDasbor;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Ringkasan angka dokumen (F11.1, US-LAP-01). Kontributor: hanya kategori yang ditugaskan.
 */
class StatistikDokumenOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('laporan.lihat') ?? false;
    }

    protected function getHeading(): ?string
    {
        return 'Ringkasan dokumen';
    }

    protected function getStats(): array
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        $data = StatistikDasbor::untuk($pengguna);
        $persen = fn (?int $nilai): string => $nilai === null ? '—' : $nilai.'%';

        return [
            Stat::make('Dokumen terbit', number_format($data['total_terbit'], 0, ',', '.'))
                ->color('success'),
            Stat::make('Menunggu verifikasi', number_format($data['menunggu_verifikasi'], 0, ',', '.'))
                ->color($data['menunggu_verifikasi'] > 0 ? 'warning' : 'gray'),
            Stat::make('Dicabut', number_format($data['dicabut'], 0, ',', '.'))
                ->description('Dokumen terbit berstatus dicabut')
                ->color('danger'),
            Stat::make('Tautan dibuka (30 hari)', number_format($data['dibuka_30_hari'], 0, ',', '.'))
                ->description('Web dan API; pratinjau Drive tidak dihitung'),
            Stat::make('Kelengkapan', $persen($data['persen_lengkap']))
                ->description('Abstrak '.$persen($data['persen_abstrak']).' · Teks '.$persen($data['persen_teks'])),
        ];
    }
}
