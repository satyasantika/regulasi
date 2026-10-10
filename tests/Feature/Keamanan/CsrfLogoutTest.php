<?php

use App\Enums\Peran;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * KM-06 (05-UJI §3): POST tanpa token CSRF ke rute keluar panel ditolak 419 dan sesi tetap utuh.
 * Laravel melewati pemeriksaan CSRF saat pengujian, jadi middleware diganti subkelas yang tetap memeriksa.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->singleton(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
});

it('menolak keluar panel tanpa token csrf dengan 419 dan sesi tetap masuk', function (Peran $peran, string $panel) {
    $pengguna = penggunaDenganPeran($peran);

    $this->actingAs($pengguna)
        ->post("/{$panel}/logout")
        ->assertStatus(419);

    $this->assertAuthenticatedAs($pengguna);
})->with([
    'super-admin di panel admin' => [Peran::SuperAdmin, 'admin'],
    'admin-regulasi di panel admin' => [Peran::AdminRegulasi, 'admin'],
    'pengguna di panel akun' => [Peran::Pengguna, 'akun'],
]);

it('menolak keluar dari formulir lintas situs walau memakai cookie sesi', function () {
    $pengguna = penggunaDenganPeran(Peran::SuperAdmin);

    $this->actingAs($pengguna)
        ->withHeader('Sec-Fetch-Site', 'cross-site')
        ->post('/admin/logout')
        ->assertStatus(419);

    $this->assertAuthenticatedAs($pengguna);
});

it('mengizinkan keluar dengan token csrf yang sah', function () {
    $pengguna = penggunaDenganPeran(Peran::SuperAdmin);

    $this->actingAs($pengguna)
        ->withSession(['_token' => 'token-uji'])
        ->post('/admin/logout', ['_token' => 'token-uji'])
        ->assertRedirect();

    $this->assertGuest();
});

it('mengalihkan tamu ke halaman masuk tanpa efek karena autentikasi berjalan sebelum csrf', function () {
    $this->post('/admin/logout')->assertRedirect(route('filament.admin.auth.login'));

    $this->assertGuest();
});
