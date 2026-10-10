<?php

namespace App\Filament\Admin\Resources\DokumenResource\RelationManagers;

use App\Models\RiwayatStatusDokumen;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Riwayat status dokumen (baca saja, append-only; 03-SKEMA §3.12).
 */
class RiwayatStatusRelationManager extends RelationManager
{
    protected static string $relationship = 'riwayatStatus';

    protected static ?string $title = 'Riwayat status';

    protected static ?string $modelLabel = 'riwayat status';

    protected static ?string $pluralModelLabel = 'Riwayat status';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('pengguna'))
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Belum ada perubahan status')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d F Y H:i'),
                TextColumn::make('jenis')->label('Jenis')->badge(),
                TextColumn::make('perubahan')
                    ->label('Perubahan')
                    ->state(fn (RiwayatStatusDokumen $record): string => ($record->labelDari() ?? '—').' → '.$record->labelKe()),
                TextColumn::make('pengguna.name')->label('Oleh')->placeholder('Sistem'),
                TextColumn::make('catatan')->label('Catatan')->wrap()->placeholder('—'),
            ]);
    }
}
