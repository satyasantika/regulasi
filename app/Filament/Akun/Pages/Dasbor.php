<?php

namespace App\Filament\Akun\Pages;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Support\Facades\Route;

/**
 * Dasbor panel akun: sambutan, tautan ke portal, dan tautan ke panel admin bagi staf.
 */
class Dasbor extends Dashboard
{
    protected string $view = 'filament.akun.pages.dasbor';

    protected static ?string $title = 'Akun Saya';

    public function getUrlBeranda(): string
    {
        return Route::has('beranda') ? route('beranda') : url('/');
    }

    /**
     * URL panel admin bila pengguna berhak membukanya (keputusan di User::canAccessPanel).
     */
    public function getUrlPanelAdmin(): ?string
    {
        $panel = Filament::getPanel('admin');
        $pengguna = auth()->user();

        return $pengguna instanceof User && $pengguna->canAccessPanel($panel) ? $panel->getUrl() : null;
    }
}
