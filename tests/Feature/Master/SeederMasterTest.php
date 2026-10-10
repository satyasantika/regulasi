<?php

use App\Enums\KelompokJenisDokumen;
use App\Models\BadanPenetap;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\Tag;
use App\Support\CacheRegulasi;
use Database\Seeders\BadanPenetapSeeder;
use Database\Seeders\JenisDokumenSeeder;
use Database\Seeders\KategoriSistemSeeder;
use Database\Seeders\TagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function seedMaster(): void
{
    test()->seed([JenisDokumenSeeder::class, BadanPenetapSeeder::class, KategoriSistemSeeder::class, TagSeeder::class]);
}

test('seeder master mengisi jumlah baris sesuai skema', function () {
    seedMaster();

    expect(JenisDokumen::count())->toBe(18)
        ->and(BadanPenetap::count())->toBe(11)
        ->and(KategoriSistem::count())->toBe(11)
        ->and(Tag::count())->toBe(16);
});

test('seeder master idempoten', function () {
    seedMaster();
    $idAwal = JenisDokumen::where('kode', 'SOP')->value('id');

    seedMaster();

    expect(JenisDokumen::count())->toBe(18)
        ->and(BadanPenetap::count())->toBe(11)
        ->and(KategoriSistem::count())->toBe(11)
        ->and(Tag::count())->toBe(16)
        ->and(JenisDokumen::where('kode', 'SOP')->value('id'))->toBe($idAwal);
});

test('data seeder sesuai tabel skema', function () {
    seedMaster();

    $permen = JenisDokumen::where('kode', 'PERMEN')->firstOrFail();
    $sop = JenisDokumen::where('kode', 'SOP')->firstOrFail();
    $fkip = BadanPenetap::where('singkatan', 'FKIP Unsil')->with('induk')->firstOrFail();
    $aset = KategoriSistem::where('kode', 'aset')->firstOrFail();

    expect($permen->kelompok)->toBe(KelompokJenisDokumen::Peraturan)
        ->and($permen->wajib_nomor)->toBeTrue()
        ->and($permen->urutan)->toBe(50)
        ->and($sop->wajib_nomor)->toBeFalse()
        ->and(JenisDokumen::where('kode', 'PERLEMBAGA')->value('singkatan'))->toBeNull()
        ->and($fkip->teu)->toBe('Universitas Siliwangi. Fakultas Keguruan dan Ilmu Pendidikan')
        ->and($fkip->induk?->nama)->toBe('Universitas Siliwangi')
        ->and($aset->nama)->toBe('Aset/BMN')
        ->and($aset->ikon)->toBe('heroicon-o-building-office-2')
        ->and(KategoriSistem::where('kode', 'umum')->value('ikon'))->toBe('heroicon-o-scale');
});

test('cache master jenis dokumen terurut berdasarkan urutan dan hanya yang aktif', function () {
    seedMaster();
    JenisDokumen::where('kode', 'MANUAL')->firstOrFail()->update(['aktif' => false]);

    $daftar = CacheRegulasi::master('jenis-dokumen');

    expect($daftar)->toHaveCount(17)
        ->and($daftar->first()->kode)->toBe('UU')
        ->and($daftar->last()->kode)->toBe('LAINNYA')
        ->and($daftar->pluck('urutan')->all())->toBe($daftar->pluck('urutan')->sort()->values()->all())
        ->and($daftar->pluck('kode'))->not->toContain('MANUAL')
        ->and(Cache::has('master:jenis-dokumen'))->toBeTrue();
});

test('cache master dibersihkan setelah nama jenis diubah', function () {
    seedMaster();
    expect(CacheRegulasi::master('jenis-dokumen')->firstWhere('kode', 'SE')->nama)->toBe('Surat Edaran');

    JenisDokumen::where('kode', 'SE')->firstOrFail()->update(['nama' => 'Surat Edaran Pimpinan']);

    expect(Cache::has('master:jenis-dokumen'))->toBeFalse()
        ->and(CacheRegulasi::master('jenis-dokumen')->firstWhere('kode', 'SE')->nama)->toBe('Surat Edaran Pimpinan');
});

test('cache master dibersihkan saat kategori dihapus dan dipulihkan', function () {
    seedMaster();
    $aset = KategoriSistem::where('kode', 'aset')->firstOrFail();

    expect(CacheRegulasi::master('kategori-sistem'))->toHaveCount(11);

    $aset->delete();
    expect(CacheRegulasi::master('kategori-sistem'))->toHaveCount(10);

    $aset->restore();
    expect(CacheRegulasi::master('kategori-sistem'))->toHaveCount(11)
        ->and(CacheRegulasi::master('badan-penetap'))->toHaveCount(11);
});

test('nama master tidak dikenal ditolak', function () {
    CacheRegulasi::master('dokumen');
})->throws(InvalidArgumentException::class);
