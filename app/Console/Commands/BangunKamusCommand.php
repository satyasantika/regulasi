<?php

namespace App\Console\Commands;

use App\Jobs\BangunKamusIstilah;
use App\Services\Pencarian\BangunKamus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Bangun ulang kamus istilah pencarian (02-ARSITEKTUR §5.1): sinkron (bawaan, dipakai penjadwal mingguan dan
 * pascadeploy) atau `--antre` untuk menaruhnya di antrean `berat`.
 */
#[Signature('regulasi:bangun-kamus {--antre : Antrekan ke antrean berat alih-alih menjalankan sekarang}')]
#[Description('Bangun ulang kamus istilah pencarian (istilah_pencarian) dan muat sinonim awal')]
class BangunKamusCommand extends Command
{
    public function handle(BangunKamus $bangun): int
    {
        if ($this->option('antre')) {
            BangunKamusIstilah::dispatch();
            $this->info('Pembangunan kamus diantrekan (antrean berat).');

            return self::SUCCESS;
        }

        $hasil = $bangun->handle();

        $this->info("Kamus dibangun: {$hasil['istilah']} istilah, {$hasil['dihapus']} istilah usang dihapus, {$hasil['sinonim_baru']} sinonim baru.");

        return self::SUCCESS;
    }
}
