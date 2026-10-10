<?php

use App\Actions\Pengguna\UbahPengguna;
use App\Enums\Peran;
use App\Filament\Admin\Resources\UserResource\Pages\CreateUser;
use App\Filament\Admin\Resources\UserResource\Pages\EditUser;
use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Models\Aktivitas;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

test('super-admin membuat kontributor dan surel atur kata sandi terkirim', function () {
    Notification::fake();
    $super = penggunaDenganPeran(Peran::SuperAdmin);

    Livewire::actingAs($super)->test(CreateUser::class)
        ->fillForm([
            'name' => 'Pengelola Aset',
            'email' => 'aset@unsil.ac.id',
            'nip' => '198001012005011001',
            'peran' => [Peran::Kontributor->value],
            'aktif' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $baru = User::where('email', 'aset@unsil.ac.id')->firstOrFail();

    expect($baru->hasRole(Peran::Kontributor->value))->toBeTrue()
        ->and($baru->aktif)->toBeTrue()
        ->and($baru->hasVerifiedEmail())->toBeTrue();

    Notification::assertSentTo($baru, ResetPassword::class, fn (ResetPassword $n): bool => str_contains($n->url, '/admin/password-reset/reset'));
    expect(DB::table('password_reset_tokens')->where('email', 'aset@unsil.ac.id')->exists())->toBeTrue();
});

test('pembuatan pengguna tercatat di log aktivitas termasuk perannya', function () {
    Notification::fake();
    $super = penggunaDenganPeran(Peran::SuperAdmin);

    Livewire::actingAs($super)->test(CreateUser::class)
        ->fillForm(['name' => 'Admin Baru', 'email' => 'adminbaru@unsil.ac.id', 'peran' => [Peran::AdminRegulasi->value]])
        ->call('create')
        ->assertHasNoFormErrors();

    $baru = User::where('email', 'adminbaru@unsil.ac.id')->firstOrFail();

    expect(Aktivitas::where('subject_id', $baru->id)->pluck('event')->all())->toContain('created')->toContain('peran-ditambahkan')
        ->and(Aktivitas::where('subject_id', $baru->id)->where('event', 'created')->firstOrFail()->causer_id)->toBe($super->id);
});

test('form pengguna tidak memiliki isian kata sandi', function () {
    Livewire::actingAs(penggunaDenganPeran(Peran::SuperAdmin))->test(CreateUser::class)
        ->assertFormFieldDoesNotExist('password')
        ->assertFormFieldExists('peran');
});

test('admin-regulasi bisa melihat daftar tetapi tidak bisa membuka form edit', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $target = penggunaDenganPeran(Peran::Kontributor);

    $this->actingAs($admin)->get('/admin/pengguna')->assertOk()->assertSee($target->email);
    $this->actingAs($admin)->get("/admin/pengguna/{$target->id}")->assertOk();
    $this->actingAs($admin)->get("/admin/pengguna/{$target->id}/edit")->assertForbidden();
    $this->actingAs($admin)->get('/admin/pengguna/create')->assertForbidden();

    Livewire::actingAs($admin)->test(ListUsers::class)
        ->assertActionHidden(TestAction::make('edit')->table($target))
        ->assertActionHidden('create');
});

test('kontributor dan pengguna tidak bisa membuka manajemen pengguna', function () {
    $this->actingAs(penggunaDenganPeran(Peran::Kontributor))->get('/admin/pengguna')->assertForbidden();
});

test('super-admin tidak bisa menonaktifkan diri sendiri', function () {
    $super = penggunaDenganPeran(Peran::SuperAdmin);

    Livewire::actingAs($super)->test(EditUser::class, ['record' => $super->getKey()])
        ->assertFormFieldDisabled('aktif');

    expect(fn () => app(UbahPengguna::class)->handle($super, ['aktif' => false], $super))
        ->toThrow(ValidationException::class);

    expect($super->fresh()->aktif)->toBeTrue();
});

test('super-admin menonaktifkan pengguna lain dan mengubah perannya', function () {
    $super = penggunaDenganPeran(Peran::SuperAdmin);
    $target = penggunaDenganPeran(Peran::Pengguna);

    Livewire::actingAs($super)->test(EditUser::class, ['record' => $target->getKey()])
        ->assertSchemaStateSet(['peran' => [Peran::Pengguna->value]])
        ->fillForm(['aktif' => false, 'peran' => [Peran::Kontributor->value]])
        ->call('save')
        ->assertHasNoFormErrors();

    $target->refresh();

    expect($target->aktif)->toBeFalse()
        ->and($target->getRoleNames()->all())->toBe([Peran::Kontributor->value])
        ->and(Aktivitas::where('subject_id', $target->id)->where('event', 'peran-dicabut')->firstOrFail()->properties->get('peran'))->toBe(['pengguna']);
});

test('tidak ada aksi hapus pengguna', function () {
    $super = penggunaDenganPeran(Peran::SuperAdmin);
    $target = penggunaDenganPeran(Peran::Pengguna);

    Livewire::actingAs($super)->test(ListUsers::class)
        ->assertActionDoesNotExist(TestAction::make('delete')->table($target));

    Livewire::actingAs($super)->test(EditUser::class, ['record' => $target->getKey()])
        ->assertActionDoesNotExist('delete');
});

test('admin-regulasi tanpa mfa diarahkan ke pengaturan mfa', function (Peran $peran) {
    $respons = $this->actingAs(penggunaDenganPeran($peran, mfa: false))->get('/admin');

    $respons->assertRedirect();
    expect($respons->headers->get('Location'))->toContain('multi-factor-authentication/set-up');
})->with([Peran::AdminRegulasi, Peran::SuperAdmin]);

test('halaman pengaturan mfa dapat dibuka tanpa putaran pengalihan', function () {
    $this->actingAs(penggunaDenganPeran(Peran::AdminRegulasi, mfa: false))
        ->get('/admin/multi-factor-authentication/set-up')
        ->assertOk();
});

test('kontributor tanpa mfa tetap bisa masuk panel admin dan pengguna di panel akun', function () {
    $this->actingAs(penggunaDenganPeran(Peran::Kontributor, mfa: false))->get('/admin')->assertOk();
    $this->actingAs(penggunaDenganPeran(Peran::Pengguna, mfa: false))->get('/akun')->assertOk();
});

test('rahasia mfa tersimpan terenkripsi dan tidak masuk log aktivitas', function () {
    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $user->saveAppAuthenticationRecoveryCodes(['kode-satu', 'kode-dua']);

    $mentah = DB::table('users')->where('id', $user->id)->first();

    expect($mentah->app_authentication_secret)->not->toContain('JBSWY3DPEHPK3PXP')
        ->and($mentah->app_authentication_recovery_codes)->not->toContain('kode-satu')
        ->and($user->fresh()->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXP');

    $log = Aktivitas::where('subject_id', $user->id)->get()->map(fn ($a) => json_encode($a->attribute_changes).json_encode($a->properties))->implode('');
    expect($log)->not->toContain('JBSWY3DPEHPK3PXP')->not->toContain('app_authentication');
});
