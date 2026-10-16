<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PeranDanIzinSeeder::class,
            JenisDokumenSeeder::class,
            BadanPenetapSeeder::class,
            KategoriSistemSeeder::class,
            TagSeeder::class,
            SinonimPencarianSeeder::class,
            PenggunaAwalSeeder::class,
        ]);

        // Dokumen contoh hanya di luar produksi; di produksi jalankan eksplisit:
        // php artisan db:seed --class=DokumenContohSeeder
        if (! app()->isProduction()) {
            $this->call(DokumenContohSeeder::class);
        }
    }
}
