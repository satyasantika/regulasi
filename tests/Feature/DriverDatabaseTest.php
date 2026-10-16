<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Gerbang Profil Ringan: phpunit.xml memakai driver `array`/`sync`, sehingga perilaku lock, cache, dan antrean di
 * database (produksi) diuji di sini dengan driver SUNGGUHAN.
 */
it('mengunci dan melepas Cache::lock di atas cache database (tabel cache_locks)', function () {
    config(['cache.default' => 'database']);
    Cache::purge('database');

    $pertama = Cache::lock('lock:uji-profil-ringan', 10);
    $kedua = Cache::lock('lock:uji-profil-ringan', 10);

    expect($pertama->get())->toBeTrue()
        ->and($kedua->get())->toBeFalse()
        ->and(DB::table('cache_locks')->count())->toBe(1);

    $pertama->release();

    expect($kedua->get())->toBeTrue();
    $kedua->release();
    expect(DB::table('cache_locks')->count())->toBe(0);
});

it('menyimpan dan membaca cache database dengan TTL', function () {
    config(['cache.default' => 'database']);
    Cache::purge('database');

    Cache::put('uji', ['a' => 1], 60);

    expect(Cache::get('uji'))->toBe(['a' => 1])
        ->and(DB::table('cache')->count())->toBe(1);
});

it('menaruh job ke tabel jobs pada antrean default dan berat memakai driver database', function () {
    $payload = json_encode(['job' => 'x', 'data' => []]);

    Queue::connection('database')->pushRaw($payload, 'default');
    Queue::connection('database-berat')->pushRaw($payload);

    expect(DB::table('jobs')->where('queue', 'default')->count())->toBe(1)
        ->and(DB::table('jobs')->where('queue', 'berat')->count())->toBe(1);
});
