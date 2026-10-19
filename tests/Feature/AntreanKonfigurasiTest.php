<?php

use App\Filament\Admin\Exports\KelengkapanDokumenExporter;
use App\Jobs\BuatEksporLaporan;
use App\Jobs\EkstrakTeksTautan;
use Filament\Actions\Exports\Models\Export;

/**
 * Profil Ringan (STANDAR-TEKNIS §1b): dua antrean di database, worker dinyalakan penjadwal.
 */
it('mendefinisikan koneksi database dan database-berat dengan retry_after di atas timeout worker', function () {
    $ringan = config('queue.connections.database');
    $berat = config('queue.connections.database-berat');

    expect($ringan['driver'])->toBe('database')
        ->and($ringan['after_commit'])->toBeTrue()
        ->and($ringan['retry_after'])->toBeGreaterThan(60)   // --timeout worker default
        ->and($berat['driver'])->toBe('database')
        ->and($berat['queue'])->toBe('berat')
        ->and($berat['after_commit'])->toBeTrue()
        ->and($berat['retry_after'])->toBeGreaterThan(900);  // --timeout worker berat
});

it('mengarahkan ekstraksi dan ekspor ke koneksi database-berat dan antrean berat', function () {
    foreach ([new EkstrakTeksTautan('tautan-uji'), new BuatEksporLaporan('statistik', [], 'pengguna-uji')] as $job) {
        expect($job->connection)->toBe('database-berat')->and($job->queue)->toBe('berat');
    }

    $pengekspor = new KelengkapanDokumenExporter(new Export, [], []);

    expect($pengekspor->getJobConnection())->toBe('database-berat')
        ->and($pengekspor->getJobQueue())->toBe('berat');
});
