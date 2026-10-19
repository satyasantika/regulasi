<?php

use App\Enums\Peran;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PenggunaAwalSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
});

test('seeder membuat empat peran dan 21 permission', function () {
    expect(Role::pluck('name')->sort()->values()->all())
        ->toBe(['admin-regulasi', 'kontributor', 'pengguna', 'super-admin'])
        ->and(Permission::count())->toBe(21);
});

test('peran dan permission memakai uuid', function () {
    expect(Role::first()->id)->toBeUuid()
        ->and(Permission::first()->id)->toBeUuid();
});

test('admin-regulasi punya dokumen.verifikasi', function () {
    $admin = User::factory()->create()->assignRole(Peran::AdminRegulasi->value);

    expect($admin->can('dokumen.verifikasi'))->toBeTrue()
        ->and($admin->can('audit.lihat'))->toBeTrue()
        ->and($admin->can('pengguna.kelola'))->toBeFalse();
});

test('kontributor tidak punya dokumen.verifikasi maupun master.kelola', function () {
    $kontributor = User::factory()->create()->assignRole(Peran::Kontributor->value);

    expect($kontributor->can('dokumen.verifikasi'))->toBeFalse()
        ->and($kontributor->can('master.kelola'))->toBeFalse()
        ->and($kontributor->can('dokumen.buat'))->toBeTrue();
});

test('pengguna hanya punya dokumen.lihat-internal', function () {
    $pengguna = User::factory()->create()->assignRole(Peran::Pengguna->value);

    expect($pengguna->getAllPermissions()->pluck('name')->all())->toBe(['dokumen.lihat-internal']);
});

test('super-admin lolos gate apa pun', function () {
    $super = User::factory()->create()->assignRole(Peran::SuperAdmin->value);

    Gate::define('gate-uji-tak-terdaftar', fn (): bool => false);

    expect(Gate::forUser($super)->allows('gate-uji-tak-terdaftar'))->toBeTrue()
        ->and($super->can('pengguna.kelola'))->toBeTrue();
});

test('seeder dapat dijalankan dua kali tanpa duplikat', function () {
    $this->seed(PeranDanIzinSeeder::class);
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Role::count())->toBe(4)
        ->and(Permission::count())->toBe(21)
        ->and(Role::findByName('admin-regulasi')->permissions()->count())->toBe(18)
        ->and(Role::findByName('kontributor')->permissions()->count())->toBe(10);
});

test('seeder pengguna awal membuat super-admin dari konfigurasi', function () {
    config(['regulasi.seed_superadmin' => ['email' => 'akar@unsil.test', 'password' => 'rahasia-uji-123']]);

    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'akar@unsil.test')->firstOrFail()->hasRole('super-admin'))->toBeTrue()
        ->and(User::where('email', 'admin@unsil.test')->exists())->toBeFalse();
});

test('seeder pengguna awal gagal jelas di produksi bila env kosong', function () {
    config(['regulasi.seed_superadmin' => ['email' => null, 'password' => null]]);
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new PenggunaAwalSeeder)->run())
        ->toThrow(RuntimeException::class, 'REGULASI_SUPERADMIN_PASSWORD');
});

test('akun contoh hanya dibuat di lingkungan lokal', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'admin@unsil.test')->firstOrFail()->hasRole('admin-regulasi'))->toBeTrue()
        ->and(User::where('email', 'kontributor.aset@unsil.test')->firstOrFail()->hasRole('kontributor'))->toBeTrue()
        ->and(User::where('email', 'pengguna@unsil.test')->firstOrFail()->hasRole('pengguna'))->toBeTrue();
});

test('izin horizon.akses tidak lagi ada dan rute /horizon tidak tersedia (Horizon dicabut)', function () {
    $this->seed(PeranDanIzinSeeder::class);

    expect(Permission::query()->where('name', 'horizon.akses')->exists())->toBeFalse();

    $this->actingAs(User::factory()->create()->assignRole(Peran::SuperAdmin->value))->get('/horizon')->assertNotFound();
});

test('migrasi data menghapus izin horizon.akses warisan dari basis data produksi beserta pemegangnya', function () {
    $this->seed(PeranDanIzinSeeder::class);
    $izin = Permission::query()->create(['name' => 'horizon.akses', 'guard_name' => 'web']);
    $peran = Role::query()->where('name', Peran::AdminRegulasi->value)->firstOrFail();
    $peran->givePermissionTo($izin);
    $pengguna = User::factory()->create();
    $pengguna->givePermissionTo($izin);

    (require database_path('migrations/2026_10_20_100003_hapus_izin_horizon_akses.php'))->up();

    expect(Permission::query()->where('name', 'horizon.akses')->exists())->toBeFalse()
        ->and(DB::table('role_has_permissions')->where('permission_id', $izin->getKey())->exists())->toBeFalse()
        ->and(DB::table('model_has_permissions')->where('permission_id', $izin->getKey())->exists())->toBeFalse()
        ->and(Permission::query()->where('name', 'dokumen.verifikasi')->exists())->toBeTrue();

    // idempoten: menjalankan ulang pada basis data bersih tidak gagal
    (require database_path('migrations/2026_10_20_100003_hapus_izin_horizon_akses.php'))->up();
});

test('kolom tambahan users tersedia dengan bawaan aktif', function () {
    $user = User::factory()->create(['nip' => '198001012005011001', 'kode_eksternal' => 'EXT-1']);

    expect($user->fresh())
        ->aktif->toBeTrue()
        ->nip->toBe('198001012005011001')
        ->terakhir_masuk_pada->toBeNull();
});
