<?php

use App\Actions\Dokumen\BuatSlugDokumen;
use App\Enums\Peran;
use App\Enums\StatusBerlaku;
use App\Enums\StatusPublikasi;
use App\Enums\Visibilitas;
use App\Models\Aktivitas;
use App\Models\BadanPenetap;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Support\SebutanDokumen;
use Database\Seeders\BadanPenetapSeeder;
use Database\Seeders\JenisDokumenSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('id dokumen terisi uuid v7 dan unik', function () {
    $a = Dokumen::factory()->create();
    $b = Dokumen::factory()->create();

    expect(Str::isUuid($a->id))->toBeTrue()
        ->and(substr($a->id, 14, 1))->toBe('7')
        ->and($a->id)->not->toBe($b->id)
        ->and($a->status_publikasi)->toBe(StatusPublikasi::Draf)
        ->and($a->status_berlaku)->toBe(StatusBerlaku::Berlaku)
        ->and($a->visibilitas)->toBe(Visibilitas::Publik);
});

test('tabel dokumen memiliki indeks fulltext dan indeks identitas', function () {
    $indeks = collect(DB::select('SHOW INDEX FROM dokumen'))->pluck('Index_type', 'Key_name');

    expect($indeks->get('ft_dokumen'))->toBe('FULLTEXT')
        ->and($indeks->has('uk_dokumen_identitas'))->toBeTrue()
        ->and($indeks->has('idx_dokumen_publik'))->toBeTrue()
        ->and($indeks->has('idx_dokumen_jenis_tahun'))->toBeTrue();
});

test('slug dokumen dibentuk dari sebutan singkat dan judul serta unik', function () {
    $aksi = new BuatSlugDokumen;
    $dokumen = Dokumen::factory()->make(['sebutan_singkat' => 'PMK 181/PMK.06/2016', 'judul' => 'Penatausahaan Barang Milik Negara']);

    $slug = $aksi->handle($dokumen);
    expect($slug)->toBe('pmk-181-pmk-06-2016-penatausahaan-barang-milik-negara');

    Dokumen::factory()->create(['slug' => $slug]);
    expect($aksi->handle($dokumen))->toBe($slug.'-2');

    Dokumen::factory()->create(['slug' => $slug.'-2'])->delete();
    expect($aksi->handle($dokumen))->toBe($slug.'-3');

    $panjang = Dokumen::factory()->make(['sebutan_singkat' => 'X', 'judul' => str_repeat('kata panjang ', 40)]);
    expect(strlen($aksi->handle($panjang)))->toBeLessThanOrEqual(200);
});

test('BR-06 menolak duplikat jenis badan nomor tahun', function () {
    $jenis = JenisDokumen::factory()->create();
    $badan = BadanPenetap::factory()->create();
    $identitas = ['jenis_dokumen_id' => $jenis->id, 'badan_penetap_id' => $badan->id, 'nomor' => '13', 'tahun' => 2025];

    Dokumen::factory()->create($identitas);

    expect(fn () => Dokumen::factory()->create($identitas))->toThrow(QueryException::class);
});

test('BR-06 mengizinkan dua dokumen tanpa nomor dengan jenis badan tahun sama', function () {
    $jenis = JenisDokumen::factory()->create();
    $badan = BadanPenetap::factory()->create();
    $identitas = ['jenis_dokumen_id' => $jenis->id, 'badan_penetap_id' => $badan->id, 'nomor' => null, 'tahun' => 2025];

    Dokumen::factory()->create($identitas);
    Dokumen::factory()->create($identitas);

    expect(Dokumen::count())->toBe(2);
});

test('scope terlihatOleh: tamu hanya terbit publik, login terbit publik dan internal', function () {
    $terbitPublik = Dokumen::factory()->terbit()->create();
    $terbitInternal = Dokumen::factory()->terbit()->internal()->create();
    Dokumen::factory()->create();
    Dokumen::factory()->internal()->create();
    Dokumen::factory()->berstatus(StatusPublikasi::MenungguVerifikasi)->create();
    Dokumen::factory()->berstatus(StatusPublikasi::Diarsipkan)->create();

    $pengguna = penggunaDenganPeran(Peran::Pengguna);

    expect(Dokumen::terlihatOleh(null)->pluck('id')->all())->toBe([$terbitPublik->id])
        ->and(Dokumen::terlihatOleh($pengguna)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$terbitPublik->id, $terbitInternal->id])->sort()->values()->all());
});

test('scope dalamKategoriPengguna hanya dokumen berkategori yang ditugaskan', function () {
    $aset = KategoriSistem::factory()->create();
    $akreditasi = KategoriSistem::factory()->create();
    $kontributor = penggunaDenganPeran(Peran::Kontributor);
    $kontributor->kategoriSistem()->attach($aset);

    $dokAset = Dokumen::factory()->create();
    $dokAset->kategoriSistem()->attach([$aset->id, $akreditasi->id]);
    $dokAkreditasi = Dokumen::factory()->create();
    $dokAkreditasi->kategoriSistem()->attach($akreditasi);
    Dokumen::factory()->create();

    expect(Dokumen::dalamKategoriPengguna($kontributor)->pluck('id')->all())->toBe([$dokAset->id]);
});

test('sebutan PMK 181/PMK.06/2016 tidak menambah tahun', function () {
    $this->seed([JenisDokumenSeeder::class, BadanPenetapSeeder::class]);

    $dokumen = Dokumen::factory()->make([
        'jenis_dokumen_id' => JenisDokumen::where('kode', 'PERMEN')->value('id'),
        'badan_penetap_id' => BadanPenetap::where('slug', 'kemenkeu')->value('id'),
        'nomor' => '181/PMK.06/2016',
        'tahun' => 2016,
    ]);

    expect(SebutanDokumen::buat($dokumen))->toBe([
        'sebutan' => 'Peraturan Menteri Keuangan Nomor 181/PMK.06/2016',
        'sebutan_singkat' => 'PMK 181/PMK.06/2016',
    ]);
});

test('sebutan Peraturan BAN-PT 13/2025, Permendikbudristek, dan dokumen tanpa nomor', function () {
    $this->seed([JenisDokumenSeeder::class, BadanPenetapSeeder::class]);
    $jenis = fn (string $kode) => JenisDokumen::where('kode', $kode)->value('id');
    $badan = fn (string $slug) => BadanPenetap::where('slug', $slug)->value('id');

    $banpt = Dokumen::factory()->make(['jenis_dokumen_id' => $jenis('PERLEMBAGA'), 'badan_penetap_id' => $badan('ban-pt'), 'nomor' => '13', 'tahun' => 2025]);
    $permen = Dokumen::factory()->make(['jenis_dokumen_id' => $jenis('PERMEN'), 'badan_penetap_id' => $badan('kemendikbudristek'), 'nomor' => '53', 'tahun' => 2023]);
    $pedoman = Dokumen::factory()->make(['jenis_dokumen_id' => $jenis('PEDOMAN'), 'badan_penetap_id' => $badan('lamdik'), 'nomor' => null, 'tahun' => 2025]);

    expect(SebutanDokumen::buat($banpt))->toBe(['sebutan' => 'Peraturan BAN-PT Nomor 13 Tahun 2025', 'sebutan_singkat' => 'BAN-PT 13/2025'])
        ->and(SebutanDokumen::buat($permen))->toBe([
            'sebutan' => 'Peraturan Menteri Pendidikan, Kebudayaan, Riset, dan Teknologi Nomor 53 Tahun 2023',
            'sebutan_singkat' => 'Permendikbudristek 53/2023',
        ])
        ->and(SebutanDokumen::buat($pedoman)['sebutan'])->toBe('Pedoman/Panduan Tahun 2025');
});

test('tanggal efektif memakai berlaku, lalu pengundangan, lalu penetapan', function () {
    $dokumen = Dokumen::factory()->make([
        'tanggal_penetapan' => '2025-06-16',
        'tanggal_pengundangan' => '2025-06-20',
        'tanggal_berlaku' => '2025-08-18',
    ]);

    expect($dokumen->tanggal_efektif?->toDateString())->toBe('2025-08-18');

    $dokumen->tanggal_berlaku = null;
    expect($dokumen->tanggal_efektif?->toDateString())->toBe('2025-06-20');

    $dokumen->tanggal_pengundangan = null;
    expect($dokumen->tanggal_efektif?->toDateString())->toBe('2025-06-16');

    $dokumen->tanggal_penetapan = null;
    expect($dokumen->tanggal_efektif)->toBeNull();
});

test('enum status publikasi mengikuti diagram transisi', function () {
    expect(StatusPublikasi::Draf->bolehTransisiKe(StatusPublikasi::MenungguVerifikasi))->toBeTrue()
        ->and(StatusPublikasi::Draf->bolehTransisiKe(StatusPublikasi::Terbit))->toBeTrue()
        ->and(StatusPublikasi::Draf->bolehTransisiKe(StatusPublikasi::Diarsipkan))->toBeFalse()
        ->and(StatusPublikasi::MenungguVerifikasi->bolehTransisiKe(StatusPublikasi::Ditolak))->toBeTrue()
        ->and(StatusPublikasi::Ditolak->bolehTransisiKe(StatusPublikasi::Draf))->toBeTrue()
        ->and(StatusPublikasi::Ditolak->bolehTransisiKe(StatusPublikasi::Terbit))->toBeFalse()
        ->and(StatusPublikasi::Terbit->bolehTransisiKe(StatusPublikasi::Diarsipkan))->toBeTrue()
        ->and(StatusPublikasi::Diarsipkan->bolehTransisiKe(StatusPublikasi::Terbit))->toBeTrue()
        ->and(StatusBerlaku::Diubah->getLabel())->toBe('Berlaku dengan Perubahan')
        ->and(StatusBerlaku::Dicabut->getColor())->toBe('danger');
});

test('perubahan dokumen tercatat di log aktivitas tanpa jumlah dibuka', function () {
    $dokumen = Dokumen::factory()->create();
    $dokumen->update(['judul' => 'Judul Baru']);

    $dokumen->getConnection()->table('dokumen')->where('id', $dokumen->id)->increment('jumlah_dibuka');

    $log = Aktivitas::query()->where('subject_id', $dokumen->id)->where('event', 'updated')->firstOrFail();

    expect($log->log_name)->toBe('dokumen')
        ->and($log->attribute_changes->get('attributes'))->toBe(['judul' => 'Judul Baru'])
        ->and(Aktivitas::query()->where('subject_id', $dokumen->id)->count())->toBe(2);
});
