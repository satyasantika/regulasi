<?php

namespace Database\Seeders;

use App\Enums\Peran;
use App\Models\KategoriSistem;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Super-admin awal (bawaan fkip@unsil.ac.id, sandi dari .env); akun contoh hanya di lingkungan lokal.
 */
class PenggunaAwalSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('regulasi.seed_superadmin.email');
        $password = config('regulasi.seed_superadmin.password');

        if (blank($email) || blank($password)) {
            if (app()->isProduction()) {
                throw new RuntimeException('REGULASI_SUPERADMIN_PASSWORD wajib diisi di .env sebelum menjalankan seeder pengguna awal di produksi.');
            }

            logger()->warning('REGULASI_SUPERADMIN_PASSWORD kosong: super-admin tidak dibuat.');
        } else {
            $this->buatSuperAdmin((string) $email, (string) config('regulasi.seed_superadmin.nama', 'Super Admin FKIP'), (string) $password);
        }

        if (app()->isLocal()) {
            $this->buat('admin@unsil.test', 'Admin Regulasi', 'password', Peran::AdminRegulasi);
            $kontributor = $this->buat('kontributor.aset@unsil.test', 'Kontributor Aset', 'password', Peran::Kontributor);
            $this->tugaskan($kontributor, 'aset');
            $this->buat('pengguna@unsil.test', 'Pengguna Kampus', 'password', Peran::Pengguna);
        }
    }

    /**
     * Super-admin awal (idempoten): dibuat sekali dengan sandi dari .env dan ditandai wajib ganti sandi saat masuk
     * pertama. Menjalankan ulang seeder tidak mengubah sandi maupun tanda wajib ganti sandi akun yang sudah ada.
     */
    private function buatSuperAdmin(string $email, string $nama, string $password): User
    {
        $pengguna = $this->buat($email, $nama, $password, Peran::SuperAdmin);

        if ($pengguna->wasRecentlyCreated) {
            $pengguna->forceFill(['wajib_ganti_sandi' => true])->save();
        }

        return $pengguna;
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
