<?php

use App\Enums\Peran;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Uji Feature memakai TestCase Laravel. RefreshDatabase dipasang per berkas uji yang menyentuh basis data.
|
*/

pest()->extend(TestCase::class)
    ->in('Feature');

/**
 * Pengguna aktif dengan peran tertentu (seeder peran dijalankan bila belum).
 * Peran yang wajib MFA otomatis dianggap sudah mengaktifkan MFA kecuali $mfa = false.
 *
 * @param  array<string, mixed>  $atribut
 */
function penggunaDenganPeran(Peran $peran, array $atribut = [], bool $mfa = true): User
{
    if (Role::query()->count() === 0) {
        test()->seed(PeranDanIzinSeeder::class);
    }

    $pengguna = User::factory()->create($atribut)->assignRole($peran->value);

    if ($mfa && $pengguna->wajibMfa()) {
        $pengguna->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    }

    return $pengguna;
}
