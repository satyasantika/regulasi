<?php

namespace Database\Seeders;

use App\Services\Pencarian\KamusPencarian;
use Illuminate\Database\Seeder;

/**
 * Sinonim awal pencarian dua arah dari config/regulasi.php → 'sinonim' (idempoten).
 */
class SinonimPencarianSeeder extends Seeder
{
    public function run(KamusPencarian $kamus): void
    {
        $kamus->semaiSinonim();
        $kamus->lupakan();
    }
}
