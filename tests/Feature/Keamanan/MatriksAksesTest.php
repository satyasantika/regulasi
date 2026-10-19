<?php

use App\Enums\JenisTautan;
use App\Enums\PenyediaTautan;
use App\Enums\Peran;
use App\Enums\StatusPublikasi;
use App\Models\Aktivitas;
use App\Models\BadanPenetap;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\KlienApi;
use App\Models\LaporanEvaluasi;
use App\Models\Tag;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Support\Laporan\RingkasanEvaluasi;
use Carbon\CarbonImmutable;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

/**
 * Matriks hak akses PRD §3.2 (F12.1, KM-01): setiap sel diuji untuk super-admin, admin-regulasi, kontributor
 * (kategori Aset/BMN), pengguna, dan tamu. Kode harapan: angka = status HTTP; "masuk" = dialihkan ke halaman
 * masuk; "berkas" = dialihkan (302) ke URL berkas tersimpan.
 */
uses(RefreshDatabase::class);

const PERAN_MATRIKS = ['super-admin', 'admin-regulasi', 'kontributor', 'pengguna', 'tamu'];

beforeEach(function () {
    RateLimiter::clear('buka');
    $this->seed(PeranDanIzinSeeder::class);

    $aset = KategoriSistem::factory()->create(['kode' => 'aset', 'slug' => 'aset-bmn']);
    $akreditasi = KategoriSistem::factory()->create(['kode' => 'akreditasi', 'slug' => 'akreditasi']);

    $kontributor = penggunaDenganPeran(Peran::Kontributor);
    $kontributor->kategoriSistem()->attach($aset);

    $this->aktor = [
        'super-admin' => penggunaDenganPeran(Peran::SuperAdmin),
        'admin-regulasi' => penggunaDenganPeran(Peran::AdminRegulasi),
        'kontributor' => $kontributor,
        'pengguna' => penggunaDenganPeran(Peran::Pengguna),
        'tamu' => null,
    ];

    $dokumen = function (KategoriSistem $kategori, array $atribut = [], ?Closure $ubah = null): Dokumen {
        $pabrik = Dokumen::factory();
        $pabrik = $ubah ? $ubah($pabrik) : $pabrik;
        $dokumen = $pabrik->create($atribut);
        $dokumen->kategoriSistem()->attach($kategori);

        return $dokumen;
    };

    $tautan = fn (Dokumen $d): TautanBerkas => TautanBerkas::create([
        'pemilik_type' => 'dokumen',
        'pemilik_id' => $d->id,
        'jenis' => JenisTautan::NaskahUtama,
        'url' => 'https://drive.google.com/file/d/1MatriksAkses'.substr(str_replace('-', '', $d->id), -12).'/view',
        'penyedia' => PenyediaTautan::GoogleDrive,
        'drive_file_id' => '1MatriksAkses'.substr(str_replace('-', '', $d->id), -12),
    ]);

    $drafMilik = $dokumen($aset, ['dibuat_oleh' => $kontributor->id]);
    $drafAsetLain = $dokumen($aset, ['dibuat_oleh' => $this->aktor['admin-regulasi']->id]);
    $drafAkreditasi = $dokumen($akreditasi, ['dibuat_oleh' => $kontributor->id]);
    $terbitAset = $dokumen($aset, ['dibuat_oleh' => $kontributor->id], fn ($f) => $f->terbit());
    $terbitInternal = $dokumen($aset, [], fn ($f) => $f->terbit()->internal());
    $menunggu = $dokumen($aset, ['dibuat_oleh' => $kontributor->id], fn ($f) => $f->berstatus(StatusPublikasi::MenungguVerifikasi));

    $klien = KlienApi::factory()->create();
    $laporan = new LaporanEvaluasi([
        'judul' => 'Laporan Semester Uji',
        'periode_mulai' => '2026-01-01',
        'periode_selesai' => '2026-06-30',
        'dibuat_oleh' => $this->aktor['admin-regulasi']->id,
    ]);
    $laporan->forceFill(['ringkasan' => RingkasanEvaluasi::hitung(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-06-30'))])->save();

    activity('uji')->log('aktivitas uji matriks');

    $this->pengganti = [
        '{pengguna}' => $this->aktor['pengguna']->id,
        // Master memakai slug sebagai kunci rute (MemilikiSlug).
        '{jenis}' => JenisDokumen::factory()->create()->slug,
        '{badan}' => BadanPenetap::factory()->create()->slug,
        '{kategori}' => $akreditasi->slug,
        '{tag}' => Tag::factory()->create()->slug,
        '{draf-milik}' => $drafMilik->id,
        '{draf-aset-lain}' => $drafAsetLain->id,
        '{draf-akreditasi}' => $drafAkreditasi->id,
        '{terbit-aset}' => $terbitAset->id,
        '{terbit-aset-slug}' => $terbitAset->slug,
        '{terbit-internal}' => $terbitInternal->id,
        '{menunggu}' => $menunggu->id,
        '{klien}' => $klien->id,
        '{laporan}' => $laporan->id,
        '{aktivitas}' => Aktivitas::query()->latest()->value('id'),
        '{tautan-publik}' => $tautan($terbitAset)->id,
        '{tautan-internal}' => $tautan($terbitInternal)->id,
        '{tautan-draf}' => $tautan($drafMilik)->id,
        '{tautan-draf-lain}' => $tautan($drafAkreditasi)->id,
        '{jenis-slug}' => $terbitAset->load('jenisDokumen')->jenisDokumen->slug,
    ];
});

/**
 * @param  array<int, int|string>  $harapan  urutan PERAN_MATRIKS
 */
function periksaSelMatriks(TestResponse $respons, int|string $harapan, string $peran, string $path): void
{
    $pesan = "{$peran} GET {$path}";

    match ($harapan) {
        'masuk' => expect($respons->getStatusCode())->toBe(302, $pesan)
            ->and(str_contains((string) $respons->headers->get('Location'), '/login'))->toBeTrue($pesan),
        'berkas' => expect($respons->getStatusCode())->toBe(302, $pesan)
            ->and(str_starts_with((string) $respons->headers->get('Location'), 'https://drive.google.com/'))->toBeTrue($pesan),
        default => expect($respons->getStatusCode())->toBe($harapan, $pesan),
    };
}

test('matriks hak akses PRD §3.2 per peran', function (string $path, array $harapan) {
    $path = strtr($path, $this->pengganti);

    foreach (PERAN_MATRIKS as $i => $peran) {
        app('auth')->forgetGuards();
        $pengguna = $this->aktor[$peran];
        $permintaan = $pengguna instanceof User ? $this->actingAs($pengguna) : $this;

        periksaSelMatriks($permintaan->get($path), $harapan[$i], $peran, $path);
    }
})->with([
    // Panel & dasbor.
    'dasbor admin' => ['/admin', [200, 200, 200, 403, 'masuk']],
    'panel akun' => ['/akun', [200, 200, 200, 200, 'masuk']],
    // Pengguna & peran: super-admin L B U; admin-regulasi L; lainnya —.
    'pengguna: daftar' => ['/admin/pengguna', [200, 200, 403, 403, 'masuk']],
    'pengguna: lihat' => ['/admin/pengguna/{pengguna}', [200, 200, 403, 403, 'masuk']],
    'pengguna: buat' => ['/admin/pengguna/create', [200, 403, 403, 403, 'masuk']],
    'pengguna: ubah' => ['/admin/pengguna/{pengguna}/edit', [200, 403, 403, 403, 'masuk']],
    // Master: super-admin & admin-regulasi L B U H; kontributor L.
    'jenis: daftar' => ['/admin/jenis-dokumen', [200, 200, 200, 403, 'masuk']],
    'jenis: buat' => ['/admin/jenis-dokumen/create', [200, 200, 403, 403, 'masuk']],
    'jenis: ubah' => ['/admin/jenis-dokumen/{jenis}/edit', [200, 200, 403, 403, 'masuk']],
    'badan: daftar' => ['/admin/badan-penetap', [200, 200, 200, 403, 'masuk']],
    'badan: buat' => ['/admin/badan-penetap/create', [200, 200, 403, 403, 'masuk']],
    'badan: ubah' => ['/admin/badan-penetap/{badan}/edit', [200, 200, 403, 403, 'masuk']],
    'kategori: daftar' => ['/admin/kategori-sistem', [200, 200, 200, 403, 'masuk']],
    'kategori: buat' => ['/admin/kategori-sistem/create', [200, 200, 403, 403, 'masuk']],
    'kategori: ubah' => ['/admin/kategori-sistem/{kategori}/edit', [200, 200, 403, 403, 'masuk']],
    // Tag: kontributor boleh membuat, tidak mengubah.
    'tag: daftar' => ['/admin/tag', [200, 200, 200, 403, 'masuk']],
    'tag: buat' => ['/admin/tag/create', [200, 200, 200, 403, 'masuk']],
    'tag: ubah' => ['/admin/tag/{tag}/edit', [200, 200, 403, 403, 'masuk']],
    // Dokumen: kontributor hanya kategori yang ditugaskan, ubah hanya draf/ditolak miliknya (BR-03, BR-16).
    'dokumen: daftar' => ['/admin/dokumen', [200, 200, 200, 403, 'masuk']],
    'dokumen: buat' => ['/admin/dokumen/create', [200, 200, 200, 403, 'masuk']],
    'dokumen: lihat draf milik' => ['/admin/dokumen/{draf-milik}', [200, 200, 200, 403, 'masuk']],
    'dokumen: ubah draf milik' => ['/admin/dokumen/{draf-milik}/edit', [200, 200, 200, 403, 'masuk']],
    'dokumen: lihat draf sekategori orang lain' => ['/admin/dokumen/{draf-aset-lain}', [200, 200, 200, 403, 'masuk']],
    'dokumen: ubah draf sekategori orang lain' => ['/admin/dokumen/{draf-aset-lain}/edit', [200, 200, 403, 403, 'masuk']],
    // Di luar kategori: kueri resource sudah tersaring sehingga 404 (keberadaan dokumen tidak bocor).
    'dokumen: lihat di luar kategori' => ['/admin/dokumen/{draf-akreditasi}', [200, 200, 404, 403, 'masuk']],
    'dokumen: ubah di luar kategori' => ['/admin/dokumen/{draf-akreditasi}/edit', [200, 200, 404, 403, 'masuk']],
    'dokumen: ubah menunggu verifikasi' => ['/admin/dokumen/{menunggu}/edit', [200, 200, 403, 403, 'masuk']],
    'dokumen: ubah terbit' => ['/admin/dokumen/{terbit-aset}/edit', [200, 200, 403, 403, 'masuk']],
    // Verifikasi & tautan bermasalah (periksa ulang/ekstraksi ulang): admin saja.
    'antrean verifikasi' => ['/admin/antrean-verifikasi', [200, 200, 403, 403, 'masuk']],
    'tautan bermasalah' => ['/admin/tautan-bermasalah', [200, 200, 403, 403, 'masuk']],
    // Laporan: kontributor L (Kat); penggunaan API & evaluasi hanya admin.
    'laporan statistik' => ['/admin/laporan/statistik', [200, 200, 200, 403, 'masuk']],
    'laporan kelengkapan' => ['/admin/laporan/kelengkapan', [200, 200, 200, 403, 'masuk']],
    'laporan pemanfaatan' => ['/admin/laporan/pemanfaatan', [200, 200, 200, 403, 'masuk']],
    'laporan relasi status' => ['/admin/laporan/relasi-status', [200, 200, 200, 403, 'masuk']],
    'laporan penggunaan api' => ['/admin/laporan/penggunaan-api', [200, 200, 403, 403, 'masuk']],
    'laporan evaluasi: daftar' => ['/admin/laporan-evaluasi', [200, 200, 403, 403, 'masuk']],
    'laporan evaluasi: buat' => ['/admin/laporan-evaluasi/create', [200, 200, 403, 403, 'masuk']],
    'laporan evaluasi: lihat' => ['/admin/laporan-evaluasi/{laporan}', [200, 200, 403, 403, 'masuk']],
    'laporan evaluasi: pdf' => ['/laporan-evaluasi/{laporan}/pdf', [200, 200, 403, 403, 'masuk']],
    // Klien API & token: super-admin L B U H; admin-regulasi L.
    'klien api: daftar' => ['/admin/klien-api', [200, 200, 403, 403, 'masuk']],
    'klien api: lihat' => ['/admin/klien-api/{klien}', [200, 200, 403, 403, 'masuk']],
    'klien api: buat' => ['/admin/klien-api/create', [200, 403, 403, 403, 'masuk']],
    'klien api: ubah' => ['/admin/klien-api/{klien}/edit', [200, 403, 403, 403, 'masuk']],
    // Log aktivitas: super-admin & admin-regulasi L.
    'log aktivitas: daftar' => ['/admin/log-aktivitas', [200, 200, 403, 403, 'masuk']],
    'log aktivitas: lihat' => ['/admin/log-aktivitas/{aktivitas}', [200, 200, 403, 403, 'masuk']],
    // Portal publik: dokumen terbit publik untuk semua; internal hanya login; draf tidak pernah (BR-01, BR-02).
    'beranda' => ['/', [200, 200, 200, 200, 200]],
    'daftar dokumen' => ['/dokumen', [200, 200, 200, 200, 200]],
    'daftar per jenis' => ['/jenis/{jenis-slug}', [200, 200, 200, 200, 200]],
    'daftar per kategori' => ['/kategori/aset-bmn', [200, 200, 200, 200, 200]],
    'detail terbit publik' => ['/dokumen/{terbit-aset}/{terbit-aset-slug}', [200, 200, 200, 200, 200]],
    'detail terbit internal' => ['/dokumen/{terbit-internal}', [301, 301, 301, 301, 'masuk']],
    'detail draf' => ['/dokumen/{draf-milik}', [404, 404, 404, 404, 404]],
    'sitemap' => ['/sitemap.xml', [200, 200, 200, 200, 200]],
    // Buka tautan (BR-17): publik untuk semua; internal hanya login; draf hanya staf yang berhak melihat.
    'buka tautan publik' => ['/d/{tautan-publik}/buka', ['berkas', 'berkas', 'berkas', 'berkas', 'berkas']],
    'buka tautan internal' => ['/d/{tautan-internal}/buka', ['berkas', 'berkas', 'berkas', 'berkas', 'masuk']],
    'buka tautan draf milik' => ['/d/{tautan-draf}/buka', ['berkas', 'berkas', 'berkas', 404, 404]],
    'buka tautan draf luar kategori' => ['/d/{tautan-draf-lain}/buka', ['berkas', 'berkas', 404, 404, 404]],
    // API butuh token: sesi web tidak berlaku (BR-18).
    'api tanpa token' => ['/api/v1/dokumen', [401, 401, 401, 401, 401]],
    'api kesehatan' => ['/api/health', [200, 200, 200, 200, 200]],
]);

test('api: token tanpa ability internal tidak melihat dokumen internal, token internal melihatnya', function () {
    $klien = KlienApi::factory()->create();
    $biasa = $klien->createToken('biasa', ['dokumen:baca'])->plainTextToken;
    $internal = $klien->createToken('internal', ['dokumen:baca', 'dokumen:baca-internal'])->plainTextToken;
    $id = $this->pengganti['{terbit-internal}'];

    $this->withToken($biasa)->getJson("/api/v1/dokumen/{$id}")->assertNotFound();
    app('auth')->forgetGuards();
    $this->withToken($internal)->getJson("/api/v1/dokumen/{$id}")->assertOk();
});

test('api: token tanpa ability dokumen:baca ditolak dan metode tulis tidak tersedia', function () {
    $token = KlienApi::factory()->create()->createToken('salah', ['lain'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/dokumen')->assertForbidden();
    app('auth')->forgetGuards();

    $baca = KlienApi::factory()->create()->createToken('baca', ['dokumen:baca'])->plainTextToken;
    expect($this->withToken($baca)->postJson('/api/v1/dokumen', ['judul' => 'x'])->getStatusCode())->toBeIn([404, 405]);
    expect(Dokumen::query()->where('judul', 'x')->exists())->toBeFalse();
});

test('aksi verifikasi, arsip, status manual, relasi, dan ekspor mengikuti matriks', function () {
    $dokumen = Dokumen::query()->findOrFail($this->pengganti['{menunggu}']);
    $harapan = [
        // [verifikasi, arsipkan, ubahStatusBerlaku]
        'super-admin' => [true, true, true],
        'admin-regulasi' => [true, true, true],
        'kontributor' => [false, false, false],
        'pengguna' => [false, false, false],
    ];

    foreach ($harapan as $peran => [$verifikasi, $arsip, $status]) {
        $u = $this->aktor[$peran];

        expect($u->can('verifikasi', $dokumen))->toBe($verifikasi, "{$peran} verifikasi")
            ->and($u->can('arsipkan', $dokumen))->toBe($arsip, "{$peran} arsipkan")
            ->and($u->can('ubahStatusBerlaku', $dokumen))->toBe($status, "{$peran} status manual")
            ->and($u->can('relasi.kelola'))->toBe($verifikasi, "{$peran} relasi")
            ->and($u->can('laporan.ekspor'))->toBe($verifikasi, "{$peran} ekspor")
            ->and($u->can('klien-api.kelola'))->toBe($peran === 'super-admin', "{$peran} klien api")
            ->and($u->can('pengguna.kelola'))->toBe($peran === 'super-admin', "{$peran} pengguna");
    }

    $milik = Dokumen::query()->findOrFail($this->pengganti['{draf-milik}']);
    $lain = Dokumen::query()->findOrFail($this->pengganti['{draf-aset-lain}']);
    $kontributor = $this->aktor['kontributor'];

    expect($kontributor->can('ajukan', $milik))->toBeTrue()
        ->and($kontributor->can('ajukan', $lain))->toBeFalse()
        ->and($kontributor->can('delete', $milik))->toBeTrue()
        ->and($kontributor->can('delete', $lain))->toBeFalse()
        ->and($kontributor->can('restore', $milik))->toBeFalse()
        ->and($this->aktor['admin-regulasi']->can('restore', $milik))->toBeFalse()
        ->and($this->aktor['super-admin']->can('restore', $milik))->toBeTrue();
});
