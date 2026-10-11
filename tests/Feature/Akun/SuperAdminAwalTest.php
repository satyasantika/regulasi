<?php

use App\Enums\Peran;
use App\Models\User;
use Database\Seeders\PenggunaAwalSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    config(['regulasi.seed_superadmin' => ['email' => 'fkip@unsil.ac.id', 'password' => 'sandi-awal-uji-123', 'nama' => 'Super Admin FKIP']]);
});

test('surel super-admin bawaan adalah fkip@unsil.ac.id dan sandi hanya dari env', function () {
    $konfigurasi = (string) file_get_contents(config_path('regulasi.php'));

    expect($konfigurasi)->toContain("env('REGULASI_SUPERADMIN_EMAIL', 'fkip@unsil.ac.id')")
        ->toContain("env('REGULASI_SUPERADMIN_PASSWORD')")
        ->and((string) file_get_contents(base_path('.env.example')))->toMatch('/^REGULASI_SUPERADMIN_PASSWORD=$/m');
});

test('seeder membuat super-admin fkip@unsil.ac.id yang wajib ganti sandi saat masuk pertama', function () {
    $this->seed(PenggunaAwalSeeder::class);

    $super = User::where('email', 'fkip@unsil.ac.id')->sole();

    expect($super->hasRole(Peran::SuperAdmin->value))->toBeTrue()
        ->and($super->aktif)->toBeTrue()
        ->and($super->wajib_ganti_sandi)->toBeTrue()
        ->and($super->email_verified_at)->not->toBeNull()
        ->and(Hash::check('sandi-awal-uji-123', $super->password))->toBeTrue();
});

test('seeder idempoten: tidak menggandakan akun dan tidak menimpa sandi yang sudah diganti', function () {
    $this->seed(PenggunaAwalSeeder::class);

    $super = User::where('email', 'fkip@unsil.ac.id')->sole();
    $super->forceFill(['password' => Hash::make('sandi-pilihan-sendiri-9'), 'wajib_ganti_sandi' => false])->save();

    $this->seed(PenggunaAwalSeeder::class);

    $super->refresh();
    expect(User::where('email', 'fkip@unsil.ac.id')->count())->toBe(1)
        ->and($super->wajib_ganti_sandi)->toBeFalse()
        ->and(Hash::check('sandi-pilihan-sendiri-9', $super->password))->toBeTrue();
});

test('super-admin baru diarahkan ke profil untuk ganti sandi', function () {
    $this->seed(PenggunaAwalSeeder::class);
    $super = User::where('email', 'fkip@unsil.ac.id')->sole();
    $super->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    $this->actingAs($super)->get('/admin')->assertRedirect(url('admin/profile'));
});

test('tanpa sandi di env super-admin tidak dibuat di luar produksi', function () {
    config(['regulasi.seed_superadmin.password' => null]);

    $this->seed(PenggunaAwalSeeder::class);

    expect(User::where('email', 'fkip@unsil.ac.id')->exists())->toBeFalse();
});

test('sandi super-admin tidak tertulis di berkas yang di-commit', function () {
    foreach (['.env.example', '.env.production.example', 'config/regulasi.php', 'database/seeders/PenggunaAwalSeeder.php', 'docs/DEPLOY.md', 'docs/OPERASIONAL.md', 'README.md'] as $berkas) {
        expect((string) file_get_contents(base_path($berkas)))->not->toContain('siliwangi', $berkas);
    }
});
