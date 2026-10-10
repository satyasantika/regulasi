<?php

namespace App\Filament\Admin\Pages;

use App\Enums\JenisRiwayatStatus;
use App\Enums\StatusPublikasi;
use App\Filament\Admin\Resources\DokumenResource;
use App\Filament\Admin\Resources\DokumenResource\Actions\AksiPublikasi;
use App\Models\Dokumen;
use App\Models\RiwayatStatusDokumen;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Antrean verifikasi (F5.2, US-VER-02): dokumen menunggu_verifikasi, paling lama menunggu di atas.
 * Hanya untuk pemilik dokumen.verifikasi.
 */
class AntreanVerifikasi extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'antrean-verifikasi';

    protected static ?string $title = 'Antrean Verifikasi';

    protected static ?string $navigationLabel = 'Antrean Verifikasi';

    protected static string|UnitEnum|null $navigationGroup = 'Dokumen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('dokumen.verifikasi') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $jumlah = static::jumlahMenunggu();

        return $jumlah > 0 ? (string) $jumlah : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function jumlahMenunggu(): int
    {
        return Dokumen::query()->where('status_publikasi', StatusPublikasi::MenungguVerifikasi->value)->count();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(static::query())
            ->defaultSort('diajukan_pada', 'asc')
            ->emptyStateHeading('Tidak ada dokumen yang menunggu verifikasi')
            ->recordUrl(fn (Dokumen $record): string => DokumenResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('sebutan_singkat')->label('Sebutan')->searchable(),
                TextColumn::make('judul')->label('Judul')->limit(70)->wrap()->searchable(),
                TextColumn::make('pembuat.name')->label('Pembuat')->placeholder('—'),
                TextColumn::make('kategoriSistem.nama')->label('Kategori')->badge()->color('gray'),
                TextColumn::make('diajukan_pada')
                    ->label('Lama menunggu')
                    ->sortable()
                    ->formatStateUsing(fn (mixed $state): string => filled($state) ? CarbonImmutable::parse((string) $state)->diffForHumans() : '—')
                    ->tooltip(fn (Dokumen $record): ?string => filled($record->getAttribute('diajukan_pada'))
                        ? CarbonImmutable::parse((string) $record->getAttribute('diajukan_pada'))->translatedFormat('d F Y H:i')
                        : null),
                TextColumn::make('kelengkapan')
                    ->label('Kelengkapan')
                    ->badge()
                    ->state(fn (Dokumen $record): string => AksiPublikasi::ringkasanKelengkapan($record))
                    ->color(fn (string $state): string => self::lengkap($state) ? 'success' : 'danger'),
            ])
            ->recordActions([
                AksiPublikasi::terbitkan(),
                AksiPublikasi::tolak(),
            ])
            ->toolbarActions([
                AksiPublikasi::terbitkanMassal(),
            ]);
    }

    /**
     * Dokumen menunggu verifikasi beserta waktu pengajuan terakhir (dari riwayat; cadangan updated_at).
     *
     * @return Builder<Dokumen>
     */
    public static function query(): Builder
    {
        $diajukan = RiwayatStatusDokumen::query()
            ->select('created_at')
            ->whereColumn('riwayat_status_dokumen.dokumen_id', 'dokumen.id')
            ->where('jenis', JenisRiwayatStatus::Publikasi->value)
            ->where('ke', StatusPublikasi::MenungguVerifikasi->value)
            ->orderByDesc('created_at')
            ->limit(1);

        return Dokumen::query()
            ->with(['pembuat', 'kategoriSistem', 'jenisDokumen', 'naskahUtama'])
            ->select('dokumen.*')
            ->selectRaw('COALESCE(('.$diajukan->toSql().'), dokumen.updated_at) as diajukan_pada', $diajukan->getBindings())
            ->where('status_publikasi', StatusPublikasi::MenungguVerifikasi->value);
    }

    private static function lengkap(string $ringkasan): bool
    {
        [$terpenuhi, $total] = array_map('intval', explode('/', $ringkasan));

        return $terpenuhi === $total;
    }
}
