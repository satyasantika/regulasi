<?php

use App\Actions\Relasi\TambahRelasiDokumen;
use App\Enums\JenisRelasi;
use App\Enums\JenisTautan;
use App\Enums\PenyediaTautan;
use App\Enums\Peran;
use App\Enums\StatusBerlaku;
use App\Enums\StatusPublikasi;
use App\Models\BadanPenetap;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\TautanBerkas;
use Database\Seeders\BadanPenetapSeeder;
use Database\Seeders\DokumenContohSeeder;
use Database\Seeders\JenisDokumenSeeder;
use Database\Seeders\KategoriSistemSeeder;
use Database\Seeders\TagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

const ID_DRIVE_DETAIL = '1DetailInternalRahasiaQwErTy12345';

function tautanDetail(Dokumen $dokumen, JenisTautan $jenis = JenisTautan::NaskahUtama, string $id = ID_DRIVE_DETAIL): TautanBerkas
{
    return TautanBerkas::create([
        'pemilik_type' => 'dokumen',
        'pemilik_id' => $dokumen->id,
        'jenis' => $jenis,
        'url' => "https://drive.google.com/file/d/{$id}/view?usp=sharing",
        'penyedia' => PenyediaTautan::GoogleDrive,
        'drive_file_id' => $id,
    ]);
}

function urlDetail(Dokumen $dokumen, ?string $slug = null): string
{
    return route('dokumen.show', ['dokumen' => $dokumen->id, 'slug' => $slug ?? $dokumen->slug]);
}

test('dokumen terbit publik 200 dan memuat sebutan, T.E.U., tanggal, dan breadcrumb', function () {
    $badan = BadanPenetap::factory()->create(['teu' => 'Indonesia. Badan Uji Detail']);
    $jenis = JenisDokumen::factory()->create(['nama' => 'Peraturan Menteri', 'slug' => 'peraturan-menteri']);
    $dokumen = Dokumen::factory()->terbit()->create([
        'jenis_dokumen_id' => $jenis->id,
        'badan_penetap_id' => $badan->id,
        'sebutan' => 'Peraturan Uji Nomor 13 Tahun 2025',
        'sebutan_singkat' => 'UJI 13/2025',
        'judul' => 'Instrumen Uji',
        'tanggal_penetapan' => '2025-06-16',
        'tanggal_berlaku' => '2025-08-18',
    ]);
    tautanDetail($dokumen);

    $this->get(urlDetail($dokumen))
        ->assertOk()
        ->assertSee('Peraturan Uji Nomor 13 Tahun 2025')
        ->assertSee('UJI 13/2025')
        ->assertSee('Indonesia. Badan Uji Detail')
        ->assertSee('16 Juni 2025')
        ->assertSee('18 Agustus 2025')
        ->assertSee(route('dokumen.jenis', 'peraturan-menteri'), false)
        ->assertSee('Buka berkas')
        ->assertSee('Tampilkan pratinjau')
        ->assertSee('https://drive.google.com/file/d/'.ID_DRIVE_DETAIL.'/preview', false)
        // URL mentah tersimpan tidak dicetak; berkas dibuka lewat rute berotorisasi.
        ->assertDontSee('usp=sharing', false)
        ->assertSee(route('tautan.buka', TautanBerkas::sole()), false);
});

test('slug salah atau kosong dialihkan 301 ke slug terkini', function () {
    $dokumen = Dokumen::factory()->terbit()->create();

    $this->get(urlDetail($dokumen, 'slug-lama'))->assertStatus(301)->assertRedirect(urlDetail($dokumen));
    $this->get("/dokumen/{$dokumen->id}")->assertStatus(301)->assertRedirect(urlDetail($dokumen));
});

test('draf, menunggu verifikasi, diarsipkan, dan ditolak mendapat 404', function (StatusPublikasi $status) {
    $dokumen = Dokumen::factory()->berstatus($status)->create();

    $this->get(urlDetail($dokumen))->assertNotFound();
    $this->actingAs(penggunaDenganPeran(Peran::Pengguna))->get(urlDetail($dokumen))->assertNotFound();
})->with([StatusPublikasi::Draf, StatusPublikasi::MenungguVerifikasi, StatusPublikasi::Diarsipkan, StatusPublikasi::Ditolak]);

test('dokumen terbit yang dihapus lunak dan UUID tak dikenal mendapat 404', function () {
    $dokumen = Dokumen::factory()->terbit()->create();
    $dokumen->delete();

    $this->get(urlDetail($dokumen))->assertNotFound();
    $this->get('/dokumen/01a00000-0000-7000-8000-000000000000/apa-saja')->assertNotFound();
    $this->get('/dokumen/123/apa-saja')->assertNotFound();
});

test('dokumen internal: tamu dialihkan ke halaman masuk tanpa URL Drive, pengguna login melihat iframe pratinjau', function () {
    $dokumen = Dokumen::factory()->terbit()->internal()->create();
    tautanDetail($dokumen);

    // Tamu, baik dengan slug benar maupun salah: diarahkan ke login dan tidak ada URL Drive/URL mentah di respons.
    foreach ([urlDetail($dokumen), urlDetail($dokumen, 'slug-lain')] as $url) {
        $respons = $this->get($url)->assertRedirect(route('filament.akun.auth.login'));

        expect((string) $respons->getContent())
            ->not->toContain(ID_DRIVE_DETAIL)
            ->not->toContain('drive.google.com');
    }

    $this->actingAs(penggunaDenganPeran(Peran::Pengguna))
        ->get(urlDetail($dokumen))
        ->assertOk()
        ->assertSee('Internal FKIP')
        ->assertSee('<iframe src="https://drive.google.com/file/d/'.ID_DRIVE_DETAIL.'/preview"', false)
        ->assertDontSee('usp=sharing', false);
});

test('halaman publik tidak pernah memuat URL Drive dokumen internal yang direlasikan', function () {
    $publik = Dokumen::factory()->terbit()->create();
    $internal = Dokumen::factory()->terbit()->internal()->create(['sebutan_singkat' => 'INTERNAL 9/2025']);
    tautanDetail($internal);
    app(TambahRelasiDokumen::class)->handle($publik, $internal, JenisRelasi::Melaksanakan, null, penggunaDenganPeran(Peran::AdminRegulasi));

    $html = (string) $this->get(urlDetail($publik))->assertOk()->getContent();

    expect($html)->toContain('INTERNAL 9/2025')
        ->toContain('internal — masuk untuk membuka')
        ->not->toContain(ID_DRIVE_DETAIL)
        ->not->toContain(urlDetail($internal));
});

test('abstrak berisi skrip ditampilkan ter-escape per paragraf', function () {
    $dokumen = Dokumen::factory()->terbit()->create([
        'abstrak' => "Paragraf pertama.\n\n<script>alert('xss')</script>",
    ]);

    $this->get(urlDetail($dokumen))
        ->assertOk()
        ->assertSee('<p>Paragraf pertama.</p>', false)
        ->assertSee('&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;', false)
        ->assertDontSee("<script>alert('xss')</script>", false);
});

test('dokumen tanpa berkas menampilkan kotak Metadata saja', function () {
    $dokumen = Dokumen::factory()->rujukan()->create();

    $this->get(urlDetail($dokumen))
        ->assertOk()
        ->assertSee('Metadata saja — naskah belum tersedia')
        ->assertDontSee('Tampilkan pratinjau');
});

test('sumber resmi pemerintah cukup tombol buka di situs sumber tanpa iframe', function () {
    $dokumen = Dokumen::factory()->terbit()->create();
    TautanBerkas::create([
        'pemilik_type' => 'dokumen',
        'pemilik_id' => $dokumen->id,
        'jenis' => JenisTautan::NaskahUtama,
        'url' => 'https://peraturan.go.id/files/uji-detail.pdf',
        'penyedia' => PenyediaTautan::SumberResmi,
    ]);

    $this->get(urlDetail($dokumen))
        ->assertOk()
        ->assertSee('Buka di situs sumber')
        ->assertDontSee('<iframe', false)
        ->assertDontSee('peraturan.go.id/files', false);
});

test('Permendikbud 3/2021 hasil seed menampilkan Dicabut oleh dengan tautan ke Permendiktisaintek 42/2025', function () {
    Queue::fake();
    $this->seed([JenisDokumenSeeder::class, BadanPenetapSeeder::class, KategoriSistemSeeder::class, TagSeeder::class]);
    penggunaDenganPeran(Peran::SuperAdmin);
    config([
        'regulasi.seed_tautan_lokal' => storage_path('framework/testing/tautan-detail-'.uniqid().'.php'),
        'regulasi.seed_terbitkan_semua' => true,
    ]);
    app()['env'] = 'local';

    try {
        $this->seed(DokumenContohSeeder::class);
    } finally {
        app()['env'] = 'testing';
    }

    $jenisPermen = JenisDokumen::query()->where('kode', 'PERMEN')->value('id');
    $permen3 = Dokumen::query()->where('jenis_dokumen_id', $jenisPermen)->where('nomor', '3')->where('tahun', 2021)->sole();
    $permen42 = Dokumen::query()->where('jenis_dokumen_id', $jenisPermen)->where('nomor', '42')->where('tahun', 2025)->sole();

    expect($permen3->status_berlaku)->toBe(StatusBerlaku::Dicabut);

    $this->get(urlDetail($permen3))
        ->assertOk()
        ->assertSee('Dicabut oleh')
        ->assertSee('Dicabut oleh Permendiktisaintek 42/2025 sejak 15 Oktober 2025.')
        ->assertSee(urlDetail($permen42), false);

    $this->get(urlDetail($permen42))
        ->assertOk()
        ->assertSee('Mencabut')
        ->assertSee(urlDetail($permen3), false);
});

test('CSP mengizinkan sematan hanya dari Google Drive dan Docs', function () {
    $dokumen = Dokumen::factory()->terbit()->create();

    $csp = (string) $this->get(urlDetail($dokumen))->assertOk()->headers->get('Content-Security-Policy');

    expect($csp)->toContain('frame-src https://drive.google.com https://docs.google.com')
        ->toContain("object-src 'none'");
});
