<?php

use App\Models\LogBukaTautan;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Penjadwal (02-ARSITEKTUR §9), zona Asia/Jakarta, dijalankan service regulasi-scheduler (`schedule:work`).
| Semua tugas withoutOverlapping() + onOneServer() (kunci atomik di tabel cache_locks).
*/
$zona = 'Asia/Jakarta';

// BR-08: terapkan relasi yang tanggal efektifnya tiba.
Schedule::command('regulasi:terapkan-status')->dailyAt('00:15')->timezone($zona)->withoutOverlapping()->onOneServer();

// Sapu storage/app/tmp (ekstraksi, ekspor) yang berumur > 24 jam.
Schedule::command('berkas:bersihkan-tmp')->dailyAt('00:45')->timezone($zona)->withoutOverlapping()->onOneServer();

// Antrekan ulang ekstraksi gagal dengan percobaan < batas.
Schedule::command('regulasi:ekstraksi-ulang --gagal')->hourly()->timezone($zona)->withoutOverlapping()->onOneServer();

// BR-17: retensi log buka tautan (Prunable, config regulasi.retensi_log_buka_bulan = 24).
Schedule::command('model:prune', ['--model' => [LogBukaTautan::class]])->dailyAt('01:00')->timezone($zona)->withoutOverlapping()->onOneServer();

// Retensi log aktivitas 365 hari (config activitylog.clean_after_days).
Schedule::command('activitylog:clean --force')->dailyAt('01:30')->timezone($zona)->withoutOverlapping()->onOneServer();

// Pemeriksaan keteraksesan mingguan seluruh tautan dokumen terbit.
Schedule::command('berkas:periksa-tautan')->weeklyOn(1, '06:30')->timezone($zona)->withoutOverlapping()->onOneServer();

// US-NOT-03: ringkasan mingguan ke admin-regulasi.
Schedule::command('regulasi:ringkasan-mingguan')->weeklyOn(1, '07:30')->timezone($zona)->withoutOverlapping()->onOneServer();

// Kamus istilah pencarian dibangun ulang mingguan (02-ARSITEKTUR §5.1); juga dipicu saat dokumen terbit/diubah.
Schedule::command('regulasi:bangun-kamus')->weeklyOn(0, '02:00')->timezone($zona)->withoutOverlapping()->onOneServer();

// Profil Ringan (STANDAR-TEKNIS §1b): tanpa Horizon/Redis. Penjadwal menyalakan worker tiap menit dan
// berhenti saat antrean kosong; jeda proses job paling lama ±1 menit.
Schedule::command('queue:work database --queue=default --stop-when-empty --max-time=55 --timeout=60 --tries=3')
    ->everyMinute()->withoutOverlapping(2)->runInBackground();

Schedule::command('queue:work database-berat --queue=berat --stop-when-empty --max-time=600 --timeout=900 --tries=2 --memory=512')
    ->everyMinute()->withoutOverlapping(30)->runInBackground();

// Detak penjadwal: dibaca /api/health; peringatan bila lebih tua dari 5 menit.
Schedule::call(fn () => cache()->put(config('app.kode').':detak-penjadwal', now(), 600))
    ->everyMinute()->name('detak-penjadwal');

Schedule::command('queue:prune-failed --hours=720')->dailyAt('03:00');
Schedule::command('queue:prune-batches --hours=168')->dailyAt('03:05');
