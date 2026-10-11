<?php

use App\Enums\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('landing memuat hero, pencarian, statistik, keunggulan, dan panduan per peran tanpa super-admin', function () {
    $respons = $this->get(route('beranda'))->assertOk();

    $respons->assertSee('Temukan regulasi yang dipakai sistem-sistem FKIP')
        ->assertSee('name="q"', false)
        ->assertSee('Dokumen terbit')
        ->assertSee('Mengapa memakai repositori ini')
        ->assertSee('Panduan pengguna per peran')
        ->assertSee(route('filament.akun.auth.login'), false)
        ->assertDontSee('panduan/super-admin.html', false)
        ->assertDontSee('Super Admin');

    foreach (['pengguna', 'kontributor', 'admin-regulasi'] as $peran) {
        $respons->assertSee(asset("panduan/{$peran}.html"), false);
    }
});

test('panduan super-admin tetap tersedia di public/panduan', function () {
    expect(is_file(public_path('panduan/super-admin.html')))->toBeTrue();
});

test('pengguna yang sudah masuk tidak melihat ajakan masuk di hero', function () {
    $this->actingAs(penggunaDenganPeran(Peran::Pengguna))
        ->get(route('beranda'))
        ->assertOk()
        ->assertDontSee('Masuk dengan akun Unsil');
});
