<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ActivityResource\Pages\ListActivities;
use App\Filament\Admin\Resources\ActivityResource\Pages\ViewActivity;
use App\Models\Aktivitas;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Log aktivitas baca-saja (US-AUDIT-01). Akses diatur ActivityPolicy (audit.lihat).
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Aktivitas::class;

    protected static ?string $slug = 'log-aktivitas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Log Aktivitas';

    protected static ?string $modelLabel = 'log aktivitas';

    protected static ?string $pluralModelLabel = 'Log Aktivitas';

    protected static ?int $navigationSort = 90;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ringkasan')
                ->columns(2)
                ->schema([
                    TextEntry::make('created_at')->label('Waktu')->dateTime('d F Y H:i'),
                    TextEntry::make('causer.name')->label('Pelaku')->placeholder('Sistem'),
                    TextEntry::make('log_name')->label('Log')->badge()->placeholder('—'),
                    TextEntry::make('event')->label('Peristiwa')->placeholder('—'),
                    TextEntry::make('description')->label('Deskripsi'),
                    TextEntry::make('subjek')->label('Subjek')
                        ->state(fn (Aktivitas $record): string => self::subjek($record)),
                ]),
            Section::make('Perubahan')
                ->columns(2)
                ->schema([
                    KeyValueEntry::make('nilai_lama')->label('Nilai lama')
                        ->keyLabel('Atribut')->valueLabel('Nilai')
                        ->state(fn (Aktivitas $record): array => self::rata($record->attribute_changes?->get('old'))),
                    KeyValueEntry::make('nilai_baru')->label('Nilai baru')
                        ->keyLabel('Atribut')->valueLabel('Nilai')
                        ->state(fn (Aktivitas $record): array => self::rata($record->attribute_changes?->get('attributes'))),
                    KeyValueEntry::make('properti')->label('Properti lain')
                        ->keyLabel('Kunci')->valueLabel('Nilai')
                        ->state(fn (Aktivitas $record): array => self::rata($record->properties?->all()))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('causer'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d F Y H:i')->sortable(),
                TextColumn::make('causer.name')->label('Pelaku')->placeholder('Sistem'),
                TextColumn::make('log_name')->label('Log')->badge()->placeholder('—'),
                TextColumn::make('description')->label('Deskripsi')->searchable()->wrap(),
                TextColumn::make('subjek')->label('Subjek')
                    ->state(fn (Aktivitas $record): string => self::subjek($record)),
            ])
            ->filters([
                Filter::make('rentang')->label('Rentang tanggal')
                    ->schema([
                        DatePicker::make('dari')->label('Dari'),
                        DatePicker::make('sampai')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, $tanggal) => $q->whereDate('created_at', '>=', $tanggal))
                        ->when($data['sampai'] ?? null, fn (Builder $q, $tanggal) => $q->whereDate('created_at', '<=', $tanggal))),
                SelectFilter::make('log_name')->label('Log')
                    ->options(fn (): array => Aktivitas::query()->whereNotNull('log_name')->distinct()->orderBy('log_name')->pluck('log_name', 'log_name')->all()),
                SelectFilter::make('causer_id')->label('Pelaku')->searchable()
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([ViewAction::make()])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
            'view' => ViewActivity::route('/{record}'),
        ];
    }

    private static function subjek(Aktivitas $record): string
    {
        if ($record->subject_type === null) {
            return '—';
        }

        return class_basename(Relation::getMorphedModel($record->subject_type) ?? $record->subject_type).' · '.$record->subject_id;
    }

    /**
     * Ubah nilai bersarang menjadi teks agar dapat ditampilkan sebagai pasangan kunci–nilai.
     *
     * @return array<string, string>
     */
    private static function rata(mixed $data): array
    {
        $data = $data instanceof Collection ? $data->all() : $data;

        if (! is_array($data)) {
            return [];
        }

        $hasil = [];

        foreach ($data as $kunci => $nilai) {
            $hasil[(string) $kunci] = match (true) {
                $nilai === null => '—',
                is_bool($nilai) => $nilai ? 'ya' : 'tidak',
                is_scalar($nilai) => (string) $nilai,
                default => (string) json_encode($nilai, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            };
        }

        return $hasil;
    }
}
