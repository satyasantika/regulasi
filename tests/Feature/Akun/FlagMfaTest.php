<?php

use App\Enums\Peran;
use App\Filament\Auth\Masuk;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('MFA aktif secara bawaan di kode dan contoh env', function () {
    expect(config('regulasi.mfa_aktif'))->toBeTrue()
        ->and((string) file_get_contents(config_path('regulasi.php')))->toContain("env('MFA_AKTIF', true)")
        ->and((string) file_get_contents(base_path('.env.example')))->toMatch('/^MFA_AKTIF=true$/m');
});

test('MFA aktif: super-admin & admin-regulasi tanpa MFA diarahkan ke pengaturan, profil menampilkan MFA', function (Peran $peran) {
    $pengguna = penggunaDenganPeran($peran, mfa: false);

    expect($pengguna->wajibMfa())->toBeTrue();

    $respons = $this->actingAs($pengguna)->get('/admin');
    $respons->assertRedirect();
    expect($respons->headers->get('Location'))->toContain('multi-factor-authentication/set-up');

    expect(Filament::getPanel('admin')->hasMultiFactorAuthentication())->toBeTrue()
        ->and(Filament::getPanel('akun')->hasMultiFactorAuthentication())->toBeTrue();

    $this->actingAs(penggunaDenganPeran(Peran::Kontributor))->get('/admin/profile')->assertOk()->assertSee('Aplikasi authenticator');
})->with([Peran::SuperAdmin, Peran::AdminRegulasi]);

test('MFA aktif: masuk dengan MFA terpasang meminta kode', function () {
    penggunaDenganPeran(Peran::AdminRegulasi, ['email' => 'admin.mfa@unsil.ac.id', 'password' => 'sandi-kuat-123456']);
    Filament::setCurrentPanel('admin');

    Livewire::test(Masuk::class)
        ->fillForm(['email' => 'admin.mfa@unsil.ac.id', 'password' => 'sandi-kuat-123456'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertGuest();
});

test('MFA_AKTIF=false: tidak ada kewajiban, tidak ada tantangan saat masuk, dan bagian MFA hilang dari profil', function () {
    config(['regulasi.mfa_aktif' => false]);

    $super = penggunaDenganPeran(Peran::SuperAdmin, ['email' => 'super.tanpa.mfa@unsil.ac.id', 'password' => 'sandi-kuat-123456']);
    $super->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    expect($super->wajibMfa())->toBeFalse()
        ->and(Filament::getPanel('admin')->hasMultiFactorAuthentication())->toBeFalse()
        ->and(Filament::getPanel('akun')->hasMultiFactorAuthentication())->toBeFalse();

    $this->actingAs(penggunaDenganPeran(Peran::AdminRegulasi, mfa: false))->get('/admin')->assertOk();

    auth()->logout();
    Filament::setCurrentPanel('admin');

    Livewire::test(Masuk::class)
        ->fillForm(['email' => 'super.tanpa.mfa@unsil.ac.id', 'password' => 'sandi-kuat-123456'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($super);

    $this->get('/admin/profile')->assertOk()->assertDontSee('Aplikasi authenticator');
});
