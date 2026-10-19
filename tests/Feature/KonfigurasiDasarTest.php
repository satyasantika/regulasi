<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

test('zona waktu aplikasi adalah Asia/Jakarta', function () {
    expect(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(date_default_timezone_get())->toBe('Asia/Jakarta');
});

test('locale aplikasi adalah id dan tanggal berbahasa Indonesia', function () {
    expect(config('app.locale'))->toBe('id')
        ->and(Date::create(2026, 10, 6)->translatedFormat('d F Y'))->toBe('06 Oktober 2026');
});

test('tanggal memakai CarbonImmutable', function () {
    expect(now())->toBeInstanceOf(CarbonImmutable::class);
});

test('lazy loading dicegah di luar produksi', function () {
    expect(Model::preventsLazyLoading())->toBeTrue()
        ->and(Model::preventsSilentlyDiscardingAttributes())->toBeTrue();
});

test('konfigurasi regulasi tersedia tanpa pengaturan unggah berkas', function () {
    expect(config('regulasi.potong_isi_teks_kata'))->toBe(60000)
        ->and(config('regulasi.retensi_log_buka_bulan'))->toBe(24)
        ->and(config('regulasi.domain_registrasi'))->toBe('unsil.ac.id')
        ->and(config('regulasi.sinonim.pmk'))->toBe(['peraturan menteri keuangan'])
        ->and(config('regulasi.ukuran_maks_kb'))->toBeNull();
});

test('uji berjalan di basis data db_regulasi_testing', function () {
    expect(config('database.default'))->toBe('mariadb')
        ->and(config('database.connections.mariadb.database'))->toBe('db_regulasi_testing');
});

test('antrean bawaan memakai database (Profil Ringan)', function () {
    expect(config('queue.connections'))->toHaveKeys(['database', 'database-berat'])
        ->and(file_get_contents(config_path('queue.php')))->not->toContain("'redis' =>") // kerangka kerja tetap menggabungkan bawaannya
        ->and(config('queue.default'))->not->toBe('redis')
        ->and(config('scout.driver'))->toBe('database')
        ->and(file_get_contents(config_path('scout.php')))->not->toContain("'meilisearch' =>");
});
