<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TagResource\Pages\CreateTag;
use App\Filament\Admin\Resources\TagResource\Pages\EditTag;
use App\Filament\Admin\Resources\TagResource\Pages\ListTag;
use App\Models\Tag;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Tag subjek. Kontributor boleh menambah (tag.buat); ubah/hapus hanya pengelola master.
 */
class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static ?string $slug = 'tag';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $navigationLabel = 'Tag';

    protected static ?string $modelLabel = 'tag';

    protected static ?string $pluralModelLabel = 'Tag';

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nama')
            ->columns([
                TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                TextColumn::make('slug')->label('Slug')->searchable(),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d F Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTag::route('/'),
            'create' => CreateTag::route('/create'),
            'edit' => EditTag::route('/{record}/edit'),
        ];
    }
}
