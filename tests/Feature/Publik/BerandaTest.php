<?php

use App\Actions\Dokumen\TerbitkanDokumen;
use App\Enums\JenisTautan;
use App\Enums\KelompokJenisDokumen;
use App\Enums\PenyediaTautan;
use App\Enums\Peran;
use App\Enums\StatusPublikasi;
use App\Livewire\Publik\Beranda;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\TautanBerkas;
use App\Support\CacheRegulasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('beranda 200 untuk tamu dan memuat kotak pencarian serta statistik', function () {
    $jenis = JenisDokumen::factory()->create(['nama' => 'Peraturan Uji Beranda', 'slug' => 'peraturan-uji-beranda']);
    Dokumen::factory()->terbit()->count(2)->create(['jenis_dokumen_id' => $jenis->id]);

    $this->get(route('beranda'))
        ->assertOk()
        ->assertSee('lang="id"', false)
        ->assertSee('Lewati ke konten utama')
        ->assertSee('name="q"', false)
        ->assertSee('Peraturan Uji Beranda')
        ->assertSee(url('jenis/peraturan-uji-beranda'), false)
        ->assertSee('Versi '.config('regulasi.versi'));
});

test('statistik hanya menghitung dokumen terbit dan publik', function () {
    $jenis = JenisDokumen::factory()->create(['urutan' => 1]);
    $kosong = JenisDokumen::factory()->create(['urutan' => 2]);
    $kategori = KategoriSistem::factory()->create();

    $terbit = Dokumen::factory()->terbit()->count(2)->create(['jenis_dokumen_id' => $jenis->id, 'jumlah_dibuka' => 3]);
    $terbit->each(fn (Dokumen $d) => $d->kategoriSistem()->attach($kategori));
    Dokumen::factory()->create(['jenis_dokumen_id' => $jenis->id]);
    Dokumen::factory()->terbit()->internal()->create(['jenis_dokumen_id' => $jenis->id]);
    Dokumen::factory()->berstatus(StatusPublikasi::Diarsipkan)->create(['jenis_dokumen_id' => $kosong->id]);

    $statistik = CacheRegulasi::statistikBeranda();

    expect($statistik['total'])->toBe(2)
        ->and($statistik['total_dibuka'])->toBe(6)
        ->and($statistik['per_jenis'])->toHaveCount(1)
        ->and($statistik['per_jenis'][0])->toMatchArray(['kode' => $jenis->kode, 'jumlah' => 2])
        ->and($statistik['per_status']['berlaku'])->toBe(2)
        ->and(collect($statistik['per_kategori'])->firstWhere('kode', $kategori->kode)['jumlah'])->toBe(2);
});

test('cache beranda terisi lalu dibersihkan setelah dokumen diterbitkan', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = Dokumen::factory()->create([
        'jenis_dokumen_id' => JenisDokumen::factory()->create(['kelompok' => KelompokJenisDokumen::Peraturan]),
        'tanggal_penetapan' => '2025-01-15',
    ]);
    $dokumen->kategoriSistem()->attach(KategoriSistem::factory()->create());
    TautanBerkas::query()->create([
        'pemilik_type' => 'dokumen',
        'pemilik_id' => $dokumen->id,
        'jenis' => JenisTautan::NaskahUtama,
        'url' => 'https://drive.google.com/file/d/1BerandaUjiCacheAbcdefghijklmnop/view',
        'penyedia' => PenyediaTautan::GoogleDrive,
    ]);

    $this->get(route('beranda'))->assertOk()->assertDontSee($dokumen->sebutan_singkat);

    expect(Cache::has('beranda:statistik'))->toBeTrue()
        ->and(Cache::has('beranda:terbaru'))->toBeTrue()
        ->and(Cache::has('faset:tahun'))->toBeTrue()
        ->and(CacheRegulasi::statistikBeranda()['total'])->toBe(0);

    app(TerbitkanDokumen::class)->handle($dokumen, $admin);

    expect(Cache::has('beranda:statistik'))->toBeFalse()
        ->and(Cache::has('beranda:terbaru'))->toBeFalse()
        ->and(Cache::has('faset:tahun'))->toBeFalse();

    $this->get(route('beranda'))->assertOk()->assertSee($dokumen->sebutan_singkat);
    expect(CacheRegulasi::statistikBeranda()['total'])->toBe(1);
});

test('tamu tidak melihat dokumen internal di daftar terbaru, pengguna login melihatnya', function () {
    $publik = Dokumen::factory()->terbit()->create(['sebutan_singkat' => 'PUBLIK 1/2025']);
    $internal = Dokumen::factory()->terbit()->internal()->create(['sebutan_singkat' => 'INTERNAL 2/2025']);

    Livewire::test(Beranda::class)
        ->assertSee('PUBLIK 1/2025')
        ->assertDontSee('INTERNAL 2/2025');

    $this->actingAs(penggunaDenganPeran(Peran::Pengguna));

    Livewire::test(Beranda::class)
        ->assertSee('PUBLIK 1/2025')
        ->assertSee('INTERNAL 2/2025')
        ->assertSee('Internal');

    // Data publik ter-cache tidak tercemar dokumen internal.
    expect(collect(CacheRegulasi::terbaru())->pluck('id')->all())->toBe([$publik->id])
        ->and($internal->id)->not->toBeIn(collect(Cache::get('beranda:terbaru'))->pluck('id')->all());
});
