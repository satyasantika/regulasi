<?php

namespace App\Console\Commands;

use App\Notifications\RingkasanMingguan;
use App\Support\PenerimaNotifikasi;
use App\Support\RingkasanMingguan as DataRingkasan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Kirim ringkasan mingguan ke admin-regulasi (US-NOT-03). Dijadwalkan Senin 07:30 WIB.
 */
#[Signature('regulasi:ringkasan-mingguan')]
#[Description('Kirim ringkasan mingguan dokumen, antrean verifikasi, kelengkapan, dan tautan bermasalah ke admin-regulasi')]
class RingkasanMingguanCommand extends Command
{
    public function handle(): int
    {
        $ringkasan = DataRingkasan::hitung();
        $penerima = PenerimaNotifikasi::admin();

        PenerimaNotifikasi::kirim($penerima, new RingkasanMingguan($ringkasan));

        foreach (RingkasanMingguan::baris($ringkasan) as $baris) {
            $this->line($baris);
        }

        $this->info("Ringkasan dikirim ke {$penerima->count()} penerima.");

        return self::SUCCESS;
    }
}
