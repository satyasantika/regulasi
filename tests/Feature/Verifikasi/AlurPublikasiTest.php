<?php

use App\Actions\Dokumen\AjukanVerifikasi;
use App\Actions\Dokumen\ArsipkanDokumen;
use App\Actions\Dokumen\PeriksaKelengkapan;
use App\Actions\Dokumen\PulihkanDokumen;
use App\Actions\Dokumen\TerbitkanDokumen;
use App\Actions\Dokumen\TolakDokumen;
use App\Enums\JenisRiwayatStatus;
use App\Enums\JenisTautan;
use App\Enums\KelompokJenisDokumen;
use App\Enums\PenyediaTautan;
use App\Enums\Peran;
use App\Enums\StatusPublikasi;
use App\Exceptions\RiwayatTidakDapatDiubah;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\RiwayatStatusDokumen;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Support\CacheRegulasi;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * Dokumen peraturan lengkap (BR-05) milik $pembuat di kategori $kategori.
 *
 * @param  array<string, mixed>  $atribut
 */
function dokumenLengkap(?User $pembuat = null, ?KategoriSistem $kategori = null, array $atribut = [], bool $naskah = true): Dokumen
{
    $dokumen = Dokumen::factory()->create(array_merge([
        'jenis_dokumen_id' => JenisDokumen::factory()->create(['kelompok' => KelompokJenisDokumen::Peraturan]),
        'tanggal_penetapan' => '2025-01-15',
        'dibuat_oleh' => $pembuat?->getKey(),
    ], $atribut));
    $dokumen->kategoriSistem()->attach($kategori ?? KategoriSistem::factory()->create());

    if ($naskah) {
        TautanBerkas::query()->create([
            'pemilik_type' => 'dokumen',
            'pemilik_id' => $dokumen->getKey(),
            'jenis' => JenisTautan::NaskahUtama,
            'url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz0'.fake()->unique()->numberBetween(10000, 99999).'/view',
            'penyedia' => PenyediaTautan::GoogleDrive,
        ]);
    }

    return $dokumen;
}

function kontributorPublikasi(KategoriSistem $kategori): User
{
    $kontributor = penggunaDenganPeran(Peran::Kontributor);
    $kontributor->kategoriSistem()->attach($kategori);

    return $kontributor;
}

test('kontributor mengajukan lalu admin menerbitkan: dua baris riwayat dan diverifikasi_oleh terisi', function () {
    $kategori = KategoriSistem::factory()->create();
    $kontributor = kontributorPublikasi($kategori);
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = dokumenLengkap($kontributor, $kategori);
    Cache::put('beranda:statistik', ['lama'], 3600);

    app(AjukanVerifikasi::class)->handle($dokumen, $kontributor);
    expect($dokumen->refresh()->status_publikasi)->toBe(StatusPublikasi::MenungguVerifikasi);

    app(TerbitkanDokumen::class)->handle($dokumen, $admin, 'Sudah sesuai naskah.');
    $dokumen->refresh();

    expect($dokumen->status_publikasi)->toBe(StatusPublikasi::Terbit)
        ->and($dokumen->diverifikasi_oleh)->toBe($admin->getKey())
        ->and($dokumen->diverifikasi_pada)->not->toBeNull()
        ->and($dokumen->dipublikasikan_pada)->not->toBeNull()
        ->and(Cache::has('beranda:statistik'))->toBeFalse();

    $riwayat = $dokumen->riwayatStatus()->get();

    expect($riwayat)->toHaveCount(2)
        ->and($riwayat->pluck('jenis')->unique()->all())->toBe([JenisRiwayatStatus::Publikasi])
        ->and($riwayat->map(fn (RiwayatStatusDokumen $r): string => "{$r->dari}>{$r->ke}")->sort()->values()->all())
        ->toBe(['draf>menunggu_verifikasi', 'menunggu_verifikasi>terbit'])
        ->and($riwayat->firstWhere('ke', 'menunggu_verifikasi')?->oleh)->toBe($kontributor->getKey())
        ->and($riwayat->firstWhere('ke', 'terbit')?->catatan)->toBe('Sudah sesuai naskah.');

    // BR-16: setelah diajukan/terbit, kontributor tidak dapat mengubah.
    expect($kontributor->can('update', $dokumen))->toBeFalse();
});

test('ajukan dokumen tanpa naskah utama gagal dengan daftar kekurangan', function () {
    $kategori = KategoriSistem::factory()->create();
    $kontributor = kontributorPublikasi($kategori);
    $dokumen = dokumenLengkap($kontributor, $kategori, ['tanggal_penetapan' => null], naskah: false);

    try {
        app(AjukanVerifikasi::class)->handle($dokumen, $kontributor);
        $this->fail('Seharusnya ValidationException.');
    } catch (ValidationException $e) {
        expect($e->errors()['kelengkapan'])->toContain('Tambahkan tautan berkas berjenis "Naskah Utama".')
            ->and(implode(' ', $e->errors()['kelengkapan']))->toContain('Tanggal penetapan wajib diisi');
    }

    expect($dokumen->refresh()->status_publikasi)->toBe(StatusPublikasi::Draf)
        ->and(RiwayatStatusDokumen::query()->count())->toBe(0);
});

test('dokumen rujukan tanpa berkas lengkap tanpa naskah utama dan tanggal penetapan', function () {
    $dokumen = dokumenLengkap(atribut: ['tanpa_berkas' => true, 'tanggal_penetapan' => null], naskah: false);

    expect(app(PeriksaKelengkapan::class)->handle($dokumen))->toBe([]);
});

test('jenis wajib nomor tanpa nomor tidak lengkap', function () {
    $dokumen = dokumenLengkap(atribut: ['nomor' => null]);

    expect(app(PeriksaKelengkapan::class)->handle($dokumen))->toHaveCount(1)
        ->and(app(PeriksaKelengkapan::class)->handle($dokumen)[0])->toContain('Nomor wajib diisi');
});

test('kontributor lain atau di luar status draf tidak dapat mengajukan', function () {
    $kategori = KategoriSistem::factory()->create();
    $pemilik = kontributorPublikasi($kategori);
    $lain = kontributorPublikasi($kategori);
    $dokumen = dokumenLengkap($pemilik, $kategori);

    expect(fn () => app(AjukanVerifikasi::class)->handle($dokumen, $lain))->toThrow(AuthorizationException::class);
});

test('tolak tanpa catatan atau catatan terlalu pendek gagal', function (string $catatan) {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = dokumenLengkap(atribut: ['status_publikasi' => StatusPublikasi::MenungguVerifikasi]);

    expect(fn () => app(TolakDokumen::class)->handle($dokumen, $admin, $catatan))->toThrow(ValidationException::class);
    expect($dokumen->refresh()->status_publikasi)->toBe(StatusPublikasi::MenungguVerifikasi);
})->with(['', '   ', 'pendek']);

test('tolak dengan catatan menyimpan catatan_verifikasi dan dokumen ditolak dapat diajukan ulang', function () {
    $kategori = KategoriSistem::factory()->create();
    $kontributor = kontributorPublikasi($kategori);
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = dokumenLengkap($kontributor, $kategori, ['status_publikasi' => StatusPublikasi::MenungguVerifikasi]);

    app(TolakDokumen::class)->handle($dokumen, $admin, 'Nomor tidak sesuai naskah asli.');

    expect($dokumen->refresh()->status_publikasi)->toBe(StatusPublikasi::Ditolak)
        ->and($dokumen->catatan_verifikasi)->toBe('Nomor tidak sesuai naskah asli.')
        ->and($kontributor->can('update', $dokumen))->toBeTrue();

    app(AjukanVerifikasi::class)->handle($dokumen, $kontributor);

    expect($dokumen->refresh()->status_publikasi)->toBe(StatusPublikasi::MenungguVerifikasi)
        ->and($dokumen->riwayatStatus()->count())->toBe(2);
});

test('kontributor tidak dapat menerbitkan, menolak, atau mengarsipkan', function () {
    $kategori = KategoriSistem::factory()->create();
    $kontributor = kontributorPublikasi($kategori);
    $dokumen = dokumenLengkap($kontributor, $kategori, ['status_publikasi' => StatusPublikasi::MenungguVerifikasi]);

    expect(fn () => app(TerbitkanDokumen::class)->handle($dokumen, $kontributor))->toThrow(AuthorizationException::class)
        ->and(fn () => app(TolakDokumen::class)->handle($dokumen, $kontributor, 'Catatan penolakan panjang'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ArsipkanDokumen::class)->handle($dokumen, $kontributor, 'Catatan pengarsipan panjang'))->toThrow(AuthorizationException::class);

    expect($dokumen->refresh()->status_publikasi)->toBe(StatusPublikasi::MenungguVerifikasi);
});

test('transisi tidak sah draf ke diarsipkan gagal', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = dokumenLengkap();

    expect(fn () => app(ArsipkanDokumen::class)->handle($dokumen, $admin, 'Salah input data dokumen'))
        ->toThrow(ValidationException::class);
    expect($dokumen->refresh()->status_publikasi)->toBe(StatusPublikasi::Draf)
        ->and(RiwayatStatusDokumen::query()->count())->toBe(0);
});

test('admin menerbitkan langsung dari draf; dipublikasikan_pada tidak berubah saat dipulihkan', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = dokumenLengkap(atribut: ['catatan_verifikasi' => 'catatan lama']);

    app(TerbitkanDokumen::class)->handle($dokumen, $admin);
    $dokumen->refresh();
    $pertama = $dokumen->dipublikasikan_pada;

    expect($dokumen->status_publikasi)->toBe(StatusPublikasi::Terbit)
        ->and($dokumen->catatan_verifikasi)->toBeNull();

    $this->travel(2)->days();
    app(ArsipkanDokumen::class)->handle($dokumen, $admin, 'Salah input, diganti dokumen lain');
    expect($dokumen->refresh()->status_publikasi)->toBe(StatusPublikasi::Diarsipkan);

    app(PulihkanDokumen::class)->handle($dokumen, $admin);
    $dokumen->refresh();

    expect($dokumen->status_publikasi)->toBe(StatusPublikasi::Terbit)
        ->and($dokumen->dipublikasikan_pada?->equalTo($pertama))->toBeTrue()
        ->and($dokumen->riwayatStatus()->pluck('ke')->all())->toBe(['terbit', 'diarsipkan', 'terbit'])
        ->and($dokumen->riwayatStatus()->first()?->labelDari())->toBe('Diarsipkan');
});

test('dokumen tidak lengkap tidak dapat diterbitkan admin', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = dokumenLengkap(naskah: false);

    expect(fn () => app(TerbitkanDokumen::class)->handle($dokumen, $admin))->toThrow(ValidationException::class);
    expect($dokumen->refresh()->status_publikasi)->toBe(StatusPublikasi::Draf);
});

test('penerbitan bersamaan: kunci publikasi dipegang proses lain menghasilkan LockTimeoutException tanpa riwayat', function () {
    config(['regulasi.kunci_publikasi.tunggu' => 0]);
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = dokumenLengkap(atribut: ['status_publikasi' => StatusPublikasi::MenungguVerifikasi]);

    $kunciLain = Cache::lock('lock:publikasi:'.$dokumen->getKey(), 10);
    expect($kunciLain->get())->toBeTrue();

    expect(fn () => app(TerbitkanDokumen::class)->handle($dokumen, $admin))->toThrow(LockTimeoutException::class);
    expect(RiwayatStatusDokumen::query()->count())->toBe(0)
        ->and($dokumen->refresh()->status_publikasi)->toBe(StatusPublikasi::MenungguVerifikasi);

    $kunciLain->release();
    app(TerbitkanDokumen::class)->handle($dokumen, $admin);

    // Penerbitan kedua (setelah yang pertama selesai) ditolak karena status sudah terbit: tetap satu riwayat.
    expect(fn () => app(TerbitkanDokumen::class)->handle($dokumen, $admin))->toThrow(ValidationException::class);
    expect(RiwayatStatusDokumen::query()->count())->toBe(1);
});

test('riwayat status tidak dapat diubah atau dihapus', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = dokumenLengkap();
    app(TerbitkanDokumen::class)->handle($dokumen, $admin);

    $riwayat = RiwayatStatusDokumen::query()->firstOrFail();

    expect(fn () => $riwayat->update(['catatan' => 'diubah']))->toThrow(RiwayatTidakDapatDiubah::class)
        ->and(fn () => $riwayat->delete())->toThrow(RiwayatTidakDapatDiubah::class)
        ->and(RiwayatStatusDokumen::query()->firstOrFail()->catatan)->toBeNull();
});

test('lupakanPublik membersihkan semua kunci publik', function () {
    foreach (CacheRegulasi::KUNCI_PUBLIK as $kunci) {
        Cache::put($kunci, 'x', 60);
    }

    CacheRegulasi::lupakanPublik();

    foreach (CacheRegulasi::KUNCI_PUBLIK as $kunci) {
        expect(Cache::has($kunci))->toBeFalse();
    }
});
