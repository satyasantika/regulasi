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
use App\Models\Dokumen;
use App\Models\EkstraksiTeks;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\Tag;
use App\Models\TautanBerkas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Scout\Jobs\MakeSearchable;
use Laravel\Scout\Jobs\RemoveFromSearch;

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

test('indeks bernama regulasi_dokumen dan memuat kategori, tag, serta isi teks terpotong naskah utama lebih dulu', function () {
    config(['regulasi.potong_isi_teks_kata' => 6]);
    $dokumen = dokumenTerindeks(['status_publikasi' => StatusPublikasi::Terbit, 'dipublikasikan_pada' => now()]);
    $dokumen->tag()->attach(Tag::factory()->create(['nama' => 'Tata Naskah Dinas']));
    tautanDenganTeks($dokumen, JenisTautan::Lampiran, 'lampiran matriks penilaian satu dua tiga');
    tautanDenganTeks($dokumen, JenisTautan::Lampiran, 'teks gagal tidak diindeks', StatusEkstraksi::TanpaTeks);

    $data = Dokumen::query()->findOrFail($dokumen->getKey())->toSearchableArray();

    expect((new Dokumen)->searchableAs())->toBe('regulasi_dokumen')
        ->and($data['kategori'])->toBe(['aset'])
        ->and($data['tag'])->toBe(['Tata Naskah Dinas'])
        ->and($data['isi_teks'])->toBe('naskah utama tata naskah dinas lampiran')
        ->and($data['kelompok'])->toBe('peraturan')
        ->and($data['status_berlaku'])->toBe('berlaku')
        ->and($data['visibilitas'])->toBe('publik')
        ->and($data['tanggal_penetapan_ts'])->toBe($dokumen->tanggal_penetapan->getTimestamp())
        ->and($data['url'])->toEndWith("/dokumen/{$dokumen->getKey()}/{$dokumen->slug}")
        ->and($data)->toHaveKeys(['id', 'sebutan_singkat', 'sebutan', 'judul', 'nomor', 'tahun', 'jenis', 'jenis_nama', 'badan_penetap', 'badan_nama', 'tanpa_berkas', 'abstrak', 'dipublikasikan_pada_ts', 'jumlah_dibuka']);
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

test('terbitkan mendorong MakeSearchable ke antrean indeks, arsipkan mendorong RemoveFromSearch', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = dokumenTerindeks(['status_publikasi' => StatusPublikasi::MenungguVerifikasi]);

    Queue::fake();
    app(TerbitkanDokumen::class)->handle($dokumen, $admin);
    Queue::assertPushedOn('default', MakeSearchable::class);

    Queue::fake();
    app(ArsipkanDokumen::class)->handle($dokumen->refresh(), $admin, 'Diganti peraturan yang lebih baru.');
    Queue::assertPushedOn('default', RemoveFromSearch::class);
    Queue::assertNotPushed(MakeSearchable::class);
});

test('menyimpan draf tidak menyentuh indeks; mengubah dokumen terbit mengindeks ulang', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $draf = dokumenTerindeks();
    $terbit = dokumenTerindeks(['status_publikasi' => StatusPublikasi::Terbit]);
    $kategori = KategoriSistem::factory()->create()->getKey();

    Queue::fake();
    app(SimpanDokumen::class)->handle(['judul' => 'Judul draf baru', 'kategori_sistem' => [$kategori]], $admin, $draf);
    Queue::assertNothingPushed();

    app(SimpanDokumen::class)->handle(['judul' => 'Judul terbit baru', 'kategori_sistem' => [$kategori]], $admin, $terbit);
    Queue::assertPushedOn('default', MakeSearchable::class);
});

test('teks tautan diekstraksi dan status berlaku berubah memicu reindeks', function () {
    $dokumen = dokumenTerindeks(['status_publikasi' => StatusPublikasi::Terbit]);
    $tautan = TautanBerkas::query()->where('pemilik_id', $dokumen->getKey())->with('ekstraksi')->firstOrFail();

    Queue::fake();
    TeksTautanDiekstraksi::dispatch($tautan, $tautan->ekstraksi);
    Queue::assertPushedOn('default', MakeSearchable::class);

    Queue::fake();
    StatusBerlakuBerubah::dispatch($dokumen, StatusBerlaku::Berlaku, StatusBerlaku::Dicabut);
    Queue::assertPushedOn('default', MakeSearchable::class);
});

test('teks diekstraksi pada dokumen draf tidak mengindeks', function () {
    $dokumen = dokumenTerindeks();
    $tautan = TautanBerkas::query()->where('pemilik_id', $dokumen->getKey())->with('ekstraksi')->firstOrFail();

    Queue::fake();
    TeksTautanDiekstraksi::dispatch($tautan, $tautan->ekstraksi);

    Queue::assertNotPushed(MakeSearchable::class);
});

test('pengaturan indeks memuat atribut filter dan sinonim dua arah', function () {
    $pengaturan = config('scout.meilisearch.index-settings')[Dokumen::class];

    expect($pengaturan['filterableAttributes'])->toContain('kategori', 'status_berlaku', 'visibilitas', 'badan_penetap')
        ->and($pengaturan['typoTolerance'])->toBe(['disableOnAttributes' => ['nomor']])
        ->and($pengaturan['synonyms']['uu'])->toBe(['undang-undang'])
        ->and($pengaturan['synonyms']['undang-undang'])->toBe(['uu'])
        ->and($pengaturan['synonyms']['tata naskah dinas'])->toBe(['tnd']);
});

test('regulasi:reindeks mengimpor ulang hanya dokumen terbit', function () {
    dokumenTerindeks(['status_publikasi' => StatusPublikasi::Terbit]);
    dokumenTerindeks(['status_publikasi' => StatusPublikasi::Terbit]);
    dokumenTerindeks();

    $this->artisan('regulasi:reindeks', ['--hapus' => true])
        ->expectsOutputToContain('2 dokumen terbit diindeks ulang ke regulasi_dokumen')
        ->assertSuccessful();
});
