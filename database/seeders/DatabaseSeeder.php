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
            PenggunaAwalSeeder::class,
        ]);
    }
}
