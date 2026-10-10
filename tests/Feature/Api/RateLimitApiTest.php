<?php

use App\Models\Dokumen;
use App\Models\KlienApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

function tokenBatas(): string
{
    return KlienApi::factory()->create()->createToken('uji', ['dokumen:baca'])->plainTextToken;
}

function getBatas(string $url, string $token, array $header = []): TestResponse
{
    app('auth')->forgetGuards();

    return test()->getJson($url, ['Authorization' => 'Bearer '.$token, ...$header]);
}

test('permintaan ke-61 dalam semenit → 429 JSON:API dengan Retry-After; token lain punya kuota sendiri', function () {
    $this->freezeSecond();
    $tokenA = tokenBatas();
    $tokenB = tokenBatas();

    for ($i = 1; $i <= 60; $i++) {
        $respons = getBatas('/api/v1/jenis-dokumen', $tokenA)->assertOk();
    }

    $respons->assertHeader('X-RateLimit-Limit', '60')->assertHeader('X-RateLimit-Remaining', '0');

    getBatas('/api/v1/jenis-dokumen', $tokenA)
        ->assertStatus(429)
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertHeader('Retry-After', '60')
        ->assertHeader('X-RateLimit-Limit', '60')
        ->assertHeader('X-RateLimit-Remaining', '0')
        ->assertExactJson(['errors' => [['status' => '429', 'title' => 'Terlalu banyak permintaan']]]);

    getBatas('/api/v1/jenis-dokumen', $tokenB)->assertOk()->assertHeader('X-RateLimit-Remaining', '59');

    // Setelah semenit kuota pulih.
    $this->travel(61)->seconds();
    getBatas('/api/v1/jenis-dokumen', $tokenA)->assertOk();
});

test('ETag & Last-Modified pada detail dokumen; If-None-Match sama → 304 tanpa isi', function () {
    $dokumen = Dokumen::factory()->terbit()->create();
    $token = tokenBatas();

    $pertama = getBatas("/api/v1/dokumen/{$dokumen->id}", $token)
        ->assertOk()
        ->assertHeader('Last-Modified')
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.id', $dokumen->id);

    $etag = $pertama->headers->get('ETag');
    expect($etag)->toStartWith('W/"');

    $tidakBerubah = getBatas("/api/v1/dokumen/{$dokumen->id}", $token, ['If-None-Match' => $etag])->assertStatus(304);
    expect($tidakBerubah->getContent())->toBe('');

    // include berbeda → ETag berbeda.
    $denganInclude = getBatas("/api/v1/dokumen/{$dokumen->id}?include=kategori", $token, ['If-None-Match' => $etag])->assertOk();
    expect($denganInclude->headers->get('ETag'))->not->toBe($etag);

    // Dokumen berubah → ETag lama tidak cocok.
    $this->travel(5)->seconds();
    $dokumen->touch();
    getBatas("/api/v1/dokumen/{$dokumen->id}", $token, ['If-None-Match' => $etag])->assertOk();
});
