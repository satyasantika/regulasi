<?php

namespace App\Providers;

use App\Contracts\PenyimpananBerkas;
use App\Enums\Peran;
use App\Http\Responses\GalatJsonApi;
use App\Http\Responses\MasukResponse;
use App\Models\Aktivitas;
use App\Models\BadanPenetap;
use App\Models\Dokumen;
use App\Models\DokumenRelasi;
use App\Models\Ekspor;
use App\Models\EkstraksiTeks;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\KlienApi;
use App\Models\LaporanEvaluasi;
use App\Models\Permission;
use App\Models\RiwayatStatusDokumen;
use App\Models\Role;
use App\Models\Tag;
use App\Models\TautanBerkas;
use App\Models\TokenAkses;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Services\Berkas\TautanEksternal;
use App\Services\Pencarian\PencarianDatabase;
use App\Services\Pencarian\PencarianDokumen;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Filament\Actions\Exports\Models\Export;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\ParallelTesting;
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

        // Catatan ekspor Filament memakai kunci UUIDv7 (STANDAR-TEKNIS §4a).
        $this->app->bind(Export::class, Ekspor::class);

        // Kontrak akses berkas menurut BERKAS_MODE (02-ARSITEKTUR §4.1); saat ini hanya mode tautan.
        $this->app->bind(PenyimpananBerkas::class, fn ($app) => match (config('berkas.mode')) {
            'tautan' => $app->make(TautanEksternal::class),
            default => throw new InvalidArgumentException('BERKAS_MODE ['.config('berkas.mode').'] belum didukung.'),
        });

        // Mesin pencarian Profil Ringan: MariaDB FULLTEXT + kamus istilah (02-ARSITEKTUR §5.1).
        $this->app->bind(PencarianDokumen::class, PencarianDatabase::class);
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
            'ekstraksi_teks' => EkstraksiTeks::class,
            'riwayat_status_dokumen' => RiwayatStatusDokumen::class,
            'dokumen_relasi' => DokumenRelasi::class,
            'klien_api' => KlienApi::class,
            'laporan_evaluasi' => LaporanEvaluasi::class,
        ]);

        // Super-admin lolos semua Gate/Policy (03-SKEMA §4.1).
        // Super-admin lolos semua gate, kecuali impersonasi yang batasannya (bukan diri sendiri/super-admin lain/nonaktif)
        // tetap diperiksa UserPolicy::impersonate.
        Gate::before(fn (User $user, string $kemampuan): ?bool => $kemampuan !== 'impersonate' && $user->hasRole(Peran::SuperAdmin->value) ? true : null);
        Gate::policy(Aktivitas::class, ActivityPolicy::class);

        // Rate limit buka tautan 30/menit per pengguna atau IP (02-ARSITEKTUR §3.4, BR-17).
        RateLimiter::for('buka', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        // Rate limit API v1: 60/menit per token (BR-18); token tak dikenal per IP. 429 berformat JSON:API dengan
        // header X-RateLimit-Limit/Remaining dan Retry-After.
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)
            ->by('token:'.(KlienApi::dariPermintaan($request)?->currentAccessToken()?->getKey() ?? $request->ip()))
            ->response(fn (Request $request, array $header) => GalatJsonApi::buat(429, $header)));

        // Rate limit pencarian portal 60/menit per IP (02-ARSITEKTUR §3.4), diperiksa di Livewire DaftarDokumen.
        RateLimiter::for('pencarian', fn (Request $request): Limit => Limit::perMinute(60)->by((string) $request->ip()));

        $this->aturProxyTepercaya();
        $this->siapkanSubpath();
        $this->siapkanBasisDataPararel();
    }

    private function aturProxyTepercaya(): void
    {
        $proxy = trim((string) config('regulasi.trusted_proxies'));

        if ($proxy !== '') {
            TrustProxies::at($proxy === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $proxy)))));
        }
    }

    /**
     * Setiap proses uji paralel (`php artisan test --parallel`) mendapat basis data tersendiri
     * `{DB_DATABASE}_test_{token}` yang dibuat otomatis oleh Laravel, tetapi belum bermigrasi —
     * tanpa gerbang ini tabel tidak ada dan uji gagal di proses selain yang pertama.
     */
    private function siapkanBasisDataPararel(): void
    {
        if (! $this->app->runningUnitTests()) {
            return;
        }

        ParallelTesting::setUpTestDatabase(function (): void {
            Artisan::call('migrate', ['--force' => true]);
        });
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
