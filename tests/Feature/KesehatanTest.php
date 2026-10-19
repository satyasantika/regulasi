<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('mengembalikan 200 dan struktur json saat basis data sehat', function () {
    config()->set('regulasi.versi', '9.9.9');

    $this->getJson(route('kesehatan'))
        ->assertOk()
        ->assertJsonStructure(['status', 'versi', 'waktu', 'layanan' => ['database', 'job_tertunda', 'job_gagal_24j', 'detak_penjadwal']])
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('versi', '9.9.9')
        ->assertJsonPath('layanan.database', 'ok')
        ->assertJsonPath('layanan.job_tertunda', 0)
        ->assertJsonPath('layanan.job_gagal_24j', 0)
        ->assertJsonPath('layanan.detak_penjadwal', 'belum_ada')
        ->assertJsonMissingPath('layanan.redis')
        ->assertJsonMissingPath('layanan.pencarian');
});

it('mengembalikan 503 saat basis data gagal', function () {
    DB::shouldReceive('select')->andThrow(new RuntimeException('DB mati'));

    $this->getJson('/api/health')
        ->assertStatus(503)
        ->assertJsonPath('status', 'terganggu')
        ->assertJsonPath('layanan.database', 'gagal');
});

it('melaporkan job tertunda, job gagal 24 jam, dan detak penjadwal tanpa menurunkan status', function () {

    $job = ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'created_at' => now()->getTimestamp()];
    DB::table('jobs')->insert([...$job, 'available_at' => now()->subMinutes(30)->getTimestamp()]);
    DB::table('jobs')->insert([...$job, 'available_at' => now()->getTimestamp()]);
    DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);
    DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()->subDays(2)]);
    Cache::put(config('app.kode').':detak-penjadwal', now()->subMinutes(20)->getTimestamp(), 600);

    $this->getJson('/api/health')
        ->assertOk()
        ->assertJsonPath('layanan.job_tertunda', 1)
        ->assertJsonPath('layanan.job_gagal_24j', 1)
        ->assertJsonPath('layanan.detak_penjadwal', 'terlambat');

    Cache::put(config('app.kode').':detak-penjadwal', now()->getTimestamp(), 600);

    $this->getJson('/api/health')->assertJsonPath('layanan.detak_penjadwal', 'ok');
});
