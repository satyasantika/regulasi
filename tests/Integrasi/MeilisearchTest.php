<?php

use App\Enums\StatusPublikasi;
use App\Enums\Visibilitas;
use App\Models\Dokumen;
use App\Models\KategoriSistem;
use App\Services\Pencarian\KriteriaPencarian;
use App\Services\Pencarian\PencarianMeilisearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Meilisearch\Client;

uses(RefreshDatabase::class);

/**
 * Uji integrasi Meilisearch nyata (US-CARI-02). Di-skip bila MEILISEARCH_HOST tidak dapat dihubungi (mis. CI).
 * Memakai indeks berawalan acak agar indeks pengembangan tidak tersentuh, lalu menghapusnya.
 */
function meilisearchTerjangkau(): bool
{
    $host = (string) config('scout.meilisearch.host');
    $bagian = parse_url($host);
    $soket = @fsockopen($bagian['host'] ?? 'localhost', (int) ($bagian['port'] ?? 7700), $kode, $pesan, 1);

    if ($soket === false) {
        return false;
    }

    fclose($soket);

    return true;
}

test('pencarian salah ketik "permendikbudrstek" menemukan Permendikbudristek 53/2023', function () {
    config([
        'scout.driver' => 'meilisearch',
        'scout.prefix' => 'regulasi_uji_'.bin2hex(random_bytes(3)).'_',
        'scout.queue' => false,
    ]);
    $klien = app(Client::class);
    $indeks = (new Dokumen)->searchableAs();

    try {
        Artisan::call('scout:sync-index-settings');

        $dokumen = Dokumen::factory()->create([
            'nomor' => '53',
            'tahun' => 2023,
            'judul' => 'Penjaminan Mutu Pendidikan Tinggi',
            'sebutan_singkat' => 'Permendikbudristek 53/2023',
            'status_publikasi' => StatusPublikasi::Terbit,
        ]);
        Dokumen::factory()->create(['judul' => 'Tata Naskah Dinas', 'status_publikasi' => StatusPublikasi::Terbit])->searchable();
        $dokumen->searchable();

        $ditemukan = [];

        for ($i = 0; $i < 40 && $ditemukan === []; $i++) {
            usleep(250_000);
            $ditemukan = Dokumen::search('permendikbudrstek 53')->keys()->all();
        }

        expect($ditemukan)->toContain($dokumen->getKey());
        expect($klien->index($indeks)->getSynonyms())->toHaveKey('tnd');
    } finally {
        $klien->deleteIndex($indeks);
    }
})->group('meilisearch')->skip(fn (): bool => ! meilisearchTerjangkau(), 'Meilisearch tidak dapat dihubungi');

test('PencarianMeilisearch menerapkan filter kategori, visibilitas, dan faset disjungtif', function () {
    config([
        'scout.driver' => 'meilisearch',
        'scout.prefix' => 'regulasi_uji_'.bin2hex(random_bytes(3)).'_',
        'scout.queue' => false,
    ]);
    $klien = app(Client::class);
    $indeks = (new Dokumen)->searchableAs();

    try {
        Artisan::call('scout:sync-index-settings');

        $aset = KategoriSistem::factory()->create(['kode' => 'aset']);
        $keuangan = KategoriSistem::factory()->create(['kode' => 'keuangan']);
        $buat = function (array $atribut, KategoriSistem $kategori): Dokumen {
            $dokumen = Dokumen::factory()->create(array_merge(['status_publikasi' => StatusPublikasi::Terbit, 'dipublikasikan_pada' => now()], $atribut));
            $dokumen->kategoriSistem()->attach($kategori);

            return $dokumen;
        };
        $cocok = $buat(['judul' => 'Penatausahaan barang milik negara', 'tahun' => 2016], $aset);
        $buat(['judul' => 'Barang milik negara internal', 'tahun' => 2020, 'visibilitas' => Visibilitas::Internal], $aset);
        $buat(['judul' => 'Barang milik negara pada satuan kerja keuangan', 'tahun' => 2018], $keuangan);
        Dokumen::query()->get()->each->searchable();

        $hasil = null;

        for ($i = 0; $i < 40 && ($hasil === null || $hasil->dokumen->total() === 0); $i++) {
            usleep(250_000);
            $hasil = app(PencarianMeilisearch::class)->cari(KriteriaPencarian::dari(['q' => 'bmn', 'kategori' => ['aset']]));
        }

        expect($hasil->mesin)->toBe('meilisearch')
            ->and($hasil->dokumen->getCollection()->map->getKey()->all())->toBe([$cocok->getKey()])
            ->and($hasil->faset['kategori'])->toBe(['aset' => 1, 'keuangan' => 1])
            ->and($hasil->faset['tahun'])->toBe(['2016' => 1]);
    } finally {
        $klien->deleteIndex($indeks);
    }
})->group('meilisearch')->skip(fn (): bool => ! meilisearchTerjangkau(), 'Meilisearch tidak dapat dihubungi');
