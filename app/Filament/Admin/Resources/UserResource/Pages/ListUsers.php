<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Actions\Pengguna\ImporPenggunaTempel;
use App\Enums\Peran;
use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->aksiImporTempel(),
            CreateAction::make(),
        ];
    }

    /**
     * Impor massal pengguna dari salin-tempel Excel (hanya super-admin), dengan pratinjau langsung.
     */
    protected function aksiImporTempel(): Action
    {
        $domain = (string) config('regulasi.domain_registrasi');

        return Action::make('imporTempel')
            ->label('Impor massal (tempel)')
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->hasRole(Peran::SuperAdmin->value) ?? false)
            ->modalHeading('Impor pengguna dari salin-tempel Excel')
            ->modalDescription("Salin baris dari Excel/Google Sheets lalu tempel. Kolom berurutan: nama, surel (@{$domain}), peran (".implode(', ', Peran::nilai(Peran::cases())).'), dan kategori (kode kategori sistem, khusus kontributor; beberapa dipisah "|"). Surel yang sudah terdaftar dilewati. Satu baris salah membatalkan seluruh impor.')
            ->modalSubmitActionLabel('Impor')
            ->modalWidth('3xl')
            ->schema([
                Textarea::make('teks')
                    ->label('Data pengguna')
                    ->rows(8)
                    ->required()
                    ->live(debounce: 600)
                    ->placeholder("Nama\tSurel\tPeran\tKategori\nDewi Lestari\tdewi@{$domain}\tkontributor\takreditasi|sdm\nBudi Santoso\tbudi@{$domain}\tpengguna"),
                TextEntry::make('pratinjau')
                    ->label('Pratinjau')
                    ->state(fn (Get $get): array => self::ringkasPratinjau((string) $get('teks')))
                    ->listWithLineBreaks()
                    ->bulleted(),
                TextInput::make('kata_sandi')
                    ->label('Kata sandi awal (opsional)')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->helperText('Kosongkan agar setiap akun baru menerima tautan atur kata sandi lewat surel.'),
                Toggle::make('wajib_ganti_sandi')
                    ->label('Wajib ganti kata sandi saat masuk pertama')
                    ->default(true)
                    ->visible(fn (Get $get): bool => filled($get('kata_sandi'))),
            ])
            ->action(function (array $data): void {
                /** @var User $oleh */
                $oleh = auth()->user();

                $hasil = app(ImporPenggunaTempel::class)->handle(
                    (string) $data['teks'],
                    $oleh,
                    filled($data['kata_sandi'] ?? null) ? (string) $data['kata_sandi'] : null,
                    (bool) ($data['wajib_ganti_sandi'] ?? true),
                );

                if ($hasil['galat'] !== []) {
                    Notification::make()
                        ->title('Impor dibatalkan: tidak ada pengguna yang dibuat')
                        ->body(implode("\n", array_slice($hasil['galat'], 0, 15)).(count($hasil['galat']) > 15 ? "\n…dan ".(count($hasil['galat']) - 15).' galat lain.' : ''))
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title("{$hasil['dibuat']} pengguna dibuat, ".count($hasil['dilewati']).' dilewati (sudah terdaftar)')
                    ->success()
                    ->send();
            });
    }

    /**
     * @return list<string>
     */
    public static function ringkasPratinjau(string $teks): array
    {
        if (trim($teks) === '') {
            return ['Tempel data untuk melihat pratinjau.'];
        }

        $hasil = app(ImporPenggunaTempel::class)->periksa($teks);

        if ($hasil['galat'] !== []) {
            return ['Ada '.count($hasil['galat']).' galat; perbaiki sebelum impor:', ...array_slice($hasil['galat'], 0, 10)];
        }

        $baris = array_map(
            fn (array $akun): string => "{$akun['name']} <{$akun['email']}> — {$akun['peran']->getLabel()}".($akun['kategori'] !== [] ? ' ('.implode(', ', $akun['kategori']).')' : ''),
            array_slice($hasil['baru'], 0, 20),
        );

        return [
            count($hasil['baru']).' akun baru akan dibuat, '.count($hasil['dilewati']).' dilewati karena sudah terdaftar.',
            ...$baris,
            ...(count($hasil['baru']) > 20 ? ['…dan '.(count($hasil['baru']) - 20).' akun lain.'] : []),
        ];
    }
}
