<?php

use App\Enums\Peran;
use App\Filament\Akun\Pages\Daftar;
use App\Filament\Auth\Masuk;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
});

test('halaman masuk kedua panel tampil berbahasa indonesia', function (string $path) {
    $this->get($path)->assertOk()->assertSee('Masuk ke akun Anda');
})->with(['/admin/login', '/akun/login']);

test('kontributor bisa membuka panel admin', function () {
    $this->actingAs(penggunaDenganPeran(Peran::Kontributor))->get('/admin')->assertOk();
});

test('pengguna mendapat 403 di admin tetapi bisa membuka akun', function () {
    $pengguna = penggunaDenganPeran(Peran::Pengguna);

    $this->actingAs($pengguna)->get('/admin')->assertForbidden();
    $this->actingAs($pengguna)->get('/akun')->assertOk()->assertSee('Ke beranda portal')->assertDontSee('Ke panel admin');
});

test('dasbor akun staf menampilkan tautan ke panel admin', function () {
    $this->actingAs(penggunaDenganPeran(Peran::Kontributor))->get('/akun')->assertOk()->assertSee('Ke panel admin');
});

test('pengguna nonaktif ditolak di kedua panel', function (string $path) {
    $this->actingAs(penggunaDenganPeran(Peran::Kontributor, ['aktif' => false]))->get($path)->assertForbidden();
})->with(['/admin', '/akun']);

test('pengguna nonaktif tidak bisa masuk lewat formulir', function () {
    penggunaDenganPeran(Peran::Kontributor, ['email' => 'nonaktif@unsil.ac.id', 'password' => 'rahasia-panjang', 'aktif' => false]);
    Filament::setCurrentPanel('admin');

    Livewire::test(Masuk::class)
        ->fillForm(['email' => 'nonaktif@unsil.ac.id', 'password' => 'rahasia-panjang'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

test('registrasi dengan surel gmail ditolak', function () {
    Filament::setCurrentPanel('akun');

    Livewire::test(Daftar::class)
        ->fillForm([
            'name' => 'Nama Luar',
            'email' => 'nama@gmail.com',
            'password' => 'kata-sandi-kuat-123',
            'passwordConfirmation' => 'kata-sandi-kuat-123',
        ])
        ->call('register')
        ->assertHasFormErrors(['email'])
        ->assertSee('Gunakan surel @unsil.ac.id');

    expect(User::where('email', 'nama@gmail.com')->exists())->toBeFalse();
});

test('registrasi dengan surel unsil.ac.id berhasil, berperan pengguna, dan diminta verifikasi', function () {
    Notification::fake();
    Filament::setCurrentPanel('akun');

    Livewire::test(Daftar::class)
        ->fillForm([
            'name' => 'Dosen Kampus',
            'email' => 'dosen@unsil.ac.id',
            'password' => 'kata-sandi-kuat-123',
            'passwordConfirmation' => 'kata-sandi-kuat-123',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $pengguna = User::where('email', 'dosen@unsil.ac.id')->firstOrFail();

    expect($pengguna->hasRole(Peran::Pengguna->value))->toBeTrue()
        ->and($pengguna->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($pengguna, VerifyEmail::class);

    $this->actingAs($pengguna)->get('/akun')->assertRedirectContains('email-verification');
});

test('masuk di panel akun diarahkan ke beranda portal dan mencatat waktu masuk', function () {
    $pengguna = penggunaDenganPeran(Peran::Pengguna, ['email' => 'baca@unsil.ac.id', 'password' => 'rahasia-panjang']);
    Filament::setCurrentPanel('akun');

    Livewire::test(Masuk::class)
        ->fillForm(['email' => 'baca@unsil.ac.id', 'password' => 'rahasia-panjang'])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect(url('/'));

    $this->assertAuthenticatedAs($pengguna);
    expect($pengguna->fresh()->terakhir_masuk_pada)->not->toBeNull();
});

test('masuk di panel admin diarahkan ke dasbor admin', function () {
    penggunaDenganPeran(Peran::Kontributor, ['email' => 'staf@unsil.ac.id', 'password' => 'rahasia-panjang']);
    Filament::setCurrentPanel('admin');

    Livewire::test(Masuk::class)
        ->fillForm(['email' => 'staf@unsil.ac.id', 'password' => 'rahasia-panjang'])
        ->call('authenticate')
        ->assertRedirect(Filament::getPanel('admin')->getUrl());
});

test('percobaan masuk ke-6 dalam semenit dibatasi', function () {
    penggunaDenganPeran(Peran::Kontributor, ['email' => 'uji@unsil.ac.id', 'password' => 'benar-benar-rahasia']);
    Filament::setCurrentPanel('admin');

    for ($i = 1; $i <= 5; $i++) {
        Livewire::test(Masuk::class)
            ->fillForm(['email' => 'uji@unsil.ac.id', 'password' => 'salah'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);
    }

    // Percobaan ke-6 dengan kata sandi BENAR tetap ditolak.
    Livewire::test(Masuk::class)
        ->fillForm(['email' => 'uji@unsil.ac.id', 'password' => 'benar-benar-rahasia'])
        ->call('authenticate')
        ->assertNotified('Terlalu banyak percobaan masuk');

    $this->assertGuest();
    RateLimiter::clear(Masuk::kunciPembatas('uji@unsil.ac.id', '127.0.0.1'));
});

test('pembatasan masuk terpisah per surel', function () {
    penggunaDenganPeran(Peran::Kontributor, ['email' => 'b@unsil.ac.id', 'password' => 'rahasia-b-12345']);
    Filament::setCurrentPanel('admin');

    for ($i = 1; $i <= 5; $i++) {
        Livewire::test(Masuk::class)->fillForm(['email' => 'a@unsil.ac.id', 'password' => 'x'])->call('authenticate');
    }

    Livewire::test(Masuk::class)
        ->fillForm(['email' => 'b@unsil.ac.id', 'password' => 'rahasia-b-12345'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticated();
});
