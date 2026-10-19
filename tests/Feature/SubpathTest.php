<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    $url = 'https://supportfkip.unsil.ac.id/regulasi';

    config([
        'app.url' => $url,
        'app.asset_url' => $url,
        'session.path' => '/regulasi',
        'session.cookie' => 'regulasi_session',
    ]);

    URL::forceRootUrl($url);
    URL::forceScheme('https');
    URL::useAssetOrigin($url);
});

afterEach(function () {
    URL::forceRootUrl(null);
    URL::useAssetOrigin(null);
});

test('url dan asset berawalan subpath produksi', function () {
    expect(url('/'))->toStartWith('https://supportfkip.unsil.ac.id/regulasi')
        ->and(url('/panel'))->toBe('https://supportfkip.unsil.ac.id/regulasi/panel')
        ->and(asset('build/app.js'))->toBe('https://supportfkip.unsil.ac.id/regulasi/build/app.js');
});

test('halaman awal memasang cookie sesi regulasi_session ber-path /regulasi', function () {
    $respons = $this->get('/')->assertOk();

    $cookie = collect($respons->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === 'regulasi_session');

    expect($cookie)->not->toBeNull()
        ->and($cookie->getPath())->toBe('/regulasi');
});

test('redirect tamu ke login tetap di bawah subpath', function () {
    Route::middleware('web')->get('/masuk', fn () => 'masuk')->name('login');
    Route::middleware(['web', 'auth'])->get('/_uji/terlindung', fn () => 'rahasia');
    app('router')->getRoutes()->refreshNameLookups();

    $this->get('/_uji/terlindung')
        ->assertRedirect('https://supportfkip.unsil.ac.id/regulasi/masuk');
});

test('URL update Livewire panel tidak menggandakan awalan subpath (dengan maupun tanpa X-Forwarded-Prefix)', function (array $header) {
    $html = $this->withHeaders($header)->get('/admin/login')->assertOk()->getContent();
    preg_match('/data-update-uri="([^"]+)"/', $html, $cocok);

    expect($cocok[1])->toMatch('#^https://supportfkip\.unsil\.ac\.id/regulasi/livewire-[a-z0-9]+/update$#');
})->with([
    'tanpa header' => [[]],
    'dengan X-Forwarded-Prefix' => [['X-Forwarded-Prefix' => '/regulasi', 'X-Forwarded-Proto' => 'https']],
]);

test('aset filament dan skrip livewire berawalan subpath', function () {
    $html = $this->get('/akun/login')->assertOk()->getContent();

    expect($html)->toContain('https://supportfkip.unsil.ac.id/regulasi/css/filament/filament/app.css')
        ->toMatch('#src="https://supportfkip\.unsil\.ac\.id/regulasi/livewire-[a-z0-9]+/livewire\.js#');
});
