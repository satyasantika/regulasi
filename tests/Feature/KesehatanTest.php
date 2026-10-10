<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    config()->set('services.meilisearch.host', 'http://regulasi-meilisearch:7700');
});

it('mengembalikan 200 dan struktur json saat basis data dan redis sehat', function () {
    Http::fake(['*/health' => Http::response(['status' => 'available'])]);
    Redis::shouldReceive('connection->ping')->andReturn(true);
    config()->set('regulasi.versi', '9.9.9');

    $this->getJson(route('kesehatan'))
        ->assertOk()
        ->assertJsonStructure(['status', 'versi', 'waktu', 'layanan' => ['database', 'redis', 'pencarian']])
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('versi', '9.9.9')
        ->assertJsonPath('layanan.database', 'ok')
        ->assertJsonPath('layanan.redis', 'ok')
        ->assertJsonPath('layanan.pencarian', 'ok');
});

it('tetap 200 walau pencarian gagal', function () {
    Http::fake(['*/health' => Http::response([], 500)]);
    Redis::shouldReceive('connection->ping')->andReturn(true);

    $this->getJson('/api/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('layanan.pencarian', 'gagal');
});

it('mengembalikan 503 saat basis data gagal', function () {
    Http::fake(['*/health' => Http::response(['status' => 'available'])]);
    Redis::shouldReceive('connection->ping')->andReturn(true);
    DB::shouldReceive('select')->andThrow(new RuntimeException('DB mati'));

    $this->getJson('/api/health')
        ->assertStatus(503)
        ->assertJsonPath('status', 'terganggu')
        ->assertJsonPath('layanan.database', 'gagal');
});

it('mengembalikan 503 saat redis gagal', function () {
    Http::fake(['*/health' => Http::response(['status' => 'available'])]);
    Redis::shouldReceive('connection->ping')->andThrow(new RuntimeException('Redis mati'));

    $this->getJson('/api/health')
        ->assertStatus(503)
        ->assertJsonPath('layanan.redis', 'gagal');
});
