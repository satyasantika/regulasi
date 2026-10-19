<?php

use App\Enums\StatusBerlaku;
use App\Enums\StatusEkstraksi;
use App\Enums\StatusPublikasi;
use App\Enums\Visibilitas;
use App\Livewire\Publik\DaftarDokumen;
use App\Models\BadanPenetap;
use App\Models\Dokumen;
use App\Models\EkstraksiTeks;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\TautanBerkas;
use App\Services\Pencarian\HasilPencarian;
use App\Services\Pencarian\KoreksiIstilah;
use App\Services\Pencarian\KriteriaPencarian;
use App\Services\Pencarian\PencarianDatabase;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;

/*
 * FULLTEXT InnoDB hanya melihat baris yang sudah di-commit, sehingga berkas ini memakai DatabaseTruncation
 * (bukan RefreshDatabase yang membungkus uji dalam transaksi).
 */
uses(DatabaseTruncation::class);

// Bersihkan juga sesudah uji agar baris ter-commit tidak bocor ke berkas uji lain (RefreshDatabase).
afterEach(function () {
    $this->truncateTablesForAllConnections();
});

beforeEach(function () {
    $this->aset = KategoriSistem::factory()->create(['kode' => 'aset']);
    $this->keuangan = KategoriSistem::factory()->create(['kode' => 'keuangan']);
});

/**
 * @param  array<string, mixed>  $atribut
 */
function dokumenCari(array $atribut = [], ?KategoriSistem $kategori = null, ?string $isiPdf = null): Dokumen
{
    $dokumen = Dokumen::factory()->create(array_merge([
        'status_publikasi' => StatusPublikasi::Terbit,
        'dipublikasikan_pada' => now(),
        'judul' => 'Ketentuan umum pengelolaan',
    ], $atribut));

    if ($kategori !== null) {
        $dokumen->kategoriSistem()->attach($kategori);
    }

    if ($isiPdf !== null) {
        $tautan = TautanBerkas::query()->create([
            'pemilik_type' => 'dokumen',
            'pemilik_id' => $dokumen->getKey(),
            'jenis' => 'naskah_utama',
            'url' => 'https://lppm.unsil.ac.id/'.fake()->unique()->uuid().'.pdf',
            'penyedia' => 'unsil',
        ]);
        EkstraksiTeks::query()->create(['tautan_berkas_id' => $tautan->getKey(), 'status' => StatusEkstraksi::Berhasil, 'isi_teks' => $isiPdf]);
    }

    return $dokumen;
}

function cariDb(array $data, bool $internal = false): HasilPencarian
{
    return app(PencarianDatabase::class)->cari(KriteriaPencarian::dari($data, $internal));
}

/**
 * @return list<string>
 */
function idHasil(HasilPencarian $hasil): array
{
    return $hasil->dokumen->getCollection()->map->getKey()->all();
}

test('kata yang hanya ada di isi PDF ditemukan', function () {
    $pedoman = dokumenCari(['judul' => 'Pedoman persuratan fakultas'], isiPdf: 'Bab I. Tata naskah dinas di lingkungan FKIP mengatur bentuk surat.');
    dokumenCari(['judul' => 'Pengelolaan barang milik negara']);

    $hasil = cariDb(['q' => 'tata naskah dinas']);

    expect(idHasil($hasil))->toBe([$pedoman->getKey()])
        ->and($hasil->mesin)->toBe('database');
});

test('filter kategori aset dan status berlaku hanya mengembalikan dokumen yang sesuai', function () {
    $cocok = dokumenCari([], $this->aset);
    dokumenCari(['status_berlaku' => StatusBerlaku::Dicabut], $this->aset);
    dokumenCari([], $this->keuangan);

    $hasil = cariDb(['kategori' => ['aset'], 'status' => ['berlaku']]);

    expect(idHasil($hasil))->toBe([$cocok->getKey()])
        // Faset disjungtif: dimensi kategori tidak dibatasi filter kategorinya sendiri.
        ->and($hasil->faset['kategori'])->toBe(['aset' => 1, 'keuangan' => 1])
        ->and($hasil->faset['status_berlaku'])->toBe(['berlaku' => 1, 'dicabut' => 1]);
});

test('faset tahun, jenis, dan badan menghitung benar', function () {
    $jenis = JenisDokumen::factory()->create(['kode' => 'PERMEN']);
    $badan = BadanPenetap::factory()->create();
    dokumenCari(['tahun' => 2023, 'jenis_dokumen_id' => $jenis->getKey(), 'badan_penetap_id' => $badan->getKey()]);
    dokumenCari(['tahun' => 2023, 'jenis_dokumen_id' => $jenis->getKey(), 'badan_penetap_id' => $badan->getKey()]);
    dokumenCari(['tahun' => 2024]);
    dokumenCari(['tahun' => 2025, 'status_publikasi' => StatusPublikasi::Draf]);

    $hasil = cariDb([]);

    expect($hasil->faset['tahun'])->toBe(['2024' => 1, '2023' => 2])
        ->and($hasil->faset['jenis']['PERMEN'])->toBe(2)
        ->and($hasil->faset['badan_penetap'][$badan->slug])->toBe(2)
        ->and($hasil->dokumen->total())->toBe(3);
});

test('dokumen internal hanya muncul bila sertakanInternal', function () {
    $publik = dokumenCari(['judul' => 'Pedoman akreditasi program studi']);
    $internal = dokumenCari(['judul' => 'Pedoman akreditasi internal', 'visibilitas' => Visibilitas::Internal]);

    expect(idHasil(cariDb(['q' => 'akreditasi'])))->toBe([$publik->getKey()])
        ->and(idHasil(cariDb(['q' => 'akreditasi'], true)))->toEqualCanonicalizing([$publik->getKey(), $internal->getKey()]);
});

test('draf, ditolak, dan diarsipkan tidak pernah muncul', function () {
    foreach ([StatusPublikasi::Draf, StatusPublikasi::MenungguVerifikasi, StatusPublikasi::Ditolak, StatusPublikasi::Diarsipkan] as $status) {
        dokumenCari(['judul' => 'Rahasia akreditasi', 'status_publikasi' => $status], isiPdf: 'akreditasi rahasia');
    }

    expect(cariDb(['q' => 'akreditasi'], true)->dokumen->total())->toBe(0)
        ->and(cariDb([], true)->dokumen->total())->toBe(0);
});

test('pencarian nomor 181/PMK menemukan PMK 181/PMK.06/2016', function () {
    $pmk = dokumenCari(['nomor' => '181/PMK.06/2016', 'tahun' => 2016, 'sebutan_singkat' => 'PMK 181/PMK.06/2016', 'judul' => 'Penatausahaan Barang Milik Negara']);
    dokumenCari(['nomor' => '18', 'tahun' => 2016, 'sebutan_singkat' => 'PMK 18/2016']);

    expect(idHasil(cariDb(['q' => '181/PMK'])))->toBe([$pmk->getKey()]);
});

test('urutan tahun dan pembatasan per halaman', function () {
    $lama = dokumenCari(['tahun' => 2010]);
    $baru = dokumenCari(['tahun' => 2025]);

    expect(idHasil(cariDb(['urut' => 'tahun_asc'])))->toBe([$lama->getKey(), $baru->getKey()])
        ->and(idHasil(cariDb(['urut' => 'tahun_desc'])))->toBe([$baru->getKey(), $lama->getKey()])
        ->and(KriteriaPencarian::dari(['per_halaman' => 500])->perHalaman)->toBe(50)
        ->and(cariDb(['per_halaman' => 1, 'halaman' => 2])->dokumen->count())->toBe(1);
});

test('ekspresi boolean mengabaikan token pendek dan operator', function () {
    expect(PencarianDatabase::ekspresiBoolean('PP "tata" +naskah -dinas*'))->toBe('+tata* +naskah* +dinas*')
        ->and(PencarianDatabase::ekspresiBoolean('UU'))->toBeNull();
});

test('ekspresi boolean mengikuti regulasi.ft_min_token (produksi 2: PP dan UU ikut dicari)', function () {
    config(['regulasi.ft_min_token' => 2]);

    expect(PencarianDatabase::ekspresiBoolean('PP 28 tentang UU'))->toBe('+pp* +28* +tentang* +uu*')
        ->and(PencarianDatabase::ekspresiBoolean('a'))->toBeNull();
});

/*
 * Kamus istilah, sinonim, dan Scout driver database (Profil Ringan, 02-ARSITEKTUR §5.1).
 */
test('P-02: permendikbudrstek 53 dikoreksi ke permendikbudristek dan Permendikbudristek 53/2023 teratas', function () {
    $permen = JenisDokumen::factory()->create(['kode' => 'PERMEN', 'nama' => 'Peraturan Menteri']);
    $target = dokumenCari([
        'jenis_dokumen_id' => $permen->getKey(), 'nomor' => '53', 'tahun' => 2023,
        'sebutan_singkat' => 'Permendikbudristek 53/2023', 'judul' => 'Penjaminan Mutu Pendidikan Tinggi',
    ]);
    dokumenCari(['nomor' => '3', 'tahun' => 2021, 'sebutan_singkat' => 'Permendikbud 3/2021', 'judul' => 'Statuta']);
    Artisan::call('regulasi:bangun-kamus');

    $hasil = cariDb(['q' => 'permendikbudrstek 53']);

    expect(idHasil($hasil)[0] ?? null)->toBe($target->getKey())
        ->and($hasil->dikoreksiDari)->toBe('permendikbudrstek 53')
        ->and($hasil->kueriDipakai)->toBe('permendikbudristek 53');
});

test('kueri persis tidak dikoreksi dan token pendek memakai jarak 1', function () {
    dokumenCari(['sebutan_singkat' => 'PMK 181/2016', 'judul' => 'Penatausahaan barang']);
    Artisan::call('regulasi:bangun-kamus');
    $koreksi = app(KoreksiIstilah::class);

    expect(cariDb(['q' => 'penatausahan', 'persis' => true])->dikoreksiDari)->toBeNull()
        ->and(cariDb(['q' => 'penatausahan'])->dikoreksiDari)->toBe('penatausahan')
        ->and($koreksi->koreksi('pmk'))->toBeNull()          // sudah ada di kamus
        ->and($koreksi->koreksi('pmj'))->toBe('pmk')         // token <= 5 huruf: jarak 1
        ->and($koreksi->koreksi('pxx'))->toBeNull()          // jarak 2 untuk token pendek: tidak dikoreksi
        ->and($koreksi->koreksi('181/2016'))->toBeNull();    // angka tidak dikoreksi
});

test('sinonim dua arah: bmn menemukan dokumen berjudul barang milik negara dan sebaliknya', function () {
    config(['regulasi.ft_min_token' => 3]);
    $bmn = dokumenCari(['judul' => 'Penatausahaan Barang Milik Negara', 'sebutan_singkat' => 'PMK 181/2016']);
    $lain = dokumenCari(['judul' => 'Tata kelola kepegawaian', 'sebutan_singkat' => 'PERATURAN 1']);
    $singkat = dokumenCari(['judul' => 'Pedoman BMN fakultas', 'sebutan_singkat' => 'PEDOMAN 2']);
    Artisan::call('regulasi:bangun-kamus');

    expect(idHasil(cariDb(['q' => 'bmn'])))->toEqualCanonicalizing([$bmn->getKey(), $singkat->getKey()])
        ->and(idHasil(cariDb(['q' => 'barang milik negara'])))->toEqualCanonicalizing([$bmn->getKey(), $singkat->getKey()])
        ->and(idHasil(cariDb(['q' => 'bmn'])))->not->toContain($lain->getKey());
});

test('ekspresi boolean menambahkan sinonim sebagai alternatif', function () {
    $sinonim = ['bmn' => ['barang milik negara'], 'barang milik negara' => ['bmn'], 'tnd' => ['tata naskah dinas']];

    expect(PencarianDatabase::ekspresiBoolean('bmn', $sinonim))->toBe('+(bmn* "barang milik negara")')
        ->and(PencarianDatabase::ekspresiBoolean('barang milik negara', $sinonim))->toBe('+((+barang* +milik* +negara*) "bmn")')
        ->and(PencarianDatabase::ekspresiBoolean('tnd 2020', $sinonim))->toBe('+(tnd* "tata naskah dinas") +2020*');
});

test('Scout driver database mencari dokumen terbit saja lewat Dokumen::cariTerbit', function () {
    $pmk = dokumenCari(['nomor' => '181/PMK.06/2016', 'sebutan_singkat' => 'PMK 181/PMK.06/2016', 'judul' => 'Penatausahaan Barang Milik Negara']);
    dokumenCari(['judul' => 'Penatausahaan rahasia', 'status_publikasi' => StatusPublikasi::Draf]);

    expect(config('scout.driver'))->toBe('database')
        ->and(Dokumen::cariTerbit('Penatausahaan')->get()->modelKeys())->toBe([$pmk->getKey()])
        ->and(Dokumen::cariTerbit('181/PMK')->get()->modelKeys())->toBe([$pmk->getKey()]);
});

test('portal menampilkan "Menampilkan hasil untuk" dan tautan cari persis saat kueri dikoreksi', function () {
    dokumenCari(['judul' => 'Penatausahaan Barang Milik Negara', 'sebutan_singkat' => 'PMK 181/2016']);
    Artisan::call('regulasi:bangun-kamus');

    Livewire\Livewire::test(DaftarDokumen::class)
        ->set('q', 'penatausahan')
        ->assertSee('Menampilkan hasil untuk')
        ->assertSee('penatausahaan')
        ->assertSee('Cari persis')
        ->assertSee('PMK 181/2016')
        ->set('persis', true)
        ->assertDontSee('Menampilkan hasil untuk')
        ->assertSee('0 dokumen');
});
