<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\DokumenResource;
use App\Jobs\BuatEksporLaporan;
use App\Models\DokumenRelasi;
use App\Models\User;
use App\Support\Laporan\LingkupLaporan;
use App\Support\Laporan\RelasiStatus;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Laporan relasi status (F11.3): dokumen dicabut/diubah beserta pencabut/pengubah dan tanggal efektif, serta
 * relasi tertunda (efektif = false, diterapkan penjadwal harian saat tanggal efektif tiba — BR-08).
 */
class LaporanRelasiStatus extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'laporan/relasi-status';

    protected static ?string $title = 'Relasi Status';

    protected static ?string $navigationLabel = 'Relasi Status';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?int $navigationSort = 40;

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
        /** @var User $pengguna */
        $pengguna = auth()->user();

        return $table
            ->query(fn (): Builder => RelasiStatus::query(LingkupLaporan::untuk($pengguna)))
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('dokumen_relasi.efektif')->orderByDesc('dokumen_relasi.efektif_pada'))
            ->emptyStateHeading('Tidak ada relasi mencabut/mengubah')
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->columns([
                TextColumn::make('dokumenTerkait.sebutan_singkat')
                    ->label('Dokumen')
                    ->description(fn (DokumenRelasi $record): string => str($record->dokumenTerkait->judul)->limit(60)->toString())
                    ->url(fn (DokumenRelasi $record): ?string => $record->dokumenTerkait->trashed() ? null : DokumenResource::getUrl('view', ['record' => $record->dokumenTerkait])),
                TextColumn::make('dokumenTerkait.status_berlaku')->label('Status berlaku')->badge(),
                TextColumn::make('jenis_relasi')
                    ->label('Relasi')
                    ->badge()
                    ->formatStateUsing(fn (DokumenRelasi $record): string => $record->jenis_relasi->kebalikan()->label()),
                TextColumn::make('dokumen.sebutan_singkat')
                    ->label('Oleh (pencabut/pengubah)')
                    ->description(fn (DokumenRelasi $record): string => str($record->dokumen->judul)->limit(60)->toString())
                    ->url(fn (DokumenRelasi $record): ?string => $record->dokumen->trashed() ? null : DokumenResource::getUrl('view', ['record' => $record->dokumen])),
                TextColumn::make('efektif_pada')->label('Tanggal efektif')->date('d F Y')->placeholder('—')->sortable(),
                TextColumn::make('efektif')
                    ->label('Keadaan')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Efektif' : 'Tertunda')
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning'),
            ])
            ->filters([
                SelectFilter::make('keadaan')
                    ->label('Keadaan')
                    ->options([RelasiStatus::EFEKTIF => 'Efektif', RelasiStatus::TERTUNDA => 'Tertunda (belum efektif)'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        RelasiStatus::EFEKTIF => $query->where('dokumen_relasi.efektif', true),
                        RelasiStatus::TERTUNDA => $query->where('dokumen_relasi.efektif', false),
                        default => $query,
                    }),
            ], layout: FiltersLayout::AboveContent);
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
                    $keadaan = $this->tableFilters['keadaan']['value'] ?? null;

                    BuatEksporLaporan::dispatch('relasi-status', ['keadaan' => is_string($keadaan) ? $keadaan : null], (string) $pengguna->getKey());

                    Notification::make()
                        ->title('Ekspor diantrekan')
                        ->body('Notifikasi berisi tautan unduh akan muncul saat berkas siap.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
