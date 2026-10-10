<?php

namespace Database\Seeders;

use App\Enums\Peran;
use App\Models\KategoriSistem;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Super-admin dari .env; akun contoh hanya di lingkungan lokal (03-SKEMA §4.1).
 */
class PenggunaAwalSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('regulasi.seed_superadmin.email');
        $password = config('regulasi.seed_superadmin.password');

        if (blank($email) || blank($password)) {
            if (app()->isProduction()) {
                throw new RuntimeException('SEED_SUPERADMIN_EMAIL dan SEED_SUPERADMIN_PASSWORD wajib diisi di .env sebelum menjalankan seeder pengguna awal di produksi.');
            }

            logger()->warning('SEED_SUPERADMIN_EMAIL/SEED_SUPERADMIN_PASSWORD kosong: super-admin tidak dibuat.');
        } else {
            $this->buat($email, 'Super Admin', $password, Peran::SuperAdmin);
        }

        if (app()->isLocal()) {
            $this->buat('admin@unsil.test', 'Admin Regulasi', 'password', Peran::AdminRegulasi);
            $kontributor = $this->buat('kontributor.aset@unsil.test', 'Kontributor Aset', 'password', Peran::Kontributor);
            $this->tugaskan($kontributor, 'aset');
            $this->buat('pengguna@unsil.test', 'Pengguna Kampus', 'password', Peran::Pengguna);
        }
    }

    private function buat(string $email, string $nama, string $password, Peran $peran): User
    {
        $pengguna = User::firstOrCreate(
            ['email' => $email],
            ['name' => $nama, 'password' => $password, 'aktif' => true],
        );

        $pengguna->forceFill(['email_verified_at' => $pengguna->email_verified_at ?? now()])->save();
        $pengguna->syncRoles([$peran->value]);

        return $pengguna;
    }

    /**
     * Penugasan kategori contoh (03-SKEMA §4.1); dilewati bila kategori belum di-seed.
     */
    private function tugaskan(User $pengguna, string $kodeKategori): void
    {
        $kategori = KategoriSistem::query()->where('kode', $kodeKategori)->first();

        if ($kategori !== null) {
            $pengguna->kategoriSistem()->syncWithoutDetaching([$kategori->id]);
        }
    }
}
