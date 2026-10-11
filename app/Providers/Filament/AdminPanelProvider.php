<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Dasbor;
use App\Filament\Auth\EditProfil;
use App\Filament\Auth\Masuk;
use App\Http\Middleware\WajibGantiSandi;
use App\Http\Middleware\WajibMfa;
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
 * Panel staf (super-admin, admin-regulasi, kontributor) di /admin (02-ARSITEKTUR §7.1).
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Panel tidak untuk diindeks mesin pencari (F8.4).
            ->renderHook(PanelsRenderHook::HEAD_START, fn (): HtmlString => new HtmlString('<meta name="robots" content="noindex, nofollow">'))
            ->login(Masuk::class)
            ->passwordReset()
            ->profile(EditProfil::class)
            // Panduan pengguna HTML (F12.6): tautan di halaman masuk dan menu pengguna.
            ->renderHook(...Panduan::kaitHalamanMasuk())
            ->userMenuItems([Panduan::itemMenuPengguna()])
            // MFA wajib bagi super-admin & admin-regulasi (WajibMfa), opsional bagi kontributor.
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()->brandName('Regulasi FKIP Unsil')],
                isRequired: true,
            )
            ->multiFactorAuthenticationRequiredMiddlewareName(WajibMfa::class)
            ->brandName('Regulasi FKIP Unsil')
            // Lonceng notifikasi database (F10.1), diperbarui tiap 30 detik.
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->navigationGroups([
                'Dokumen',
                'Master',
                'Laporan',
                'Sistem',
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
                Dasbor::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
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
