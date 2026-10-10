<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\DokumenResource;
use App\Models\Dokumen;
use App\Models\User;
use App\Support\Laporan\StatistikDasbor;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sepuluh dokumen yang tautannya paling sering dibuka 30 hari terakhir (F11.1), dari log_buka_tautan saluran
 * web/api. Angka diambil dari cache agregat dasbor (lingkup kategori pengguna).
 */
class DibukaTerpopulerWidget extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Paling sering dibuka (30 hari)';

    public static function canView(): bool
    {
        return auth()->user()?->can('laporan.lihat') ?? false;
    }

    public function table(Table $table): Table
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        $terpopuler = StatistikDasbor::untuk($pengguna)['terpopuler'];

        return $table
            ->query(fn (): Builder => self::query(array_keys($terpopuler)))
            ->paginated(false)
            ->recordUrl(fn (Dokumen $record): string => DokumenResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Belum ada tautan yang dibuka dalam 30 hari terakhir')
            ->columns([
                TextColumn::make('sebutan_singkat')->label('Sebutan'),
                TextColumn::make('judul')->label('Judul')->limit(60)->wrap(),
                TextColumn::make('jenisDokumen.nama')->label('Jenis'),
                TextColumn::make('dibuka')
                    ->label('Dibuka')
                    ->numeric()
                    ->state(fn (Dokumen $record): int => $terpopuler[(string) $record->getKey()] ?? 0),
            ]);
    }

    /**
     * Dokumen menurut urutan id (terpopuler lebih dulu).
     *
     * @param  list<string>  $ids
     * @return Builder<Dokumen>
     */
    public static function query(array $ids): Builder
    {
        $query = Dokumen::query()->with('jenisDokumen');

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereIn('dokumen.id', $ids)
            ->orderByRaw('FIELD(dokumen.id, '.implode(', ', array_fill(0, count($ids), '?')).')', $ids);
    }
}
