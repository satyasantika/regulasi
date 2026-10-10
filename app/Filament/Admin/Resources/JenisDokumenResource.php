<?php

namespace App\Filament\Admin\Resources;

use App\Enums\KelompokJenisDokumen;
use App\Filament\Admin\Resources\JenisDokumenResource\Pages\CreateJenisDokumen;
use App\Filament\Admin\Resources\JenisDokumenResource\Pages\EditJenisDokumen;
use App\Filament\Admin\Resources\JenisDokumenResource\Pages\ListJenisDokumen;
use App\Models\JenisDokumen;
use App\Support\CacheRegulasi;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
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
 * Master jenis dokumen (US-MAS-01). Kode tidak dapat diubah setelah dibuat karena dipakai API.
 */
class JenisDokumenResource extends Resource
{
    protected static ?string $model = JenisDokumen::class;

    protected static ?string $slug = 'jenis-dokumen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $navigationLabel = 'Jenis Dokumen';

    protected static ?string $modelLabel = 'jenis dokumen';

    protected static ?string $pluralModelLabel = 'Jenis Dokumen';

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('kode')
                    ->label('Kode')
                    ->required()
                    ->maxLength(30)
                    ->regex('/^[A-Z0-9_-]+$/')
                    ->validationMessages(['regex' => 'Kode hanya huruf kapital, angka, garis bawah, atau tanda hubung.'])
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit')
                    ->helperText('Mis. PERMEN. Tidak dapat diubah setelah dibuat (dipakai API).'),
                TextInput::make('nama')
                    ->label('Nama')
                    ->required()
                    ->maxLength(150),
                TextInput::make('singkatan')
                    ->label('Singkatan')
                    ->maxLength(50),
                TextInput::make('slug')
                    ->label('Slug')
                    ->maxLength(160)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit')
                    ->helperText('Kosongkan untuk dibuat otomatis dari nama.'),
                Select::make('kelompok')
                    ->label('Kelompok')
                    ->options(KelompokJenisDokumen::class)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, KelompokJenisDokumen|string|null $state): void {
                        $kelompok = is_string($state) ? KelompokJenisDokumen::tryFrom($state) : $state;

                        if ($kelompok !== null) {
                            $set('wajib_nomor', $kelompok->wajibNomor());
                        }
                    }),
                TextInput::make('urutan')
                    ->label('Urutan')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(65535)
                    ->default(999)
                    ->required(),
                Toggle::make('wajib_nomor')
                    ->label('Wajib nomor')
                    ->default(true)
                    ->helperText('Syarat terbit: nomor dan tanggal penetapan wajib (BR-05).'),
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
            ->reorderable('urutan')
            ->afterReordering(fn () => CacheRegulasi::lupakanMaster('jenis-dokumen'))
            ->columns([
                TextColumn::make('urutan')->label('Urutan')->sortable(),
                TextColumn::make('kode')->label('Kode')->searchable()->sortable(),
                TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                TextColumn::make('singkatan')->label('Singkatan')->placeholder('—')->toggleable(),
                TextColumn::make('kelompok')->label('Kelompok')->badge(),
                IconColumn::make('wajib_nomor')->label('Wajib nomor')->boolean(),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('kelompok')->label('Kelompok')->options(KelompokJenisDokumen::class),
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
            'index' => ListJenisDokumen::route('/'),
            'create' => CreateJenisDokumen::route('/create'),
            'edit' => EditJenisDokumen::route('/{record}/edit'),
        ];
    }
}
