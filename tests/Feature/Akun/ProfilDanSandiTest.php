<?php

use App\Actions\Pengguna\AturWajibGantiSandi;
use App\Enums\Peran;
use App\Filament\Admin\Resources\UserResource\Pages\EditUser;
use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Filament\Auth\EditProfil;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function gantiSandiLewatProfil(string $panel, User $pengguna, string $baru = 'sandi-baru-yang-kuat-123', string $lama = 'password'): Testable
{
    Filament::setCurrentPanel($panel);

    return Livewire::actingAs($pengguna)->test(EditProfil::class)
        ->fillForm([
            'name' => $pengguna->name,
            'password' => $baru,
            'passwordConfirmation' => $baru,
            'currentPassword' => $lama,
        ])
        ->call('save');
}

test('semua peran dapat membuka halaman profil di panelnya', function (Peran $peran, string $path) {
    $this->actingAs(penggunaDenganPeran($peran))->get($path)->assertOk()->assertSee('Ganti kata sandi');
})->with([
    [Peran::SuperAdmin, '/admin/profile'],
    [Peran::AdminRegulasi, '/admin/profile'],
    [Peran::Kontributor, '/admin/profile'],
    [Peran::Pengguna, '/akun/profile'],
]);

test('pengguna memperbarui nama dan NIP tanpa mengubah surel', function () {
    $pengguna = penggunaDenganPeran(Peran::Pengguna, ['email' => 'dosen@unsil.ac.id']);
    Filament::setCurrentPanel('akun');

    Livewire::actingAs($pengguna)->test(EditProfil::class)
        ->fillForm(['name' => 'Nama Baru', 'nip' => '198001012005011001', 'email' => 'lain@unsil.ac.id'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($pengguna->fresh())
        ->name->toBe('Nama Baru')
        ->nip->toBe('198001012005011001')
        ->email->toBe('dosen@unsil.ac.id');
});

test('ganti sandi mencabut kewajiban, mengganti token ingat-saya, mengeluarkan perangkat lain, dan tercatat', function (Peran $peran, string $panel) {
    Event::fake([OtherDeviceLogout::class]);
    $pengguna = penggunaDenganPeran($peran, ['wajib_ganti_sandi' => true]);
    $tokenLama = $pengguna->getRememberToken();

    gantiSandiLewatProfil($panel, $pengguna)->assertHasNoFormErrors();

    $pengguna->refresh();
    expect($pengguna->wajib_ganti_sandi)->toBeFalse()
        ->and($pengguna->sandi_diubah_pada)->not->toBeNull()
        ->and(Hash::check('sandi-baru-yang-kuat-123', $pengguna->password))->toBeTrue()
        ->and($pengguna->getRememberToken())->not->toBe($tokenLama);

    Event::assertDispatched(OtherDeviceLogout::class, fn (OtherDeviceLogout $e): bool => $e->user->is($pengguna));

    $log = Activity::query()->where('event', 'ganti-sandi')->sole();
    expect($log->causer_id)->toBe($pengguna->id)
        ->and(json_encode($log->properties))->not->toContain('sandi-baru-yang-kuat-123');
})->with([
    [Peran::Kontributor, 'admin'],
    [Peran::Pengguna, 'akun'],
]);

test('ganti sandi ditolak bila sandi lama salah atau sandi baru sama dengan yang sekarang', function () {
    $pengguna = penggunaDenganPeran(Peran::Pengguna);

    gantiSandiLewatProfil('akun', $pengguna, lama: 'salah-sandi')->assertHasFormErrors(['currentPassword']);
    gantiSandiLewatProfil('akun', $pengguna, baru: 'password')->assertHasFormErrors(['password']);

    expect(Hash::check('password', $pengguna->fresh()->password))->toBeTrue();
});

test('sesi lain dengan hash sandi lama dikeluarkan di portal dan panel', function () {
    $pengguna = penggunaDenganPeran(Peran::Pengguna);

    $this->actingAs($pengguna)->withSession(['password_hash_web' => $pengguna->getAuthPassword()])->get('/akun')->assertOk();

    $pengguna->forceFill(['password' => Hash::make('sandi-lain-yang-baru-1')])->save();

    $this->get('/akun')->assertRedirect();
    $this->assertGuest();
});

test('akun wajib ganti sandi diarahkan ke profil, tetapi profil dan keluar tetap terbuka', function (Peran $peran, string $panel) {
    $pengguna = penggunaDenganPeran($peran, ['wajib_ganti_sandi' => true]);

    $this->actingAs($pengguna)->get("/{$panel}")->assertRedirect(url("{$panel}/profile"));
    $this->actingAs($pengguna)->get("/{$panel}/profile")->assertOk();
    $this->actingAs($pengguna)->post("/{$panel}/logout")->assertRedirect();
})->with([
    [Peran::AdminRegulasi, 'admin'],
    [Peran::Pengguna, 'akun'],
]);

test('super-admin mengatur wajib ganti sandi lewat form dan aksi massal', function () {
    $super = penggunaDenganPeran(Peran::SuperAdmin);
    $a = penggunaDenganPeran(Peran::Kontributor);
    $b = penggunaDenganPeran(Peran::Pengguna);
    Filament::setCurrentPanel('admin');

    Livewire::actingAs($super)->test(EditUser::class, ['record' => $a->getKey()])
        ->fillForm(['wajib_ganti_sandi' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($a->fresh()->wajib_ganti_sandi)->toBeTrue();

    Livewire::actingAs($super)->test(ListUsers::class)
        ->callTableBulkAction('wajibkanGantiSandi', [$a, $b]);

    expect($b->fresh()->wajib_ganti_sandi)->toBeTrue()
        ->and(Activity::query()->where('event', 'wajib-ganti-sandi')->count())->toBe(2);

    Livewire::actingAs($super)->test(ListUsers::class)
        ->callTableBulkAction('cabutWajibGantiSandi', [$a, $b]);

    expect($a->fresh()->wajib_ganti_sandi)->toBeFalse()->and($b->fresh()->wajib_ganti_sandi)->toBeFalse();
});

test('selain super-admin tidak dapat mengatur wajib ganti sandi', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $sasaran = penggunaDenganPeran(Peran::Pengguna);

    expect(fn () => app(AturWajibGantiSandi::class)->handle([$sasaran], true, $admin))
        ->toThrow(AuthorizationException::class);

    Filament::setCurrentPanel('admin');
    Livewire::actingAs($admin)->test(ListUsers::class)->assertTableBulkActionHidden('wajibkanGantiSandi');
});
