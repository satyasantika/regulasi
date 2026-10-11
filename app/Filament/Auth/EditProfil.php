<?php

namespace App\Filament\Auth;

use App\Actions\Pengguna\PerbaruiProfil;
use App\Models\User;
use Closure;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;

/**
 * Profil sendiri untuk semua peran (panel admin & akun): nama, NIP, dan ganti kata sandi. Surel adalah identitas
 * masuk dan hanya diubah super-admin. Ganti sandi mengeluarkan sesi di perangkat lain (PerbaruiProfil).
 */
class EditProfil extends EditProfile
{
    public function mount(): void
    {
        parent::mount();

        $pengguna = $this->getUser();

        if ($pengguna instanceof User && $pengguna->wajib_ganti_sandi) {
            Notification::make()
                ->title('Ganti kata sandi Anda')
                ->body('Akun Anda diwajibkan mengganti kata sandi sebelum melanjutkan.')
                ->warning()
                ->persistent()
                ->send();
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profil')
                ->schema([
                    $this->getNameFormComponent(),
                    TextInput::make('email')
                        ->label('Surel')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Surel adalah identitas masuk; perubahan lewat super-admin.'),
                    TextInput::make('nip')->label('NIP/NIDN/NPM')->maxLength(30),
                ]),
            Section::make('Ganti kata sandi')
                ->description('Setelah kata sandi diganti, perangkat lain yang sedang masuk dengan akun ini otomatis keluar.')
                ->schema([
                    $this->getPasswordFormComponent(),
                    $this->getPasswordConfirmationFormComponent(),
                    $this->getCurrentPasswordFormComponent(),
                ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        /** @var User $record */
        /** @var array{name?: string, nip?: string|null, password?: string|null} $data */
        return app(PerbaruiProfil::class)->handle($record, $data);
    }

    /**
     * Surel tidak dapat diubah di sini, sehingga sandi saat ini hanya diminta bila sandi baru diisi.
     */
    protected function getCurrentPasswordFormComponent(): Component
    {
        /** @var TextInput $kolom */
        $kolom = parent::getCurrentPasswordFormComponent();

        return $kolom->visible(fn (Get $get): bool => filled($get('password')));
    }

    protected function getPasswordFormComponent(): Component
    {
        /** @var TextInput $kolom */
        $kolom = parent::getPasswordFormComponent();

        return $kolom
            ->required(fn (): bool => (bool) $this->getUser()->getAttributeValue('wajib_ganti_sandi'))
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && Hash::check($value, (string) $this->getUser()->getAuthPassword())) {
                    $fail('Kata sandi baru harus berbeda dari yang sekarang.');
                }
            });
    }
}
