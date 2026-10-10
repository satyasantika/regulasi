<?php

namespace App\Filament\Admin\Resources\KlienApiResource\RelationManagers;

use App\Actions\Api\BuatTokenKlienApi;
use App\Actions\Api\CabutTokenKlienApi;
use App\Models\KlienApi;
use App\Models\TokenAkses;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Token Sanctum milik klien API (US-API-02): buat token (token polos ditampilkan SEKALI) dan cabut per token.
 * Aksi hanya untuk pemegang klien-api.kelola (KlienApiPolicy::kelolaToken); admin-regulasi melihat saja.
 */
class TokenRelationManager extends RelationManager
{
    protected static string $relationship = 'tokens';

    protected static ?string $title = 'Token API';

    protected static ?string $modelLabel = 'token';

    protected static ?string $pluralModelLabel = 'Token API';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    /**
     * Aksi token tetap tersedia di halaman lihat; otorisasi per aksi lewat Policy.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nama token'),
                TextColumn::make('abilities')->label('Ability')->badge(),
                TextColumn::make('last_used_at')
                    ->label('Terakhir dipakai')
                    ->dateTime('d F Y H:i', 'Asia/Jakarta')
                    ->placeholder('Belum pernah'),
                TextColumn::make('expires_at')
                    ->label('Kedaluwarsa')
                    ->dateTime('d F Y', 'Asia/Jakarta')
                    ->color(fn (TokenAkses $record): ?string => $record->expires_at?->isPast() ? 'danger' : null)
                    ->placeholder('Tidak ada'),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d F Y H:i', 'Asia/Jakarta'),
            ])
            ->headerActions([
                Action::make('buatToken')
                    ->label('Buat token')
                    ->icon(Heroicon::OutlinedPlus)
                    ->authorize(fn (): bool => $this->pengguna()?->can('kelolaToken', $this->klien()) ?? false)
                    ->modalHeading('Buat token API')
                    ->modalWidth(Width::Large)
                    ->schema([
                        TextInput::make('nama')
                            ->label('Nama token')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('Produksi'),
                        Checkbox::make('baca_internal')
                            ->label('Boleh membaca dokumen internal')
                            ->helperText('Menambah ability dokumen:baca-internal. Berikan hanya bila sistem klien membatasi aksesnya ke sivitas.'),
                        Select::make('masa_berlaku')
                            ->label('Masa berlaku')
                            ->options(array_combine(
                                KlienApi::MASA_BERLAKU_HARI,
                                array_map(fn (int $hari): string => "{$hari} hari", KlienApi::MASA_BERLAKU_HARI),
                            ))
                            ->default(365)
                            ->required(),
                    ])
                    ->action(function (array $data, HasActions $livewire): void {
                        $token = app(BuatTokenKlienApi::class)->handle(
                            $this->klien(),
                            (string) $data['nama'],
                            (bool) ($data['baca_internal'] ?? false),
                            (int) $data['masa_berlaku'],
                            $this->penggunaWajib(),
                        );

                        $livewire->mountAction('tampilkanToken', arguments: ['token' => $token->plainTextToken]);
                    })
                    ->registerModalActions([
                        Action::make('tampilkanToken')
                            ->modalHeading('Token API baru')
                            ->modalDescription('Salin token ini sekarang dan simpan di konfigurasi sistem klien. Token TIDAK akan ditampilkan lagi setelah jendela ini ditutup.')
                            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
                            ->modalIconColor('warning')
                            ->modalWidth(Width::Large)
                            ->schema(fn (array $arguments): array => [
                                TextInput::make('token_polos')
                                    ->label('Token')
                                    ->default($arguments['token'] ?? '')
                                    ->readOnly()
                                    ->copyable(copyMessage: 'Token disalin')
                                    ->extraInputAttributes(['class' => 'font-mono']),
                                Text::make('Kirim lewat header: Authorization: Bearer <token>')
                                    ->fontFamily(FontFamily::Mono)
                                    ->size('xs'),
                            ])
                            ->closeModalByClickingAway(false)
                            ->closeModalByEscaping(false)
                            ->modalCloseButton(false)
                            ->modalSubmitActionLabel('Saya sudah menyalin token')
                            ->modalCancelAction(false)
                            ->cancelParentActions(),
                    ]),
            ])
            ->recordActions([
                Action::make('cabut')
                    ->label('Cabut token')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Sistem klien yang memakai token ini langsung ditolak (401).')
                    ->authorize(fn (): bool => $this->pengguna()?->can('kelolaToken', $this->klien()) ?? false)
                    ->action(function (TokenAkses $record): void {
                        app(CabutTokenKlienApi::class)->handle($this->klien(), $record, $this->penggunaWajib());

                        Notification::make()->title('Token dicabut')->success()->send();
                    }),
            ]);
    }

    private function klien(): KlienApi
    {
        /** @var KlienApi $klien */
        $klien = $this->getOwnerRecord();

        return $klien;
    }

    private function pengguna(): ?User
    {
        $pengguna = auth()->user();

        return $pengguna instanceof User ? $pengguna : null;
    }

    private function penggunaWajib(): User
    {
        return $this->pengguna() ?? abort(403);
    }
}
