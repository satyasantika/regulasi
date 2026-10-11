<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun bertanda `wajib_ganti_sandi` hanya boleh membuka halaman profil (dan keluar) sampai kata sandinya diganti.
 * Dipasang di kedua panel setelah Authenticate; halaman pengaturan MFA wajib tetap terbuka.
 */
class WajibGantiSandi
{
    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = Filament::auth()->user();

        if (! $pengguna instanceof User || ! $pengguna->wajib_ganti_sandi) {
            return $next($request);
        }

        $panel = Filament::getCurrentOrDefaultPanel();

        if ($request->routeIs(
            $panel->generateRouteName('auth.profile'),
            $panel->generateRouteName('auth.logout'),
            // Pengaturan MFA wajib tetap terbuka agar tidak terjadi pengalihan berputar.
            $panel->generateRouteName('auth.multi-factor-authentication.set-up-required'),
            'livewire.*',
        )) {
            return $next($request);
        }

        Notification::make()
            ->title('Ganti kata sandi Anda')
            ->body('Akun Anda diwajibkan mengganti kata sandi sebelum melanjutkan.')
            ->warning()
            ->persistent()
            ->send();

        return redirect()->to((string) $panel->getProfileUrl());
    }
}
