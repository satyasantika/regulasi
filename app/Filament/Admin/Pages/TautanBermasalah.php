<?php

namespace App\Filament\Admin\Pages;

use App\Enums\StatusCekTautan;
use App\Enums\StatusEkstraksi;
use App\Filament\Admin\Resources\DokumenResource;
use App\Jobs\EkstrakTeksTautan;
use App\Jobs\PeriksaTautanBerkas;
use App\Models\Dokumen;
use App\Models\TautanBerkas;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as KoleksiDasar;
use UnitEnum;

/**
 * Tautan bermasalah (F7.1, US-EKS-01): tautan tidak dapat diakses, ekstraksi gagal/tidak dapat diunduh/terlalu
 * besar/tanpa teks/bukan PDF, dan isi PDF yang berubah (US-EKS-02). Hanya untuk admin (dokumen.verifikasi).
 */
class TautanBermasalah extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'tautan-bermasalah';

    protected static ?string $title = 'Tautan Bermasalah';

    protected static ?string $navigationLabel = 'Tautan Bermasalah';

    protected static string|UnitEnum|null $navigationGroup = 'Dokumen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?int $navigationSort = 30;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('dokumen.verifikasi') ?? false;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(static::query())
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading('Tidak ada tautan bermasalah')
            ->recordUrl(fn (TautanBerkas $record): ?string => ($dokumen = $record->dokumen()) instanceof Dokumen
                ? DokumenResource::getUrl('view', ['record' => $dokumen])
                : null)
            ->columns([
                TextColumn::make('dokumen')
                    ->label('Dokumen')
                    ->state(fn (TautanBerkas $record): ?string => $record->dokumen()?->sebutan_singkat)
                    ->description(fn (TautanBerkas $record): ?string => $record->label),
                TextColumn::make('jenis')->label('Jenis')->badge(),
                TextColumn::make('penyedia')->label('Penyedia')->badge(),
                TextColumn::make('status_cek')->label('Status cek')->badge(),
                TextColumn::make('ekstraksi.status')->label('Ekstraksi')->badge()->placeholder('Belum'),
                TextColumn::make('ekstraksi.pesan')
                    ->label('Keterangan')
                    ->limit(60)
                    ->tooltip(fn (TautanBerkas $record): ?string => $record->ekstraksi?->pesan)
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('ekstraksi.isi_berubah_pada')
                    ->label('Isi berubah')
                    ->dateTime('d F Y H:i')
                    ->placeholder('—'),
                TextColumn::make('dicek_pada')->label('Dicek')->dateTime('d F Y H:i')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('masalah')
                    ->label('Masalah')
                    ->options([
                        'tidak_dapat_diakses' => 'Tautan tidak dapat diakses',
                        'ekstraksi' => 'Ekstraksi bermasalah',
                        'isi_berubah' => 'Isi berkas berubah',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'tidak_dapat_diakses' => $query->where('status_cek', StatusCekTautan::TidakDapatDiakses->value),
                        'ekstraksi' => $query->whereHas('ekstraksi', fn (Builder $q) => $q->whereIn('status', StatusEkstraksi::nilaiBermasalah())),
                        'isi_berubah' => $query->whereHas('ekstraksi', fn (Builder $q) => $q->whereNotNull('isi_berubah_pada')),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('periksaUlang')
                    ->label('Periksa ulang')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->action(fn (TautanBerkas $record) => self::periksaUlang([$record])),
                Action::make('ekstrakUlang')
                    ->label('Ekstrak ulang')
                    ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                    ->visible(fn (TautanBerkas $record): bool => $record->jenis->diekstraksi())
                    ->action(fn (TautanBerkas $record) => self::ekstrakUlang([$record])),
                Action::make('tandaiDitinjau')
                    ->label('Tandai ditinjau')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('gray')
                    ->visible(fn (TautanBerkas $record): bool => $record->ekstraksi?->isi_berubah_pada !== null)
                    ->requiresConfirmation()
                    ->modalDescription('Perubahan isi berkas sudah diperiksa dan dianggap sah. Penanda "isi berubah" dihapus.')
                    ->action(function (TautanBerkas $record): void {
                        $ekstraksi = $record->ekstraksi;

                        if ($ekstraksi === null) {
                            return;
                        }

                        activity('tautan')
                            ->performedOn($record)
                            ->causedBy(auth()->user())
                            ->event('isi-berubah-ditinjau')
                            ->withProperties(['isi_berubah_pada' => $ekstraksi->isi_berubah_pada?->toIso8601String(), 'hash_sha256' => $ekstraksi->hash_sha256])
                            ->log('Perubahan isi berkas ditandai sudah ditinjau');

                        $ekstraksi->forceFill(['isi_berubah_pada' => null])->save();

                        Notification::make()->title('Ditandai sudah ditinjau')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('periksaUlangMassal')
                    ->label('Periksa ulang')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => self::periksaUlang($records)),
                BulkAction::make('ekstrakUlangMassal')
                    ->label('Ekstrak ulang')
                    ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => self::ekstrakUlang($records)),
            ]);
    }

    /**
     * Tautan dokumen (belum dihapus) yang tidak dapat diakses, ekstraksinya bermasalah, atau isinya berubah.
     *
     * @return Builder<TautanBerkas>
     */
    public static function query(): Builder
    {
        return TautanBerkas::query()
            ->with(['ekstraksi', 'pemilik'])
            ->whereHasMorph('pemilik', [Dokumen::class])
            ->where(fn (Builder $q) => $q
                ->where('status_cek', StatusCekTautan::TidakDapatDiakses->value)
                ->orWhereHas('ekstraksi', fn (Builder $e) => $e
                    ->whereIn('status', StatusEkstraksi::nilaiBermasalah())
                    ->orWhereNotNull('isi_berubah_pada')));
    }

    /**
     * @param  iterable<mixed>  $tautan
     */
    private static function periksaUlang(iterable $tautan): void
    {
        $tautan = self::saring($tautan);

        foreach ($tautan as $butir) {
            PeriksaTautanBerkas::dispatch($butir);
        }

        Notification::make()->title($tautan->count().' pemeriksaan tautan diantrekan')->success()->send();
    }

    /**
     * @param  iterable<mixed>  $tautan
     */
    private static function ekstrakUlang(iterable $tautan): void
    {
        $dapat = self::saring($tautan)->filter(fn (TautanBerkas $butir): bool => $butir->jenis->diekstraksi());

        foreach ($dapat as $butir) {
            EkstrakTeksTautan::dispatch((string) $butir->getKey());
        }

        Notification::make()->title($dapat->count().' ekstraksi teks diantrekan')->success()->send();
    }

    /**
     * @param  iterable<mixed>  $butir
     * @return KoleksiDasar<int, TautanBerkas>
     */
    private static function saring(iterable $butir): KoleksiDasar
    {
        return collect($butir)->filter(fn (mixed $b): bool => $b instanceof TautanBerkas)->values();
    }
}
