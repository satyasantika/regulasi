<?php

use App\Services\Pencarian\PencarianDatabase;
use App\Services\Pencarian\PencarianDokumen;
use App\Services\Pencarian\PencarianMeilisearch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config(['scout.driver' => 'meilisearch', 'scout.meilisearch.host' => 'http://regulasi-meilisearch:7700']);
    Cache::forget('pencarian:sehat');
});

test('Meilisearch tidak sehat → resolver memakai PencarianDatabase dan mencatat peringatan', function () {
    Log::spy();
    Http::fake(['*/health' => Http::response(['message' => 'error'], 500)]);

    expect(app(PencarianDokumen::class))->toBeInstanceOf(PencarianDatabase::class);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $pesan): bool => str_contains($pesan, 'cadangan database'))->once();
});

test('Meilisearch tidak terjangkau → PencarianDatabase', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    expect(app(PencarianDokumen::class))->toBeInstanceOf(PencarianDatabase::class);
});

test('Meilisearch sehat → PencarianMeilisearch, hasil cek di-cache', function () {
    Http::fake(['*/health' => Http::response(['status' => 'available'])]);

    expect(app(PencarianDokumen::class))->toBeInstanceOf(PencarianMeilisearch::class)
        ->and(app(PencarianDokumen::class))->toBeInstanceOf(PencarianMeilisearch::class);

    Http::assertSentCount(1);
});

test('driver selain meilisearch selalu memakai database tanpa cek kesehatan', function () {
    config(['scout.driver' => 'collection']);
    Http::fake();

    expect(app(PencarianDokumen::class))->toBeInstanceOf(PencarianDatabase::class);
    Http::assertNothingSent();
});
