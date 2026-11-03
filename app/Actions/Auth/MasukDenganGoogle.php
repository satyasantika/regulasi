<?php

namespace App\Actions\Auth;

use App\Exceptions\MasukGoogleDitolak;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as GoogleUser;

/**
 * Mencocokkan atau membuat akun dari identitas Google (google_id lalu surel huruf kecil).
 *
 * @throws MasukGoogleDitolak
 */
class MasukDenganGoogle
{
    public function jalankan(GoogleUser $google): User
    {
        $email = strtolower(trim((string) $google->getEmail()));
        $raw = $google instanceof AbstractUser ? $google->getRaw() : [];
        $terverifikasi = filter_var($raw['email_verified'] ?? $raw['verified_email'] ?? false, FILTER_VALIDATE_BOOL);

        if ($email === '' || ! $terverifikasi) {
            throw new MasukGoogleDitolak('Surel akun Google Anda belum terverifikasi.');
        }

        $user = User::where('google_id', $google->getId())->first()
            ?? User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            $user = User::create([
                'name' => $google->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Str::random(40),
                'aktif' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
        } elseif ($user->google_id !== null && $user->google_id !== $google->getId()) {
            throw new MasukGoogleDitolak('Akun ini sudah ditautkan ke akun Google lain.');
        }

        if (! $user->aktif) {
            throw new MasukGoogleDitolak('Akun Anda dinonaktifkan. Hubungi admin.');
        }

        if ($user->app_authentication_secret !== null) {
            throw new MasukGoogleDitolak('Akun ini memakai verifikasi dua langkah; masuk dengan surel dan kata sandi.');
        }

        if ($user->google_id === null) {
            $user->forceFill(['google_id' => $google->getId()])->save();
        }

        return $user;
    }
}
