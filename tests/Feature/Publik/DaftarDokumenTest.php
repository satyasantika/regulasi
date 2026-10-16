<?php

use App\Enums\Peran;
use App\Enums\StatusBerlaku;
use App\Livewire\Publik\DaftarDokumen;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('pencarian:127.0.0.1');
});

/**
 * Dokumen terbit dalam kategori tertentu dengan status berlaku tertentu (diset langsung untuk keperluan uji).
 *
 * @param  array<string, mixed>  $atribut
 */
function dokumenDaftar(KategoriSistem $kategori, StatusBerlaku $status = StatusBerlaku::Berlaku, array $atribut = []): Dokumen
{
    $dokumen = Dokumen::factory()->terbit()->create($atribut);
    $dokumen->kategoriSistem()->attach($kategori);
    DB::table('dokumen')->where('id', $dokumen->id)->update(['status_berlaku' => $status->value]);

    return $dokumen->refresh();
}

test('filter kategori aset dan status berlaku hanya menampilkan dokumen yang cocok beserta jumlah faset', function () {
    $aset = KategoriSistem::factory()->create(['kode' => 'aset', 'nama' => 'Aset/BMN', 'slug' => 'aset']);
    $sdm = KategoriSistem::factory()->create(['kode' => 'sdm', 'nama' => 'SDM', 'slug' => 'sdm']);

    $cocok = dokumenDaftar($aset, StatusBerlaku::Berlaku, ['sebutan_singkat' => 'ASET BERLAKU 1/2024']);
    $dicabut = dokumenDaftar($aset, StatusBerlaku::Dicabut, ['sebutan_singkat' => 'ASET DICABUT 2/2020']);
    $lain = dokumenDaftar($sdm, StatusBerlaku::Berlaku, ['sebutan_singkat' => 'SDM BERLAKU 3/2024']);

    $uji = Livewire::withQueryParams(['kategori' => ['aset'], 'status' => ['berlaku']])
        ->test(DaftarDokumen::class)
        ->assertSee('ASET BERLAKU 1/2024')
        ->assertDontSee('ASET DICABUT 2/2020')
        ->assertDontSee('SDM BERLAKU 3/2024')
        ->assertSee('1 dokumen')
        ->assertSee('Kategori: Aset/BMN')
        ->assertSee('Status: Berlaku');

    $faset = $uji->viewData('faset');

    // Faset disjungtif: jumlah kategori memakai filter status saja, jumlah status memakai filter kategori saja.
    expect($faset['kategori']['opsi']['aset']['jumlah'])->toBe(1)
        ->and($faset['kategori']['opsi']['sdm']['jumlah'])->toBe(1)
        ->and($faset['status_berlaku']['opsi']['berlaku']['jumlah'])->toBe(1)
        ->and($faset['status_berlaku']['opsi']['dicabut']['jumlah'])->toBe(1)
        ->and($uji->viewData('hasil')->mesin)->toBe('database');

    $uji->assertDontSee('Pencarian sederhana sedang dipakai')->assertDontSee('Menampilkan hasil untuk');
});

test('rute jenis peraturan menteri hanya menampilkan PERMEN dengan breadcrumb dan judul yang benar', function () {
    $permen = JenisDokumen::factory()->create(['kode' => 'PERMEN', 'nama' => 'Peraturan Menteri', 'slug' => 'peraturan-menteri']);
    $pp = JenisDokumen::factory()->create(['kode' => 'PP', 'nama' => 'Peraturan Pemerintah', 'slug' => 'peraturan-pemerintah']);

    Dokumen::factory()->terbit()->create(['jenis_dokumen_id' => $permen->id, 'sebutan_singkat' => 'PERMENDIKBUD 3/2021']);
    Dokumen::factory()->terbit()->create(['jenis_dokumen_id' => $pp->id, 'sebutan_singkat' => 'PP 27/2014']);

    $html = $this->get(route('dokumen.jenis', 'peraturan-menteri'))
        ->assertOk()
        ->assertSee('PERMENDIKBUD 3/2021')
        ->assertDontSee('PP 27/2014')
        ->assertSee('<title>Peraturan Menteri — Regulasi FKIP Unsil</title>', false)
        ->getContent();

    expect($html)->toMatch('#<nav aria-label="Breadcrumb">.*Beranda.*›.*aria-current="page"[^>]*>Peraturan Menteri</span>#s');

    // Jenis terkunci: faset jenis disembunyikan dan parameter jenis dari URL diabaikan.
    Livewire::withQueryParams(['jenis' => ['PP']])
        ->test(DaftarDokumen::class, ['jenisDokumen' => $permen])
        ->assertSee('PERMENDIKBUD 3/2021')
        ->assertDontSee('PP 27/2014')
        ->assertViewHas('faset', fn (array $faset): bool => ! array_key_exists('jenis', $faset));

    $this->get('/jenis/tidak-ada')->assertNotFound();
});

test('rute kategori memakai breadcrumb Beranda › Kategori › nama', function () {
    $aset = KategoriSistem::factory()->create(['kode' => 'aset', 'nama' => 'Aset/BMN', 'slug' => 'aset']);
    dokumenDaftar($aset, atribut: ['sebutan_singkat' => 'PMK 181/PMK.06/2016']);

    $html = $this->get(route('dokumen.kategori', 'aset'))
        ->assertOk()
        ->assertSee('PMK 181/PMK.06/2016')
        ->assertSee('<title>Kategori Aset/BMN — Regulasi FKIP Unsil</title>', false)
        ->getContent();

    expect($html)->toMatch('#Beranda</a>.*›.*Kategori</span>.*›.*aria-current="page"[^>]*>Aset/BMN</span>#s');
});

test('parameter URL dipulihkan saat halaman dibuka langsung dan filter dapat dihapus', function () {
    $aset = KategoriSistem::factory()->create(['kode' => 'aset', 'nama' => 'Aset/BMN', 'slug' => 'aset']);
    dokumenDaftar($aset, atribut: ['tahun' => 2016, 'nomor' => '181/PMK.06/2016', 'sebutan_singkat' => 'PMK 181/PMK.06/2016']);
    dokumenDaftar($aset, atribut: ['tahun' => 2014, 'sebutan_singkat' => 'PP 27/2014']);

    $this->get(route('dokumen.index').'?tahun[]=2016&urut=tahun_desc')
        ->assertOk()
        ->assertSee('PMK 181/PMK.06/2016')
        ->assertDontSee('PP 27/2014')
        ->assertSee('Tahun: 2016');

    Livewire::withQueryParams(['tahun' => ['2016'], 'urut' => 'tahun_desc', 'q' => '181'])
        ->test(DaftarDokumen::class)
        ->assertSet('tahun', ['2016'])
        ->assertSet('urut', 'tahun_desc')
        ->assertSet('q', '181')
        ->assertSee('PMK 181/PMK.06/2016')
        ->call('hapusFilter', 'tahun', '2016')
        ->assertSet('tahun', [])
        ->call('hapusSemuaFilter')
        ->assertSet('q', '')
        ->assertSee('PP 27/2014');
});

test('nilai filter dan urutan tidak sah dibersihkan', function () {
    Livewire::withQueryParams(['urut' => 'drop table', 'jenis' => ['', str_repeat('x', 150)]])
        ->test(DaftarDokumen::class)
        ->assertSet('urut', 'relevan')
        ->assertSet('jenis', []);
});

test('tamu tidak melihat dokumen internal, pengguna login melihatnya', function () {
    Dokumen::factory()->terbit()->create(['sebutan_singkat' => 'PUBLIK 10/2025']);
    Dokumen::factory()->terbit()->internal()->create(['sebutan_singkat' => 'INTERNAL 11/2025']);
    Dokumen::factory()->create(['sebutan_singkat' => 'DRAF 12/2025']);

    Livewire::test(DaftarDokumen::class)
        ->assertSee('PUBLIK 10/2025')
        ->assertDontSee('INTERNAL 11/2025')
        ->assertDontSee('DRAF 12/2025');

    $this->actingAs(penggunaDenganPeran(Peran::Pengguna));

    Livewire::test(DaftarDokumen::class)
        ->assertSee('PUBLIK 10/2025')
        ->assertSee('INTERNAL 11/2025')
        ->assertDontSee('DRAF 12/2025');
});

test('lebih dari 60 pembaruan per menit dari satu IP tidak memanggil mesin pencarian', function () {
    Dokumen::factory()->terbit()->create(['sebutan_singkat' => 'PUBLIK 20/2025']);

    for ($i = 0; $i < 60; $i++) {
        RateLimiter::hit('pencarian:127.0.0.1');
    }

    Livewire::test(DaftarDokumen::class)
        ->assertSee('Terlalu banyak permintaan pencarian')
        ->assertDontSee('PUBLIK 20/2025');
});
