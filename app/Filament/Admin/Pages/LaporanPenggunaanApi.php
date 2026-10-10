<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\KlienApiResource;
use App\Models\KlienApi;
use App\Support\Laporan\PembukaanTautan;
use App\Support\Laporan\PenggunaanApi;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Laporan penggunaan API per klien (F11.3): hanya super-admin & admin-regulasi (KlienApiPolicy::viewAny);
 * kontributor tidak dapat melihatnya.
 */
class LaporanPenggunaanApi extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'laporan/penggunaan-api';

    protected static ?string $title = 'Penggunaan API';

    protected static ?string $navigationLabel = 'Penggunaan API';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static ?int $navigationSort = 50;

    public static function canAccess(): bool
    {
        $pengguna = auth()->user();

        return $pengguna !== null && $pengguna->can('laporan.lihat') && $pengguna->can('viewAny', KlienApi::class);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $saringan = PembukaanTautan::saringan($this->tableFilters ?? []);

                return PenggunaanApi::query($saringan['dari'], $saringan['sampai']);
            })
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('dibuka_api')->orderBy('klien_api.nama'))
            ->recordUrl(fn (KlienApi $record): ?string => $record->trashed() ? null : KlienApiResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Belum ada klien API')
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->columns([
                TextColumn::make('nama')->label('Klien')->searchable(),
                TextColumn::make('kategoriSistem.nama')->label('Sistem')->placeholder('—'),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
                TextColumn::make('dibuka_api')->label('Tautan dibuka via API')->numeric()->alignEnd()->sortable(),
                TextColumn::make('terakhir_dipakai_pada')->label('Terakhir dipakai')->dateTime('d F Y H:i')->placeholder('Belum pernah')->sortable(),
                TextColumn::make('token_aktif')->label('Token aktif')->numeric()->alignEnd(),
                TextColumn::make('kedaluwarsa_terdekat')
                    ->label('Kedaluwarsa terdekat')
                    ->formatStateUsing(fn (mixed $state): string => filled($state) ? CarbonImmutable::parse((string) $state)->translatedFormat('d F Y') : '—')
                    ->placeholder('—'),
            ])
            ->filters([
                Filter::make('periode')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal')->default(now()->subDays(PembukaanTautan::HARI_BAWAAN - 1)->toDateString())->native(false)->displayFormat('d F Y'),
                        DatePicker::make('sampai')->label('Sampai tanggal')->default(now()->toDateString())->native(false)->displayFormat('d F Y'),
                    ])
                    ->columns(2),
            ], layout: FiltersLayout::AboveContent);
    }
}
