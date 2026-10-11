<?php

use App\Actions\Pengguna\AkhiriImpersonasi;
use App\Actions\Pengguna\MulaiImpersonasi;
use App\Enums\Peran;
use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Models\Aktivitas;
use App\Support\Impersonasi;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->super = penggunaDenganPeran(Peran::SuperAdmin);
    Filament::setCurrentPanel('admin');
});

test('super-admin masuk sebagai kontributor dari tabel pengguna, melihat spanduk, lalu kembali', function () {
    $kontributor = penggunaDenganPeran(Peran::Kontributor, ['name' => 'Kontributor Uji']);

    Livewire::actingAs($this->super)->test(ListUsers::class)
        ->callAction(TestAction::make('impersonasi')->table($kontributor))
        ->assertRedirect(url('admin'));

    expect(auth()->id())->toBe($kontributor->id)
        ->and(session(Impersonasi::KUNCI))->toBe($this->super->id);

    $this->get('/admin')->assertOk()->assertSee('Kembali ke akun saya')->assertSee($this->super->email);
    $this->get('/')->assertOk()->assertSee('Kembali ke akun saya');

    $this->post(route('impersonasi.berhenti'))->assertRedirect(url('admin/pengguna'));

    expect(auth()->id())->toBe($this->super->id)->and(session()->has(Impersonasi::KUNCI))->toBeFalse();
    $this->get('/admin')->assertOk()->assertDontSee('Kembali ke akun saya');
});

test('pengguna panel akun diarahkan ke panel akun saat diimpersonasi', function () {
    $pengguna = penggunaDenganPeran(Peran::Pengguna);

    Livewire::actingAs($this->super)->test(ListUsers::class)
        ->callAction(TestAction::make('impersonasi')->table($pengguna))
        ->assertRedirect(url('akun'));
});

test('tidak dapat impersonasi diri sendiri, super-admin lain, atau akun nonaktif', function () {
    $superLain = penggunaDenganPeran(Peran::SuperAdmin);
    $nonaktif = penggunaDenganPeran(Peran::Pengguna, ['aktif' => false]);

    foreach ([$this->super, $superLain, $nonaktif] as $sasaran) {
        expect($this->super->can('impersonate', $sasaran))->toBeFalse();
        $this->actingAs($this->super);
        expect(fn () => app(MulaiImpersonasi::class)->handle($this->super, $sasaran))->toThrow(AuthorizationException::class);
    }

    Livewire::actingAs($this->super)->test(ListUsers::class)
        ->assertActionHidden(TestAction::make('impersonasi')->table($superLain))
        ->assertActionHidden(TestAction::make('impersonasi')->table($this->super));
});

test('selain super-admin tidak dapat impersonasi', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $pengguna = penggunaDenganPeran(Peran::Pengguna);

    expect($admin->can('impersonate', $pengguna))->toBeFalse();

    Livewire::actingAs($admin)->test(ListUsers::class)
        ->assertActionHidden(TestAction::make('impersonasi')->table($pengguna));
});

test('impersonasi tercatat dan aktivitas selama impersonasi memuat id super-admin asal', function () {
    $kontributor = penggunaDenganPeran(Peran::Kontributor);
    $this->actingAs($this->super);

    app(MulaiImpersonasi::class)->handle($this->super, $kontributor);
    activity('uji')->log('tindakan saat impersonasi');
    app(AkhiriImpersonasi::class)->handle();

    expect(Aktivitas::where('event', 'impersonasi-mulai')->sole()->causer_id)->toBe($this->super->id)
        ->and(Aktivitas::where('event', 'impersonasi-akhir')->sole()->subject_id)->toBe($kontributor->id)
        ->and(Aktivitas::where('log_name', 'uji')->sole()->properties['impersonasi_oleh'])->toBe($this->super->id)
        ->and($kontributor->fresh()->terakhir_masuk_pada)->toBeNull();
});

test('aman terhadap MFA dan wajib ganti sandi akun sasaran; profil sasaran tidak dapat diubah', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi, ['wajib_ganti_sandi' => true], mfa: false);
    $this->actingAs($this->super);

    app(MulaiImpersonasi::class)->handle($this->super, $admin);

    $this->get('/admin')->assertOk();
    $this->get('/admin/profile')->assertForbidden();
});

test('mengakhiri tanpa impersonasi ditolak', function () {
    $this->actingAs($this->super)->post(route('impersonasi.berhenti'))->assertForbidden();
});

test('tamu tidak dapat memanggil rute akhiri impersonasi', function () {
    $this->post(route('impersonasi.berhenti'))->assertRedirect();
    $this->assertGuest();
});
