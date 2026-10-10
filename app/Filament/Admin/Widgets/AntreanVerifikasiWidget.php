<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Pages\AntreanVerifikasi;
use App\Filament\Admin\Resources\DokumenResource;
use App\Models\Dokumen;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Lima dokumen yang paling lama menunggu verifikasi (F11.1). Hanya pemilik dokumen.verifikasi; kontributor
 * melihat DrafSayaWidget.
 */
class AntreanVerifikasiWidget extends TableWidget
{
    public const int JUMLAH = 5;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Antrean verifikasi (5 tertua)';

    public static function canView(): bool
    {
        return auth()->user()?->can('dokumen.verifikasi') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => AntreanVerifikasi::query()->orderBy('diajukan_pada')->limit(self::JUMLAH))
            ->paginated(false)
            ->recordUrl(fn (Dokumen $record): string => DokumenResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Tidak ada dokumen yang menunggu verifikasi')
            ->columns([
                TextColumn::make('sebutan_singkat')->label('Sebutan'),
                TextColumn::make('judul')->label('Judul')->limit(60)->wrap(),
                TextColumn::make('pembuat.name')->label('Pembuat')->placeholder('—'),
                TextColumn::make('diajukan_pada')
                    ->label('Lama menunggu')
                    ->formatStateUsing(fn (mixed $state): string => filled($state) ? CarbonImmutable::parse((string) $state)->diffForHumans() : '—'),
            ]);
    }
}
