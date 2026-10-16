<?php

use App\Actions\Dokumen\ArsipkanDokumen;
use App\Actions\Dokumen\SimpanDokumen;
use App\Actions\Dokumen\TerbitkanDokumen;
use App\Enums\JenisTautan;
use App\Enums\KelompokJenisDokumen;
use App\Enums\Peran;
use App\Enums\StatusBerlaku;
use App\Enums\StatusEkstraksi;
use App\Enums\StatusPublikasi;
use App\Events\StatusBerlakuBerubah;
use App\Events\TeksTautanDiekstraksi;
use App\Jobs\BangunKamusIstilah;
use App\Models\Dokumen;
use App\Models\EkstraksiTeks;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\TautanBerkas;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function dokumenTerindeks(array $atribut = [], bool $naskah = true): Dokumen
{
    $dokumen = Dokumen::factory()->create(array_merge([
        'jenis_dokumen_id' => JenisDokumen::factory()->create(['kelompok' => KelompokJenisDokumen::Peraturan]),
        'tanggal_penetapan' => '2023-08-28',
    ], $atribut));
    $dokumen->kategoriSistem()->attach(KategoriSistem::query()->where('kode', 'aset')->first() ?? KategoriSistem::factory()->create(['kode' => 'aset']));

    if ($naskah) {
        tautanDenganTeks($dokumen, JenisTautan::NaskahUtama, 'naskah utama tata naskah dinas');
    }

    return $dokumen;
}

function tautanDenganTeks(Dokumen $dokumen, JenisTautan $jenis, string $teks, StatusEkstraksi $status = StatusEkstraksi::Berhasil): TautanBerkas
{
    $tautan = TautanBerkas::query()->create([
        'pemilik_type' => 'dokumen',
        'pemilik_id' => $dokumen->getKey(),
        'jenis' => $jenis,
        'url' => 'https://lppm.unsil.ac.id/'.fake()->unique()->uuid().'.pdf',
        'penyedia' => 'unsil',
        'urutan' => $jenis === JenisTautan::NaskahUtama ? 5 : 0,
    ]);
    EkstraksiTeks::query()->create(['tautan_berkas_id' => $tautan->getKey(), 'status' => $status, 'isi_teks' => $teks]);

    return $tautan;
}

test('Scout memakai driver database dan indeks bernama regulasi_dokumen', function () {
    expect(config('scout.driver'))->toBe('database')
        ->and(config('scout.queue'))->toBeFalse()
        ->and((new Dokumen)->searchableAs())->toBe('regulasi_dokumen');
});

test('toSearchableArray hanya memuat kolom nyata tabel dokumen yang dicari', function () {
    $dokumen = dokumenTerindeks(['status_publikasi' => StatusPublikasi::Terbit, 'nomor' => '53', 'sebutan_singkat' => 'Permendikbudristek 53/2023']);

    $data = Dokumen::query()->findOrFail($dokumen->getKey())->toSearchableArray();

    expect(array_keys($data))->toBe(['id', 'sebutan_singkat', 'judul', 'nomor', 'abstrak'])
        ->and($data['nomor'])->toBe('53');

    foreach (array_keys($data) as $kolom) {
        expect(Schema::hasColumn('dokumen', $kolom))->toBeTrue("kolom {$kolom} tidak ada di tabel dokumen");
    }
});

test('hanya dokumen terbit dan tidak terhapus yang searchable', function () {
    $terbit = dokumenTerindeks(['status_publikasi' => StatusPublikasi::Terbit]);
    $draf = dokumenTerindeks(['status_publikasi' => StatusPublikasi::Draf]);
    $arsip = dokumenTerindeks(['status_publikasi' => StatusPublikasi::Diarsipkan]);
    $terhapus = dokumenTerindeks(['status_publikasi' => StatusPublikasi::Terbit]);
    $terhapus->delete();

    expect($terbit->shouldBeSearchable())->toBeTrue()
        ->and($draf->shouldBeSearchable())->toBeFalse()
        ->and($arsip->shouldBeSearchable())->toBeFalse()
        ->and($terhapus->shouldBeSearchable())->toBeFalse();
});

test('terbitkan dan arsipkan memicu pembangunan ulang kamus di antrean berat', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = dokumenTerindeks(['status_publikasi' => StatusPublikasi::MenungguVerifikasi]);

    Queue::fake();
    app(TerbitkanDokumen::class)->handle($dokumen, $admin);
    Queue::assertPushedOn('berat', BangunKamusIstilah::class);

    Queue::fake();
    app(ArsipkanDokumen::class)->handle($dokumen->refresh(), $admin, 'Diganti peraturan yang lebih baru.');
    Queue::assertPushedOn('berat', BangunKamusIstilah::class);
});

test('menyimpan draf tidak memicu kamus; mengubah dokumen terbit memicunya', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $draf = dokumenTerindeks();
    $terbit = dokumenTerindeks(['status_publikasi' => StatusPublikasi::Terbit]);
    $kategori = KategoriSistem::factory()->create()->getKey();

    Queue::fake();
    app(SimpanDokumen::class)->handle(['judul' => 'Judul draf baru', 'kategori_sistem' => [$kategori]], $admin, $draf);
    Queue::assertNotPushed(BangunKamusIstilah::class);

    app(SimpanDokumen::class)->handle(['judul' => 'Judul terbit baru', 'kategori_sistem' => [$kategori]], $admin, $terbit);
    Queue::assertPushedOn('berat', BangunKamusIstilah::class);
});

test('teks tautan diekstraksi dan status berlaku berubah memicu kamus', function () {
    $dokumen = dokumenTerindeks(['status_publikasi' => StatusPublikasi::Terbit]);
    $tautan = TautanBerkas::query()->where('pemilik_id', $dokumen->getKey())->with('ekstraksi')->firstOrFail();

    Queue::fake();
    TeksTautanDiekstraksi::dispatch($tautan, $tautan->ekstraksi);
    Queue::assertPushedOn('berat', BangunKamusIstilah::class);

    Queue::fake();
    StatusBerlakuBerubah::dispatch($dokumen, StatusBerlaku::Berlaku, StatusBerlaku::Dicabut);
    Queue::assertPushedOn('berat', BangunKamusIstilah::class);
});

test('job kamus unik sampai mulai diproses dan memakai koneksi database-berat', function () {
    $job = new BangunKamusIstilah;

    expect($job)->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class)
        ->and($job->connection)->toBe('database-berat')
        ->and($job->queue)->toBe('berat');
});
