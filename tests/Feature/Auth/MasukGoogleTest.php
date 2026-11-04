<?php

use App\Enums\Peran;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    RateLimiter::clear('google-login');
    config([
        'services.google.aktif' => true,
        'services.google.client_id' => 'id-uji',
        'services.google.client_secret' => 'rahasia',
    ]);
});

function googleSebagai(string $email, array $tambahan = []): void
{
    Socialite::fake('google', Laravel\Socialite\Two\User::fake(array_merge([
        'id' => 'g-'.md5($email), 'name' => 'Nama Google', 'email' => $email, 'email_verified' => true,
    ], $tambahan)));
}

it('fitur nonaktif: kedua rute 404 dan halaman masuk tidak memuat tombol', function () {
    config(['services.google.aktif' => false]);

    $this->get(route('auth.google.redirect'))->assertNotFound();
    $this->get(route('auth.google.callback'))->assertNotFound();
    $this->get(route('filament.admin.auth.login'))->assertOk()->assertDontSee('Masuk dengan Google');
    $this->get(route('filament.akun.auth.login'))->assertOk()->assertDontSee('Masuk dengan Google');
});

it('halaman masuk memuat tombol dan tautannya', function (string $rute) {
    $this->get(route($rute))
        ->assertOk()
        ->assertSee('Masuk dengan Google')
        ->assertSee(route('auth.google.redirect', ['asal' => route($rute)]), false);
})->with(['filament.admin.auth.login', 'filament.akun.auth.login']);

it('redirect_uri kosong di konfigurasi memakai bawaan', function () {
    // GOOGLE_REDIRECT_URI= kosong di .env: env() mengembalikan '' dan konfigurasi harus tetap memakai bawaan.
    putenv('GOOGLE_REDIRECT_URI=');
    $_ENV['GOOGLE_REDIRECT_URI'] = $_SERVER['GOOGLE_REDIRECT_URI'] = '';

    try {
        $layanan = require config_path('services.php');
    } finally {
        putenv('GOOGLE_REDIRECT_URI');
        unset($_ENV['GOOGLE_REDIRECT_URI'], $_SERVER['GOOGLE_REDIRECT_URI']);
    }

    config(['services.google.redirect' => $layanan['google']['redirect']]);

    $lokasi = $this->get(route('auth.google.redirect'))->assertRedirect()->headers->get('Location');
    parse_str(parse_url($lokasi, PHP_URL_QUERY), $q);

    expect($q['redirect_uri'])->toBe(route('auth.google.callback'));
});

it('mengarahkan ke Google dengan redirect_uri bawaan', function () {
    $lokasi = $this->get(route('auth.google.redirect'))->assertRedirect()->headers->get('Location');

    expect($lokasi)->toStartWith('https://accounts.google.com/');
    parse_str(parse_url($lokasi, PHP_URL_QUERY), $q);
    expect($q['redirect_uri'])->toBe(route('auth.google.callback'))
        ->and(urldecode($q['scope']))->toContain('openid');
});

it('memasukkan akun yang sudah ada dan menautkan google_id', function () {
    $user = penggunaDenganPeran(Peran::Kontributor, ['email' => 'budi@unsil.ac.id']);
    googleSebagai('budi@unsil.ac.id');

    $this->get(route('auth.google.callback'))->assertRedirect(Filament::getPanel('admin')->getUrl());

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->google_id)->toBe('g-'.md5('budi@unsil.ac.id'));
});

it('mencocokkan surel tanpa peduli huruf', function () {
    $user = User::factory()->create(['email' => 'budi@unsil.ac.id']);
    googleSebagai('Budi@Unsil.ac.id');

    $this->get(route('auth.google.callback'));

    $this->assertAuthenticatedAs($user);
    expect(User::count())->toBe(1);
});

it('membuat akun baru untuk surel yang belum terdaftar', function () {
    googleSebagai('baru@gmail.com');

    $this->get(route('auth.google.callback'))->assertRedirect(Filament::getPanel('akun')->getUrl());

    $this->assertAuthenticated();
    $u = User::firstWhere('email', 'baru@gmail.com');
    expect($u)->not->toBeNull()
        ->and($u->name)->toBe('Nama Google')
        ->and($u->aktif)->toBeTrue()
        ->and($u->email_verified_at)->not->toBeNull()
        ->and($u->google_id)->toBe('g-'.md5('baru@gmail.com'));
});

it('menolak surel yang belum diverifikasi Google', function () {
    googleSebagai('x@gmail.com', ['email_verified' => false]);

    $this->get(route('auth.google.callback'))
        ->assertSessionHas('galat_google', 'Surel akun Google Anda belum terverifikasi.');

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('menolak akun yang sudah tertaut ke Google lain', function () {
    User::factory()->create(['email' => 'budi@unsil.ac.id'])->forceFill(['google_id' => 'google-lain'])->save();
    googleSebagai('budi@unsil.ac.id');

    $this->get(route('auth.google.callback'))
        ->assertSessionHas('galat_google', 'Akun ini sudah ditautkan ke akun Google lain.');

    $this->assertGuest();
});

it('membatalkan di Google kembali ke halaman masuk', function () {
    $this->get(route('auth.google.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('filament.admin.auth.login'))
        ->assertSessionHas('galat_google', 'Masuk dengan Google dibatalkan.');

    $this->assertGuest();
});

it('state OAuth tidak cocok kembali ke halaman masuk', function () {
    Socialite::fake('google', fn () => throw new InvalidStateException);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('filament.admin.auth.login'))
        ->assertSessionHas('galat_google', 'Sesi masuk Google kedaluwarsa. Silakan coba lagi.');

    $this->assertGuest();
});

it('kembali ke asal bila asal berada di dalam aplikasi, mengabaikan asal di luar aplikasi', function () {
    $this->withSession(['google_asal' => route('filament.akun.auth.login')])
        ->get(route('auth.google.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('filament.akun.auth.login'));

    $this->withSession(['google_asal' => url('/halaman-x')])
        ->get(route('auth.google.callback', ['error' => 'access_denied']))
        ->assertRedirect(url('/halaman-x'));

    $this->withSession(['google_asal' => 'https://evil.example/'])
        ->get(route('auth.google.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('filament.admin.auth.login'));

    $this->withSession(['google_asal' => rtrim(url('/'), '/').'.evil.example/'])
        ->get(route('auth.google.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('filament.admin.auth.login'));
});

it('asal berbentuk larik tidak menyebabkan galat', function () {
    $lokasi = $this->get(route('auth.google.redirect', ['asal' => ['x']]))->assertRedirect()->headers->get('Location');
    expect($lokasi)->toStartWith('https://accounts.google.com/');

    $this->withSession(['google_asal' => ['x']])
        ->get(route('auth.google.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('filament.admin.auth.login'));
});

it('pengguna yang sudah masuk langsung diarahkan', function () {
    $staf = penggunaDenganPeran(Peran::Kontributor);
    $this->actingAs($staf)->get(route('auth.google.redirect'))
        ->assertRedirect(Filament::getPanel('admin')->getUrl());

    auth()->logout();

    $biasa = User::factory()->create();
    $this->actingAs($biasa)->get(route('auth.google.redirect'))
        ->assertRedirect(Filament::getPanel('akun')->getUrl());
});

it('membatasi 60 permintaan per menit per IP', function () {
    googleSebagai('x@gmail.com', ['email_verified' => false]);

    foreach (range(1, 60) as $_) {
        $this->get(route('auth.google.callback'))->assertRedirect();
    }

    $this->get(route('auth.google.callback'))->assertStatus(429);
});

it('menolak akun nonaktif', function () {
    User::factory()->nonaktif()->create(['email' => 'budi@unsil.ac.id']);
    googleSebagai('budi@unsil.ac.id');

    $this->get(route('auth.google.callback'))
        ->assertSessionHas('galat_google', 'Akun Anda dinonaktifkan. Hubungi admin.');

    $this->assertGuest();
});

it('menolak akun ber-MFA', function () {
    $user = User::factory()->create(['email' => 'budi@unsil.ac.id']);
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    googleSebagai('budi@unsil.ac.id');

    $this->get(route('auth.google.callback'))
        ->assertSessionHas('galat_google', 'Akun ini memakai verifikasi dua langkah; masuk dengan surel dan kata sandi.');

    $this->assertGuest();
});
