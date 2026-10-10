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
| Semua tugas withoutOverlapping() + onOneServer() (kunci atomik di Redis).
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

// Metrik Horizon.
Schedule::command('horizon:snapshot')->everyFiveMinutes()->timezone($zona)->withoutOverlapping()->onOneServer();
