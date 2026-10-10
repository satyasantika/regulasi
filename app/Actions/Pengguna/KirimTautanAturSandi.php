<?php

namespace App\Actions\Pengguna;

use App\Models\User;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Password;
use SensitiveParameter;

/**
 * Mengirim tautan atur kata sandi ke pengguna; tautan mengarah ke panel yang boleh dibukanya.
 */
class KirimTautanAturSandi
{
    public function handle(User $pengguna): string
    {
        $panel = Filament::getPanel('admin');
        $panel = $pengguna->canAccessPanel($panel) ? $panel : Filament::getPanel('akun');

        return Password::broker()->sendResetLink(
            ['email' => $pengguna->email],
            function (User $user, #[SensitiveParameter] string $token) use ($panel): void {
                $notifikasi = app(ResetPassword::class, ['token' => $token]);
                $notifikasi->url = $panel->getResetPasswordUrl($token, $user);

                $user->notify($notifikasi);
            },
        );
    }
}
