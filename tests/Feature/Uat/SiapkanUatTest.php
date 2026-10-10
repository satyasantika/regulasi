<?php

use App\Console\Commands\SiapkanUatCommand;
use App\Models\Dokumen;
use App\Models\KlienApi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

/**
 * regulasi:siapkan-uat (F12.5, 05-UJI §1): seed lengkap + akun uji per peran + klien API uji; tidak di produksi.
 */
uses(RefreshDatabase::class);

test('menolak berjalan di produksi tanpa mengubah data', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $this->artisan('regulasi:siapkan-uat', ['--sandi' => 'sandi-uat-123'])
        ->expectsOutputToContain('tidak boleh dijalankan di produksi')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

test('seed lengkap, akun uji per peran dengan penugasan kategori, dan token API uji', function () {
    $this->artisan('regulasi:siapkan-uat', ['--sandi' => 'sandi-uat-123'])->assertSuccessful();

    foreach (SiapkanUatCommand::AKUN as $surel => [, $peran, $kategori]) {
        $pengguna = User::query()->where('email', $surel)->firstOrFail();

        expect($pengguna->hasRole($peran->value))->toBeTrue($surel)
            ->and($pengguna->aktif)->toBeTrue()
            ->and($pengguna->email_verified_at)->not->toBeNull()
            ->and(Hash::check('sandi-uat-123', $pengguna->password))->toBeTrue()
            ->and($pengguna->kategoriSistem()->pluck('kode')->all())->toBe($kategori)
            ->and($pengguna->getAppAuthenticationSecret())->toBeNull();
    }

    expect(Dokumen::query()->count())->toBeGreaterThan(0)
        ->and(KlienApi::query()->whereIn('nama', array_keys(SiapkanUatCommand::KLIEN))->count())->toBe(2)
        ->and(KlienApi::query()->where('nama', 'UAT Internal')->firstOrFail()->tokens()->sole()->abilities)
        ->toBe(['dokumen:baca', 'dokumen:baca-internal'])
        ->and(KlienApi::query()->where('nama', 'UAT Sistem Aset')->firstOrFail()->tokens()->sole()->abilities)
        ->toBe(['dokumen:baca']);
});

test('admin-regulasi uji diminta mengaktifkan MFA saat masuk pertama (A-01)', function () {
    $this->artisan('regulasi:siapkan-uat', ['--sandi' => 'sandi-uat-123', '--tanpa-seed' => true]);
    $admin = User::query()->where('email', 'uat.admin@unsil.ac.id')->firstOrFail();

    $lokasi = (string) $this->actingAs($admin)->get('/admin')->assertRedirect()->headers->get('Location');

    expect($lokasi)->toContain('multi-factor');
});

test('idempoten: dijalankan ulang tanpa duplikat, token UAT lama dicabut, sandi acak ditampilkan', function () {
    $this->artisan('regulasi:siapkan-uat', ['--sandi' => 'sandi-uat-123', '--tanpa-seed' => true])->assertSuccessful();
    $tokenLama = KlienApi::query()->where('nama', 'UAT Sistem Aset')->firstOrFail()->tokens()->sole()->id;

    config(['regulasi.uat.password' => null]);
    Artisan::call('regulasi:siapkan-uat', ['--tanpa-seed' => true]);
    $keluaran = Artisan::output();

    expect(User::query()->where('email', 'like', 'uat.%')->count())->toBe(count(SiapkanUatCommand::AKUN))
        ->and(KlienApi::query()->where('nama', 'UAT Sistem Aset')->count())->toBe(1)
        ->and(KlienApi::query()->where('nama', 'UAT Sistem Aset')->firstOrFail()->tokens()->sole()->id)->not->toBe($tokenLama)
        ->and($keluaran)->toContain('acak, tampil sekali')
        ->and(Hash::check('sandi-uat-123', User::query()->where('email', 'uat.admin@unsil.ac.id')->value('password')))->toBeFalse();
});

test('kata sandi terlalu pendek ditolak', function () {
    $this->artisan('regulasi:siapkan-uat', ['--sandi' => 'pendek', '--tanpa-seed' => true])->assertFailed();
});
