<?php

namespace App\Filament\Admin\Pages;

use App\Enums\StatusBerlaku;
use App\Enums\StatusPublikasi;
use App\Jobs\BuatEksporLaporan;
use App\Models\User;
use App\Support\Laporan\LingkupLaporan;
use App\Support\Laporan\StatistikKoleksi;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Laporan statistik koleksi (F11.2, PRD §8, US-LAP-01): pivot dimensi × status berlaku. Kontributor hanya
 * kategori yang ditugaskan; ekspor Excel (antrean `ekspor`) hanya bagi pemegang laporan.ekspor.
 */
class LaporanStatistik extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'laporan/statistik';

    protected static ?string $title = 'Statistik Koleksi';

    protected static ?string $navigationLabel = 'Statistik Koleksi';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('laporan.lihat') ?? false;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $kolomStatus = array_map(
            fn (StatusBerlaku $status): TextColumn => TextColumn::make($status->value)
                ->label($status->label())
                ->numeric()
                ->alignEnd(),
            StatusBerlaku::cases(),
        );

        return $table
            ->records(fn (?array $filters): array => $this->baris($filters ?? []))
            ->paginated(false)
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->emptyStateHeading('Tidak ada dokumen untuk filter ini')
            ->columns([
                TextColumn::make('label')->label('Dimensi')->weight('medium'),
                ...$kolomStatus,
                TextColumn::make('total')->label('Total')->numeric()->alignEnd()->weight('bold'),
            ])
            ->filters([
                SelectFilter::make('dimensi')
                    ->label('Dimensi')
                    ->options(StatistikKoleksi::DIMENSI)
                    ->default('jenis')
                    ->selectablePlaceholder(false),
                SelectFilter::make('status_publikasi')
                    ->label('Status publikasi')
                    ->options([StatistikKoleksi::SEMUA_STATUS => 'Semua status', ...collect(StatusPublikasi::cases())->mapWithKeys(fn (StatusPublikasi $s): array => [$s->value => $s->getLabel()])->all()])
                    ->default(StatusPublikasi::Terbit->value)
                    ->selectablePlaceholder(false),
                Filter::make('tahun')
                    ->schema([
                        TextInput::make('dari')->label('Tahun dari')->numeric()->minValue(1945)->maxValue(2100),
                        TextInput::make('sampai')->label('Tahun sampai')->numeric()->minValue(1945)->maxValue(2100),
                    ])
                    ->columns(2),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3);
    }

    /**
     * Parameter laporan dari state filter tabel.
     *
     * @param  array<string, mixed>  $filters
     * @return array{dimensi: string, status_publikasi: string, tahun_dari: int|null, tahun_sampai: int|null}
     */
    public static function parameter(array $filters): array
    {
        $dimensi = (string) ($filters['dimensi']['value'] ?? 'jenis');
        $status = (string) ($filters['status_publikasi']['value'] ?? StatusPublikasi::Terbit->value);
        $tahun = fn (mixed $nilai): ?int => is_numeric($nilai) ? (int) $nilai : null;

        return [
            'dimensi' => array_key_exists($dimensi, StatistikKoleksi::DIMENSI) ? $dimensi : 'jenis',
            'status_publikasi' => $status === StatistikKoleksi::SEMUA_STATUS || StatusPublikasi::tryFrom($status) !== null ? $status : StatusPublikasi::Terbit->value,
            'tahun_dari' => $tahun($filters['tahun']['dari'] ?? null),
            'tahun_sampai' => $tahun($filters['tahun']['sampai'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{__key: string, label: string, berlaku: int, diubah: int, dicabut: int, tidak_berlaku: int, total: int}>
     */
    protected function baris(array $filters): array
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        $parameter = self::parameter($filters);

        $baris = StatistikKoleksi::pivot(
            LingkupLaporan::untuk($pengguna),
            $parameter['dimensi'],
            $parameter['status_publikasi'],
            $parameter['tahun_dari'],
            $parameter['tahun_sampai'],
        );

        if ($baris === []) {
            return [];
        }

        $baris[] = [
            '__key' => '__total',
            'label' => 'Total',
            'berlaku' => array_sum(array_column($baris, 'berlaku')),
            'diubah' => array_sum(array_column($baris, 'diubah')),
            'dicabut' => array_sum(array_column($baris, 'dicabut')),
            'tidak_berlaku' => array_sum(array_column($baris, 'tidak_berlaku')),
            'total' => array_sum(array_column($baris, 'total')),
        ];

        return $baris;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('eksporExcel')
                ->label('Ekspor Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (): bool => auth()->user()?->can('laporan.ekspor') ?? false)
                ->action(function (): void {
                    /** @var User $pengguna */
                    $pengguna = auth()->user();
                    abort_unless($pengguna->can('laporan.ekspor'), 403);

                    BuatEksporLaporan::dispatch('statistik', self::parameter($this->tableFilters ?? []), (string) $pengguna->getKey());

                    Notification::make()
                        ->title('Ekspor diantrekan')
                        ->body('Notifikasi berisi tautan unduh akan muncul saat berkas siap.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
