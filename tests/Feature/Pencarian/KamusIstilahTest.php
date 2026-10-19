<?php

use App\Jobs\BangunKamusIstilah;
use App\Models\BadanPenetap;
use App\Models\Dokumen;
use App\Models\IstilahPencarian;
use App\Models\JenisDokumen;
use App\Models\SinonimPencarian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('regulasi:bangun-kamus mengisi istilah dari judul, singkatan, jenis, T.E.U., dan sinonim', function () {
    JenisDokumen::factory()->create(['kode' => 'PERMEN', 'nama' => 'Peraturan Menteri']);
    BadanPenetap::factory()->create(['nama' => 'Kementerian Keuangan', 'singkatan' => 'Kemenkeu']);
    Dokumen::factory()->terbit()->create(['judul' => 'Penatausahaan Barang Milik Negara', 'sebutan_singkat' => 'PMK 181/PMK.06/2016']);
    Dokumen::factory()->create(['judul' => 'Rancangan Rahasia Draf', 'sebutan_singkat' => 'DRAF 1']);

    $this->artisan('regulasi:bangun-kamus')->assertSuccessful();

    $kamus = IstilahPencarian::query()->pluck('sumber', 'istilah');

    expect($kamus['penatausahaan'])->toBe('judul')
        ->and($kamus['pmk'])->toBe('singkatan')
        ->and($kamus['menteri'])->toBe('jenis')
        ->and($kamus['kemenkeu'])->toBe('teu')
        ->and($kamus['permendikbudristek'])->toBe('sinonim')
        ->and($kamus)->not->toHaveKey('rahasia')
        ->and(SinonimPencarian::query()->where('istilah', 'uu')->where('sinonim', 'undang-undang')->exists())->toBeTrue()
        ->and(SinonimPencarian::query()->where('istilah', 'undang-undang')->where('sinonim', 'uu')->exists())->toBeTrue();
});

test('bangun-kamus idempoten, menghitung frekuensi, dan menghapus istilah usang', function () {
    $dokumen = Dokumen::factory()->terbit()->create(['judul' => 'Pedoman pedoman akreditasi', 'sebutan_singkat' => 'PED 1/2024']);
    IstilahPencarian::query()->create(['istilah' => 'usang', 'sumber' => 'judul', 'frekuensi' => 3]);

    $this->artisan('regulasi:bangun-kamus')->assertSuccessful();
    $pertama = IstilahPencarian::query()->count();
    $this->artisan('regulasi:bangun-kamus')->assertSuccessful();

    expect(IstilahPencarian::query()->count())->toBe($pertama)
        ->and(IstilahPencarian::query()->where('istilah', 'usang')->exists())->toBeFalse()
        ->and(IstilahPencarian::query()->where('istilah', 'pedoman')->value('frekuensi'))->toBe(2)
        ->and(SinonimPencarian::query()->count())->toBe(SinonimPencarian::query()->distinct()->count(['istilah', 'sinonim']));

    $dokumen->update(['judul' => 'Standar mutu']);
    $this->artisan('regulasi:bangun-kamus')->assertSuccessful();

    expect(IstilahPencarian::query()->where('istilah', 'pedoman')->exists())->toBeFalse()
        ->and(IstilahPencarian::query()->where('istilah', 'mutu')->exists())->toBeTrue();
});

test('bangun-kamus membersihkan cache kamus', function () {
    Cache::put('pencarian:kamus', ['lama' => 1], 600);
    Cache::put('pencarian:sinonim', ['lama' => ['x']], 600);

    $this->artisan('regulasi:bangun-kamus')->assertSuccessful();

    expect(Cache::has('pencarian:kamus'))->toBeFalse()
        ->and(Cache::has('pencarian:sinonim'))->toBeFalse();
});

test('opsi --antre menaruh job di antrean berat tanpa membangun sekarang', function () {
    Queue::fake();

    $this->artisan('regulasi:bangun-kamus', ['--antre' => true])->assertSuccessful();

    Queue::assertPushedOn('berat', BangunKamusIstilah::class);
    expect(IstilahPencarian::query()->count())->toBe(0);
});

test('job BangunKamusIstilah membangun kamus saat dijalankan', function () {
    Dokumen::factory()->terbit()->create(['judul' => 'Tata naskah dinas']);

    app()->call([new BangunKamusIstilah, 'handle']);

    expect(IstilahPencarian::query()->where('istilah', 'naskah')->exists())->toBeTrue();
});
