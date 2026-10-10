<?php

namespace App\Filament\Admin\Resources;

use App\Actions\Pengguna\AturPenugasanKategori;
use App\Enums\Peran;
use App\Filament\Admin\Resources\UserResource\Pages\CreateUser;
use App\Filament\Admin\Resources\UserResource\Pages\EditUser;
use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Filament\Admin\Resources\UserResource\Pages\ViewUser;
use App\Models\KategoriSistem;
use App\Models\Role;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
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
                    ->live()
                    ->required(),
                static::isianKategoriSistem()
                    ->visible(fn (Get $get): bool => in_array(Peran::Kontributor->value, (array) $get('peran'), true))
                    ->disabled(fn (): bool => ! (auth()->user()?->can('penugasan.kelola') ?? false)),
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['roles', 'kategoriSistem']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Surel')->searchable()->sortable(),
                TextColumn::make('nip')->label('NIP')->searchable()->placeholder('—')->toggleable(),
                TextColumn::make('roles.name')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Peran::tryFrom($state)?->getLabel() ?? $state),
                TextColumn::make('kategoriSistem.nama')
                    ->label('Kategori ditugaskan')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
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
                static::aksiAturPenugasan(),
            ])
            ->toolbarActions([]);
    }

    /**
     * Pilihan kategori sistem aktif (US-MAS-03). Nilai: daftar id kategori.
     */
    public static function isianKategoriSistem(): Select
    {
        return Select::make('kategori_sistem')
            ->label('Kategori sistem yang ditugaskan')
            ->multiple()
            ->options(fn (): array => KategoriSistem::query()->aktif()->terurut()->pluck('nama', 'id')->all())
            ->helperText('Kontributor hanya dapat mengisi dokumen pada kategori yang ditugaskan.');
    }

    /**
     * Aksi tabel terpisah agar admin-regulasi (penugasan.kelola) dapat menugaskan kontributor
     * tanpa memperoleh hak ubah data pengguna (UserPolicy::update tetap ketat).
     */
    public static function aksiAturPenugasan(): Action
    {
        return Action::make('aturPenugasan')
            ->label('Atur penugasan')
            ->icon(Heroicon::OutlinedSquares2x2)
            ->color('gray')
            ->authorize('aturPenugasan')
            ->visible(fn (User $record): bool => $record->hasRole(Peran::Kontributor->value))
            ->modalHeading(fn (User $record): string => "Atur penugasan: {$record->name}")
            ->fillForm(fn (User $record): array => ['kategori_sistem' => $record->kategoriSistem->modelKeys()])
            ->schema([static::isianKategoriSistem()])
            ->action(function (User $record, array $data): void {
                /** @var User $oleh */
                $oleh = auth()->user();

                /** @var list<string> $kategori */
                $kategori = array_values($data['kategori_sistem'] ?? []);

                app(AturPenugasanKategori::class)->handle($record, $kategori, $oleh);

                Notification::make()->title('Penugasan kategori disimpan')->success()->send();
            });
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
