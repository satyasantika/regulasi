<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Auth\MultiFactor\MultiFactorChallenge;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Super-admin dan admin-regulasi wajib mengaktifkan MFA (US-AUTH-03); selama belum, semua halaman
 * panel admin dialihkan ke halaman pengaturan MFA. Peran lain boleh memakai MFA secara opsional.
 */
class WajibMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = Filament::auth()->user();

        if ($pengguna instanceof User && $pengguna->wajibMfa() && ! MultiFactorChallenge::make()->hasEnabledProviders($pengguna)) {
            return redirect()->guest(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
        }

        return $next($request);
    }
}
