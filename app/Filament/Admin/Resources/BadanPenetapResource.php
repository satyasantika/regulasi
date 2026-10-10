<?php

namespace App\Filament\Admin\Resources;

use App\Enums\TingkatBadanPenetap;
use App\Filament\Admin\Resources\BadanPenetapResource\Pages\CreateBadanPenetap;
use App\Filament\Admin\Resources\BadanPenetapResource\Pages\EditBadanPenetap;
use App\Filament\Admin\Resources\BadanPenetapResource\Pages\ListBadanPenetap;
use App\Models\BadanPenetap;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Master badan penetap / T.E.U. badan (US-MAS-02).
 */
class BadanPenetapResource extends Resource
{
    protected static ?string $model = BadanPenetap::class;

    protected static ?string $slug = 'badan-penetap';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $navigationLabel = 'Badan Penetap';

    protected static ?string $modelLabel = 'badan penetap';

    protected static ?string $pluralModelLabel = 'Badan Penetap';

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('nama')
                    ->label('Nama')
                    ->required()
                    ->maxLength(200),
                TextInput::make('teu')
                    ->label('T.E.U. badan')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Tajuk entri utama, mis. "Indonesia. Kementerian Keuangan". Konvensi penulisan perlu verifikasi terhadap Permenkumham 8/2019.'),
                TextInput::make('singkatan')
                    ->label('Singkatan')
                    ->maxLength(50),
                TextInput::make('slug')
                    ->label('Slug')
                    ->maxLength(220)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit')
                    ->helperText('Kosongkan untuk dibuat otomatis dari nama.'),
                Select::make('tingkat')
                    ->label('Tingkat')
                    ->options(TingkatBadanPenetap::class)
                    ->required(),
                Select::make('induk_id')
                    ->label('Induk')
                    ->relationship(
                        'induk',
                        'nama',
                        fn (Builder $query, ?BadanPenetap $record): Builder => $query
                            ->when($record, fn (Builder $q) => $q->whereKeyNot($record?->getKey()))
                            ->orderBy('urutan')
                            ->orderBy('nama'),
                    )
                    ->searchable()
                    ->preload(),
                TextInput::make('kode_eksternal')
                    ->label('Kode eksternal')
                    ->maxLength(50),
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('induk'))
            ->defaultSort('urutan')
            ->columns([
                TextColumn::make('nama')->label('Nama')->searchable()->sortable()->wrap(),
                TextColumn::make('teu')->label('T.E.U.')->searchable()->wrap(),
                TextColumn::make('singkatan')->label('Singkatan')->placeholder('—')->searchable(),
                TextColumn::make('tingkat')->label('Tingkat')->badge(),
                TextColumn::make('induk.nama')->label('Induk')->placeholder('—')->toggleable(),
                TextColumn::make('urutan')->label('Urutan')->sortable()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('tingkat')->label('Tingkat')->options(TingkatBadanPenetap::class),
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
            'index' => ListBadanPenetap::route('/'),
            'create' => CreateBadanPenetap::route('/create'),
            'edit' => EditBadanPenetap::route('/{record}/edit'),
        ];
    }
}
