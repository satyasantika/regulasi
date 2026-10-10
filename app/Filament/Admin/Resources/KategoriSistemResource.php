<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\KategoriSistemResource\Pages\CreateKategoriSistem;
use App\Filament\Admin\Resources\KategoriSistemResource\Pages\EditKategoriSistem;
use App\Filament\Admin\Resources\KategoriSistemResource\Pages\ListKategoriSistem;
use App\Models\KategoriSistem;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
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
 * Master kategori sistem FKIP. Kode tidak dapat diubah setelah dibuat karena dipakai API `filter[kategori]`.
 */
class KategoriSistemResource extends Resource
{
    /**
     * Warna badge Filament yang dapat dipilih.
     *
     * @var array<string, string>
     */
    public const array WARNA = [
        'primary' => 'Utama',
        'gray' => 'Abu-abu',
        'info' => 'Biru',
        'success' => 'Hijau',
        'warning' => 'Kuning',
        'danger' => 'Merah',
    ];

    protected static ?string $model = KategoriSistem::class;

    protected static ?string $slug = 'kategori-sistem';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $navigationLabel = 'Kategori Sistem';

    protected static ?string $modelLabel = 'kategori sistem';

    protected static ?string $pluralModelLabel = 'Kategori Sistem';

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('kode')
                    ->label('Kode')
                    ->required()
                    ->maxLength(30)
                    ->regex('/^[a-z0-9-]+$/')
                    ->validationMessages(['regex' => 'Kode hanya huruf kecil, angka, atau tanda hubung.'])
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit')
                    ->helperText('Mis. aset. Tidak dapat diubah setelah dibuat (dipakai API).'),
                TextInput::make('nama')
                    ->label('Nama')
                    ->required()
                    ->maxLength(100),
                TextInput::make('slug')
                    ->label('Slug')
                    ->maxLength(120)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit')
                    ->helperText('Kosongkan untuk dibuat otomatis dari nama.'),
                TextInput::make('ikon')
                    ->label('Ikon')
                    ->maxLength(60)
                    ->regex('/^heroicon-[os]-[a-z0-9-]+$/')
                    ->validationMessages(['regex' => 'Gunakan nama Heroicon, mis. heroicon-o-building-office-2.'])
                    ->helperText('Nama Heroicon, mis. heroicon-o-building-office-2.'),
                Select::make('warna')
                    ->label('Warna badge')
                    ->options(self::WARNA),
                TextInput::make('urutan')
                    ->label('Urutan')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(65535)
                    ->default(999)
                    ->required(),
                Toggle::make('aktif')
                    ->label('Aktif')
                    ->default(true),
                Textarea::make('deskripsi')
                    ->label('Deskripsi')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('urutan')
            ->columns([
                TextColumn::make('urutan')->label('Urutan')->sortable(),
                IconColumn::make('ikon')
                    ->label('Ikon')
                    ->icon(fn (?string $state): ?string => $state),
                TextColumn::make('kode')->label('Kode')->searchable()->sortable(),
                TextColumn::make('nama')
                    ->label('Nama')
                    ->badge()
                    ->color(fn (KategoriSistem $record): string => $record->warna ?? 'gray')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('aktif')->label('Aktif'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKategoriSistem::route('/'),
            'create' => CreateKategoriSistem::route('/create'),
            'edit' => EditKategoriSistem::route('/{record}/edit'),
        ];
    }
}
