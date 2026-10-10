<?php

namespace App\Providers;

use App\Contracts\PenyimpananBerkas;
use App\Enums\Peran;
use App\Http\Responses\MasukResponse;
use App\Models\Aktivitas;
use App\Models\BadanPenetap;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\Permission;
use App\Models\RiwayatStatusDokumen;
use App\Models\Role;
use App\Models\Tag;
use App\Models\TautanBerkas;
use App\Models\TokenAkses;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Services\Berkas\TautanEksternal;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LoginResponse::class, MasukResponse::class);

        // Kontrak akses berkas menurut BERKAS_MODE (02-ARSITEKTUR §4.1); saat ini hanya mode tautan.
        $this->app->bind(PenyimpananBerkas::class, fn ($app) => match (config('berkas.mode')) {
            'tautan' => $app->make(TautanEksternal::class),
            default => throw new InvalidArgumentException('BERKAS_MODE ['.config('berkas.mode').'] belum didukung.'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale('id');
        Date::use(CarbonImmutable::class);
        Sanctum::usePersonalAccessTokenModel(TokenAkses::class);

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Alias morph wajib (tautan_berkas.pemilik_type, activity_log, model_has_roles, token).
        Relation::enforceMorphMap([
            'user' => User::class,
            'role' => Role::class,
            'permission' => Permission::class,
            'token_akses' => TokenAkses::class,
            'aktivitas' => Aktivitas::class,
            'jenis_dokumen' => JenisDokumen::class,
            'badan_penetap' => BadanPenetap::class,
            'kategori_sistem' => KategoriSistem::class,
            'tag' => Tag::class,
            'dokumen' => Dokumen::class,
            'tautan_berkas' => TautanBerkas::class,
            'riwayat_status_dokumen' => RiwayatStatusDokumen::class,
        ]);

        // Super-admin lolos semua Gate/Policy (03-SKEMA §4.1).
        Gate::before(fn (User $user): ?bool => $user->hasRole(Peran::SuperAdmin->value) ? true : null);
        Gate::policy(Aktivitas::class, ActivityPolicy::class);

        // Rate limit buka tautan 30/menit per pengguna atau IP (02-ARSITEKTUR §3.4, BR-17).
        RateLimiter::for('buka', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        $this->aturProxyTepercaya();
        $this->siapkanSubpath();
    }

    private function aturProxyTepercaya(): void
    {
        $proxy = trim((string) config('regulasi.trusted_proxies'));

        if ($proxy !== '') {
            TrustProxies::at($proxy === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $proxy)))));
        }
    }

    /**
     * Aplikasi berjalan di bawah awalan path (/regulasi) di belakang reverse proxy
     * (STANDAR-TEKNIS §2.4): semua URL dibangkitkan dengan awalan itu.
     */
    private function siapkanSubpath(): void
    {
        $appUrl = (string) config('app.url');

        $path = parse_url($appUrl, PHP_URL_PATH);

        if ($path !== null && $path !== '' && $path !== '/') {
            URL::forceRootUrl($appUrl);
        }

        if (parse_url($appUrl, PHP_URL_SCHEME) === 'https') {
            URL::forceScheme('https');
        }
    }
}
