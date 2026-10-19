<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware('web')->get('/uji-galat/{kode}', fn (int $kode) => abort($kode, 'RAHASIA-DETAIL-INTERNAL'))->whereNumber('kode');
    Route::middleware('web')->get('/uji-galat-pengecualian', fn () => throw new RuntimeException('SQLSTATE rahasia /var/www/html'));
});

test('setiap kode eror tampil bertema dengan teks Indonesia, tombol kembali dan beranda, tanpa detail', function (int $kode) {
    $teks = trans("galat.{$kode}");

    expect($teks)->toBeArray();

    $respons = $this->get("/uji-galat/{$kode}");

    $respons->assertStatus($kode)
        ->assertSee((string) $kode)
        ->assertSee($teks['judul'])
        ->assertSee('r-galat__kotak', false)
        ->assertSee(asset('css/regulasi.css'), false)
        ->assertSee('Kembali')
        ->assertSee('Ke beranda')
        ->assertSee('href="'.url('/').'"', false)
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertDontSee('RAHASIA-DETAIL-INTERNAL')
        ->assertDontSee('href="/', false)
        ->assertDontSee('src="/', false);

    if ($kode >= 500) {
        $respons->assertSee('Waktu kejadian');
    }
})->with([401, 403, 404, 419, 429, 500, 503]);

test('eror 401 dan 419 menawarkan tautan masuk', function (int $kode) {
    $this->get("/uji-galat/{$kode}")->assertSee(route('filament.akun.auth.login'), false)->assertSee('>Masuk<', false);
})->with([401, 419]);

test('tombol coba lagi hanya untuk eror yang wajar diulang', function () {
    $this->get('/uji-galat/503')->assertSee('Coba lagi');
    $this->get('/uji-galat/429')->assertSee('Coba lagi');
    $this->get('/uji-galat/404')->assertDontSee('Coba lagi');
});

test('kode eror tak terdaftar memakai cadangan 4xx/5xx yang bertema', function (int $kode) {
    $this->get("/uji-galat/{$kode}")->assertStatus($kode)->assertSee('r-galat__kotak', false)->assertSee((string) $kode);
})->with([418, 502]);

test('pengecualian tak tertangani di produksi tampil 500 bertema tanpa pesan teknis', function () {
    config(['app.debug' => false]);

    $this->get('/uji-galat-pengecualian')
        ->assertStatus(500)
        ->assertSee('Terjadi kesalahan pada server')
        ->assertDontSee('SQLSTATE')
        ->assertDontSee('/var/www/html');
});

test('rute yang tidak ada di portal tampil 404 bertema', function () {
    $this->get('/tidak-ada-halaman-ini')->assertNotFound()->assertSee('Halaman tidak ditemukan')->assertSee('r-galat__kotak', false);
});
