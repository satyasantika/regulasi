<?php

namespace App\Providers\Filament;

use App\Filament\Akun\Pages\Daftar;
use App\Filament\Akun\Pages\Dasbor;
use App\Filament\Auth\EditProfil;
use App\Filament\Auth\Masuk;
use App\Http\Middleware\WajibGantiSandi;
use App\Support\Panduan;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel akun pengguna kampus di /akun: masuk, daftar (@unsil.ac.id), verifikasi surel, profil.
 */
class AkunPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('akun')
            ->path('akun')
            // Panel tidak untuk diindeks mesin pencari (F8.4).
            ->renderHook(PanelsRenderHook::HEAD_START, fn (): HtmlString => new HtmlString('<meta name="robots" content="noindex, nofollow">'))
            ->login(Masuk::class)
            ->registration(Daftar::class)
            // Panduan pengguna HTML (F12.6): tautan di halaman masuk dan menu pengguna.
            ->renderHook(...Panduan::kaitHalamanMasuk())
            // Spanduk impersonasi + tombol kembali ke akun super-admin.
            ->renderHook(PanelsRenderHook::BODY_START, fn (): string => view('impersonasi.spanduk')->render())
            ->userMenuItems([Panduan::itemMenuPengguna()])
            ->passwordReset()
            ->emailVerification()
            ->profile(EditProfil::class)
            // MFA opsional bagi pengguna panel akun; mati bila MFA_AKTIF=false.
            ->multiFactorAuthentication(
                fn (): array => config('regulasi.mfa_aktif')
                    ? [AppAuthentication::make()->recoverable()->brandName('Regulasi FKIP Unsil')]
                    : [],
            )
            ->brandName('Regulasi FKIP Unsil')
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->topNavigation()
            ->maxContentWidth('5xl')
            ->discoverPages(in: app_path('Filament/Akun/Pages'), for: 'App\Filament\Akun\Pages')
            ->pages([
                Dasbor::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                WajibGantiSandi::class,
            ]);
    }
}
