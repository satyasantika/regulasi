<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\StatusPublikasi;
use App\Filament\Admin\Resources\DokumenResource;
use App\Models\Dokumen;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Draf dan dokumen ditolak milik pengguna (F11.1) — pengganti antrean verifikasi bagi kontributor.
 */
class DrafSayaWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Draf & dokumen ditolak milik saya';

    public static function canView(): bool
    {
        $pengguna = auth()->user();

        return $pengguna instanceof User && $pengguna->can('dokumen.buat') && ! $pengguna->can('dokumen.verifikasi');
    }

    public function table(Table $table): Table
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        return $table
            ->query(fn () => Dokumen::query()
                ->with('jenisDokumen')
                ->where('dibuat_oleh', $pengguna->getKey())
                ->whereIn('status_publikasi', [StatusPublikasi::Draf->value, StatusPublikasi::Ditolak->value])
                ->latest('updated_at'))
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->recordUrl(fn (Dokumen $record): string => DokumenResource::getUrl('edit', ['record' => $record]))
            ->emptyStateHeading('Tidak ada draf atau dokumen ditolak')
            ->columns([
                TextColumn::make('sebutan_singkat')->label('Sebutan'),
                TextColumn::make('judul')->label('Judul')->limit(60)->wrap(),
                TextColumn::make('status_publikasi')->label('Status')->badge(),
                TextColumn::make('catatan_verifikasi')->label('Catatan verifikasi')->limit(60)->placeholder('—'),
                TextColumn::make('updated_at')->label('Diubah')->dateTime('d F Y H:i'),
            ]);
    }
}
