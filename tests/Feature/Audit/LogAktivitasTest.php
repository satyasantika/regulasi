<?php

use App\Enums\Peran;
use App\Filament\Admin\Resources\ActivityResource\Pages\ListActivities;
use App\Filament\Admin\Resources\ActivityResource\Pages\ViewActivity;
use App\Models\Aktivitas;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel('admin');
});

function logPengguna(User $user): string
{
    return Aktivitas::query()->where('subject_id', $user->id)->get()
        ->map(fn (Aktivitas $a): string => json_encode($a->attribute_changes).json_encode($a->properties))
        ->implode('');
}

test('mengubah email pengguna tercatat dengan nilai lama dan baru', function () {
    $user = User::factory()->create(['email' => 'lama@unsil.ac.id']);
    $admin = User::factory()->create()->assignRole(Peran::SuperAdmin->value);
    $this->actingAs($admin);

    $user->update(['email' => 'baru@unsil.ac.id']);

    $log = Aktivitas::query()->where('subject_id', $user->id)->where('event', 'updated')->latest()->firstOrFail();

    expect($log->attribute_changes->get('old'))->toBe(['email' => 'lama@unsil.ac.id'])
        ->and($log->attribute_changes->get('attributes'))->toBe(['email' => 'baru@unsil.ac.id'])
        ->and($log->causer_id)->toBe($admin->id)
        ->and($log->log_name)->toBe('pengguna')
        ->and($log->id)->toBeUuid();
});

test('mengubah nama pengguna tercatat dengan nilai lama dan baru', function () {
    $user = User::factory()->create(['name' => 'Nama Lama']);

    $user->update(['name' => 'Nama Baru']);

    $log = Aktivitas::query()->where('subject_id', $user->id)->where('event', 'updated')->firstOrFail();

    expect($log->attribute_changes->get('old'))->toBe(['name' => 'Nama Lama'])
        ->and($log->attribute_changes->get('attributes'))->toBe(['name' => 'Nama Baru']);
});

test('kata sandi dan remember token tidak pernah muncul di log', function () {
    $user = User::factory()->create(['password' => 'sandi-pertama-123']);

    $user->forceFill(['password' => 'sandi-kedua-456', 'remember_token' => 'token-rahasia'])->save();
    $user->update(['name' => 'Ganti Nama', 'password' => 'sandi-ketiga-789']);

    $log = logPengguna($user);

    expect($log)->not->toContain('password')
        ->not->toContain('sandi-')
        ->not->toContain('remember_token')
        ->not->toContain('token-rahasia')
        ->and(Aktivitas::query()->where('subject_id', $user->id)->where('event', 'updated')->count())->toBe(1);
});

test('perubahan peran pengguna tercatat', function () {
    $user = User::factory()->create();

    $user->assignRole(Peran::Kontributor->value);
    $user->removeRole(Peran::Kontributor->value);

    $peristiwa = Aktivitas::query()->where('subject_id', $user->id)->orderBy('id')->pluck('event')->all();

    expect($peristiwa)->toContain('peran-ditambahkan')->toContain('peran-dicabut')
        ->and(Aktivitas::query()->where('event', 'peran-ditambahkan')->firstOrFail()->properties->get('peran'))->toBe(['kontributor']);
});

test('kontributor mendapat 403 di halaman log aktivitas', function () {
    $this->actingAs(User::factory()->create()->assignRole(Peran::Kontributor->value))
        ->get('/admin/log-aktivitas')
        ->assertForbidden();
});

test('admin-regulasi dapat melihat daftar dan rincian log aktivitas', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $user = User::factory()->create(['name' => 'Nama Lama']);
    $user->update(['name' => 'Nama Baru']);
    $log = Aktivitas::query()->where('event', 'updated')->firstOrFail();

    $this->actingAs($admin)->get('/admin/log-aktivitas')->assertOk()->assertSee('Log Aktivitas');

    Livewire::actingAs($admin)->test(ListActivities::class)
        ->assertCanSeeTableRecords([$log])
        ->assertActionDoesNotExist('create');

    Livewire::actingAs($admin)->test(ViewActivity::class, ['record' => $log->getKey()])
        ->assertOk()
        ->assertSee('Nama Lama')
        ->assertSee('Nama Baru');
});

test('menu log aktivitas tampil untuk super-admin dan admin-regulasi', function (Peran $peran) {
    $this->actingAs(penggunaDenganPeran($peran))
        ->get('/admin')
        ->assertOk()
        ->assertSee('Log Aktivitas');
})->with([Peran::SuperAdmin, Peran::AdminRegulasi]);

test('menu log aktivitas tidak tampil untuk kontributor', function () {
    $this->actingAs(User::factory()->create()->assignRole(Peran::Kontributor->value))
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('Log Aktivitas');
});

test('log aktivitas tidak dapat diubah atau dihapus', function () {
    $super = User::factory()->create()->assignRole(Peran::SuperAdmin->value);
    User::factory()->create()->update(['name' => 'X']);

    Livewire::actingAs($super)->test(ListActivities::class)
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete');
});
