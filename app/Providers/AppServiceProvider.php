<?php

namespace App\Providers;

use App\Enums\Peran;
use App\Models\TokenAkses;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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

        // Super-admin lolos semua Gate/Policy (03-SKEMA §4.1).
        Gate::before(fn (User $user): ?bool => $user->hasRole(Peran::SuperAdmin->value) ? true : null);

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
