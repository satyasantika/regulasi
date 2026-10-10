<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\KlienApiResource\Pages\CreateKlienApi;
use App\Filament\Admin\Resources\KlienApiResource\Pages\EditKlienApi;
use App\Filament\Admin\Resources\KlienApiResource\Pages\ListKlienApi;
use App\Filament\Admin\Resources\KlienApiResource\Pages\ViewKlienApi;
use App\Filament\Admin\Resources\KlienApiResource\RelationManagers\TokenRelationManager;
use App\Models\KategoriSistem;
use App\Models\KlienApi;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Klien API dan tokennya (US-API-02, BR-18). Otorisasi lewat KlienApiPolicy: kelola → klien-api.kelola
 * (super-admin), admin-regulasi lihat saja. Token dibuat/dicabut di relation manager Token.
 */
class KlienApiResource extends Resource
{
    protected static ?string $model = KlienApi::class;

    protected static ?string $slug = 'klien-api';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Klien API';

    protected static ?string $modelLabel = 'klien API';

    protected static ?string $pluralModelLabel = 'Klien API';

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('nama')
                    ->label('Nama klien')
                    ->required()
                    ->maxLength(150)
                    ->placeholder('Sistem Aset FKIP'),
                Select::make('kategori_sistem_id')
                    ->label('Kategori sistem')
                    ->options(fn (): array => KategoriSistem::query()->orderBy('urutan')->pluck('nama', 'id')->all())
                    ->searchable(),
                TextInput::make('kontak_nama')
                    ->label('Nama kontak')
                    ->maxLength(150),
                TextInput::make('kontak_email')
                    ->label('Surel kontak')
                    ->email()
                    ->maxLength(255),
                Toggle::make('aktif')
                    ->label('Aktif')
                    ->default(true)
                    ->helperText('Klien nonaktif: semua tokennya ditolak (401).'),
                Textarea::make('catatan')
                    ->label('Catatan')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nama')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('kategoriSistem')
                ->withCount(['tokens as jumlah_token_aktif' => fn (Builder $q): Builder => $q
                    ->where(fn (Builder $w): Builder => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()))]))
            ->columns([
                TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                TextColumn::make('kategoriSistem.nama')->label('Kategori')->badge()->placeholder('—'),
                TextColumn::make('kontak_nama')->label('Kontak')->placeholder('—')->description(fn (KlienApi $record): ?string => $record->kontak_email),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
                TextColumn::make('jumlah_token_aktif')->label('Token aktif')->numeric(),
                TextColumn::make('terakhir_dipakai_pada')
                    ->label('Terakhir dipakai')
                    ->dateTime('d F Y H:i', 'Asia/Jakarta')
                    ->placeholder('Belum pernah')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('aktif')->label('Aktif'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TokenRelationManager::class,
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKlienApi::route('/'),
            'create' => CreateKlienApi::route('/create'),
            'view' => ViewKlienApi::route('/{record}'),
            'edit' => EditKlienApi::route('/{record}/edit'),
        ];
    }
}
