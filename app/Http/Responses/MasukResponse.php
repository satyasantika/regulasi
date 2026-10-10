<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Setelah masuk di panel akun pengguna diarahkan ke beranda portal; panel lain ke dasbornya.
 */
class MasukResponse implements LoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        if (Filament::getCurrentPanel()?->getId() === 'akun') {
            return redirect()->intended(Route::has('beranda') ? route('beranda') : url('/'));
        }

        return redirect()->intended(Filament::getUrl());
    }
}
