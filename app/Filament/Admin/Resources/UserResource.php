<?php

namespace App\Filament\Admin\Resources;

use App\Enums\Peran;
use App\Filament\Admin\Resources\UserResource\Pages\CreateUser;
use App\Filament\Admin\Resources\UserResource\Pages\EditUser;
use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Filament\Admin\Resources\UserResource\Pages\ViewUser;
use App\Models\Role;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Manajemen pengguna (US-AUTH-01). Otorisasi lewat UserPolicy; tidak ada hapus permanen (pakai aktif = false).
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'pengguna';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Pengguna';

    protected static ?string $modelLabel = 'pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 10;

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Nama lengkap')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Surel')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText(fn (string $operation): ?string => $operation === 'create' ? 'Tautan atur kata sandi dikirim ke alamat ini.' : null),
                TextInput::make('nip')
                    ->label('NIP/NIDN/NPM')
                    ->maxLength(30),
                TextInput::make('kode_eksternal')
                    ->label('Kode eksternal')
                    ->maxLength(50),
                Select::make('peran')
                    ->label('Peran')
                    ->multiple()
                    ->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'name')
                        ->map(fn (string $nama): string => Peran::tryFrom($nama)?->getLabel() ?? $nama)
                        ->all())
                    ->required(),
                Toggle::make('aktif')
                    ->label('Aktif')
                    ->default(true)
                    ->disabled(fn (?User $record): bool => $record !== null && $record->is(auth()->user()))
                    ->helperText('Nonaktif = tidak bisa masuk panel. Akun Anda sendiri tidak dapat dinonaktifkan.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Surel')->searchable()->sortable(),
                TextColumn::make('nip')->label('NIP')->searchable()->placeholder('—')->toggleable(),
                TextColumn::make('roles.name')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Peran::tryFrom($state)?->getLabel() ?? $state),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
                TextColumn::make('terakhir_masuk_pada')->label('Terakhir masuk')
                    ->dateTime('d F Y H:i')->placeholder('Belum pernah')->sortable(),
            ])
            ->filters([
                SelectFilter::make('peran')
                    ->label('Peran')
                    ->options(collect(Peran::cases())->mapWithKeys(fn (Peran $p): array => [$p->value => $p->getLabel()])->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('roles', fn (Builder $q) => $q->where('name', $data['value']))
                        : $query),
                TernaryFilter::make('aktif')->label('Aktif'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
