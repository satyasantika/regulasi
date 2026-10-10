<?php

namespace App\Filament\Admin\Pages;

use App\Enums\SaluranBuka;
use App\Filament\Admin\Resources\DokumenResource;
use App\Filament\Admin\Widgets\PembukaanHarianChart;
use App\Jobs\BuatEksporLaporan;
use App\Models\Dokumen;
use App\Models\KategoriSistem;
use App\Models\User;
use App\Support\Laporan\LingkupLaporan;
use App\Support\Laporan\PembukaanTautan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
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
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Laporan pembukaan tautan (F11.3, PRD §8 "Log buka tautan"): per dokumen (web, API, total) terurut total,
 * grafik harian, halaman pertama = 20 terpopuler, ekspor Excel (antrean `ekspor`). Tidak menampilkan ip_hash
 * maupun user_agent kepada siapa pun (BR-17). Kontributor: hanya dokumen kategori yang ditugaskan.
 */
class LaporanPemanfaatan extends Page implements HasTable
{
    use InteractsWithTable;

    public const int JUMLAH_TERPOPULER = 20;

    protected static ?string $slug = 'laporan/pemanfaatan';

    protected static ?string $title = 'Pembukaan Tautan';

    protected static ?string $navigationLabel = 'Pembukaan Tautan';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCursorArrowRays;

    protected static ?int $navigationSort = 30;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('laporan.lihat') ?? false;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    protected function getHeaderWidgets(): array
    {
        return [PembukaanHarianChart::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return ['tableFilters' => $this->tableFilters];
    }

    public function getSubheading(): ?string
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        $saringan = PembukaanTautan::saringan($this->tableFilters ?? []);
        $total = PembukaanTautan::total(LingkupLaporan::untuk($pengguna), $saringan);

        return PembukaanTautan::labelPeriode($saringan).': '.number_format($total['total'], 0, ',', '.').' kali dibuka (web '
            .number_format($total['web'], 0, ',', '.').', API '.number_format($total['api'], 0, ',', '.').'). Pratinjau sematan Drive tidak dihitung.';
    }

    public function table(Table $table): Table
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        $lingkup = LingkupLaporan::untuk($pengguna);

        return $table
            ->query(fn (): Builder => PembukaanTautan::perDokumen($lingkup, PembukaanTautan::saringan($this->tableFilters ?? [])))
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('dibuka_total')->orderBy('dokumen.sebutan_singkat'))
            ->paginated([self::JUMLAH_TERPOPULER, 50, 100])
            ->defaultPaginationPageOption(self::JUMLAH_TERPOPULER)
            ->recordUrl(fn (Dokumen $record): ?string => $record->trashed() ? null : DokumenResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Tidak ada tautan yang dibuka pada periode ini')
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->columns([
                TextColumn::make('peringkat')->label('#')->rowIndex(),
                TextColumn::make('sebutan_singkat')->label('Sebutan')->searchable(),
                TextColumn::make('judul')->label('Judul')->limit(60)->wrap()->toggleable(),
                TextColumn::make('jenisDokumen.nama')->label('Jenis')->toggleable(),
                TextColumn::make('dibuka_web')->label('Web')->numeric()->alignEnd()->sortable(),
                TextColumn::make('dibuka_api')->label('API')->numeric()->alignEnd()->sortable(),
                TextColumn::make('dibuka_total')->label('Total')->numeric()->alignEnd()->sortable()->weight('bold'),
            ])
            ->filters([
                Filter::make('periode')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal')->default(now()->subDays(PembukaanTautan::HARI_BAWAAN - 1)->toDateString())->native(false)->displayFormat('d F Y'),
                        DatePicker::make('sampai')->label('Sampai tanggal')->default(now()->toDateString())->native(false)->displayFormat('d F Y'),
                    ])
                    ->columns(2)
                    ->columnSpan(2),
                SelectFilter::make('saluran')
                    ->label('Saluran')
                    ->options(SaluranBuka::class)
                    // Saringan diterapkan di subquery agregat (PembukaanTautan::log), bukan query luar.
                    ->query(fn (Builder $query): Builder => $query),
                SelectFilter::make('kategori')
                    ->label('Kategori')
                    ->options(fn (): array => KategoriSistem::query()
                        ->when($lingkup->kategoriIds !== null, fn (Builder $q) => $q->whereKey($lingkup->kategoriIds ?? []))
                        ->terurut()
                        ->pluck('nama', 'id')
                        ->all())
                    ->query(fn (Builder $query): Builder => $query),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4);
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
                    $saringan = PembukaanTautan::saringan($this->tableFilters ?? []);

                    BuatEksporLaporan::dispatch('pembukaan-tautan', [
                        'dari' => $saringan['dari']->toDateString(),
                        'sampai' => $saringan['sampai']->toDateString(),
                        'saluran' => $saringan['saluran'],
                        'kategori' => $saringan['kategori'],
                    ], (string) $pengguna->getKey());

                    Notification::make()
                        ->title('Ekspor diantrekan')
                        ->body('Notifikasi berisi tautan unduh akan muncul saat berkas siap.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
