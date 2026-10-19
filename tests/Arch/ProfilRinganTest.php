<?php

/**
 * Profil Ringan (STANDAR-TEKNIS §1b): tanpa Horizon, Redis, maupun Meilisearch; sesi, cache, antrean, dan lock di
 * database. Tes ini menjaga agar ketiganya tidak kembali tanpa ADR.
 */
dataset('contoh env', ['.env.example', '.env.production.example']);

it('memakai driver database dan Scout database pada contoh env', function (string $berkas) {
    expect(file_get_contents(dirname(__DIR__, 2).'/'.$berkas))
        ->toContain("\nSESSION_DRIVER=database\n")
        ->toContain("\nQUEUE_CONNECTION=database\n")
        ->toContain("\nCACHE_STORE=database\n")
        ->toContain("\nSCOUT_DRIVER=database\n")
        ->toContain("\nAPP_KODE=regulasi\n")
        ->not->toMatch('/^(REDIS_|HORIZON_|MEILISEARCH_)/m')
        ->not->toContain('_DRIVER=redis')
        ->not->toContain('QUEUE_CONNECTION=redis')
        ->not->toContain('CACHE_STORE=redis')
        ->not->toContain('SCOUT_DRIVER=meilisearch');
})->with('contoh env');

it('tidak memasang Horizon, klien Redis, maupun klien Meilisearch', function () {
    $composer = json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);
    $paket = array_merge($composer['require'], $composer['require-dev']);
    $kunci = file_get_contents(dirname(__DIR__, 2).'/composer.lock');

    foreach (['laravel/horizon', 'meilisearch/meilisearch-php', 'predis/predis'] as $dilarang) {
        expect($paket)->not->toHaveKey($dilarang)
            ->and($kunci)->not->toContain("\"name\": \"{$dilarang}\"");
    }

    expect(file_exists(dirname(__DIR__, 2).'/config/horizon.php'))->toBeFalse()
        ->and(file_exists(dirname(__DIR__, 2).'/app/Providers/HorizonServiceProvider.php'))->toBeFalse()
        ->and(file_get_contents(dirname(__DIR__, 2).'/bootstrap/providers.php'))->not->toContain('Horizon');
});

it('tidak merujuk Horizon atau Meilisearch di kode aplikasi, rute, dan konfigurasi', function () {
    foreach (['app', 'config', 'routes', 'bootstrap', 'database/seeders'] as $folder) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/'.$folder)) as $berkas) {
            if ($berkas->getExtension() !== 'php') {
                continue;
            }

            expect(file_get_contents($berkas->getPathname()))
                ->not->toContain('Laravel\\Horizon')
                ->not->toMatch('/horizon:|viewHorizon|Meilisearch\\\\|MEILISEARCH_/');
        }
    }
});
