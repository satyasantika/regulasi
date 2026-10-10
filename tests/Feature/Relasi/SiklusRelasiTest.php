<?php

use App\Actions\Dokumen\ArsipkanDokumen;
use App\Actions\Dokumen\PulihkanDokumen;
use App\Actions\Dokumen\SimpanDokumen;
use App\Actions\Dokumen\TerbitkanDokumen;
use App\Actions\Relasi\HapusRelasiDokumen;
use App\Actions\Relasi\TambahRelasiDokumen;
use App\Actions\Relasi\UbahStatusBerlaku;
use App\Enums\JenisRelasi;
use App\Enums\JenisTautan;
use App\Enums\KelompokJenisDokumen;
use App\Enums\PenyediaTautan;
use App\Enums\Peran;
use App\Enums\StatusBerlaku;
use App\Enums\StatusPublikasi;
use App\Models\Dokumen;
use App\Models\DokumenRelasi;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\RiwayatStatusDokumen;
use App\Models\TautanBerkas;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/**
 * Dokumen peraturan yang memenuhi BR-05 (siap terbit).
 *
 * @param  array<string, mixed>  $atribut
 */
function dokumenSiklus(array $atribut = []): Dokumen
{
    $dokumen = Dokumen::factory()->create(array_merge([
        'jenis_dokumen_id' => JenisDokumen::factory()->create(['kelompok' => KelompokJenisDokumen::Peraturan]),
        'tanggal_penetapan' => '2025-01-15',
    ], $atribut));
    $dokumen->kategoriSistem()->attach(KategoriSistem::factory()->create());

    TautanBerkas::query()->create([
        'pemilik_type' => 'dokumen',
        'pemilik_id' => $dokumen->getKey(),
        'jenis' => JenisTautan::NaskahUtama,
        'url' => 'https://peraturan.go.id/naskah-'.fake()->unique()->numberBetween(10000, 99999).'.pdf',
        'penyedia' => PenyediaTautan::SumberResmi,
    ]);

    return $dokumen;
}

function relasikan(Dokumen $sumber, Dokumen $target, JenisRelasi $jenis, User $oleh): DokumenRelasi
{
    return app(TambahRelasiDokumen::class)->handle($sumber, $target, $jenis, null, $oleh);
}

beforeEach(function () {
    $this->admin = penggunaDenganPeran(Peran::AdminRegulasi);
});

test('hapus relasi mencabut: kedua baris hilang, B kembali berlaku, alasan tercatat', function () {
    $a = Dokumen::factory()->terbit()->create();
    $b = Dokumen::factory()->terbit()->create();
    $relasi = relasikan($a, $b, JenisRelasi::Mencabut, $this->admin);
    expect($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Dicabut);

    // Dihapus dari sisi B (baris dicabut_oleh) tetap menghapus pasangannya.
    app(HapusRelasiDokumen::class)->handle($relasi->pasangan()->firstOrFail(), $this->admin, 'Relasi keliru dicatat');

    expect(DokumenRelasi::count())->toBe(0)
        ->and($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Berlaku)
        ->and(RiwayatStatusDokumen::query()->where('dokumen_id', $b->id)->count())->toBe(2)
        ->and(RiwayatStatusDokumen::query()->where('dokumen_id', $b->id)->whereNotNull('dokumen_relasi_id')->count())->toBe(0)
        ->and(Activity::query()->where('event', 'relasi-dihapus')->sole()->properties['alasan'])->toBe('Relasi keliru dicatat');
});

test('hapus relasi tanpa alasan atau oleh kontributor ditolak', function () {
    $relasi = relasikan(Dokumen::factory()->terbit()->create(), Dokumen::factory()->terbit()->create(), JenisRelasi::Mencabut, $this->admin);

    expect(fn () => app(HapusRelasiDokumen::class)->handle($relasi, $this->admin, ' '))->toThrow(ValidationException::class)
        ->and(fn () => app(HapusRelasiDokumen::class)->handle($relasi, penggunaDenganPeran(Peran::Kontributor), 'Alasan cukup'))->toThrow(AuthorizationException::class)
        ->and(DokumenRelasi::count())->toBe(2);
});

test('B dengan dua pengubah dan satu pencabut: hapus pencabut menjadikannya diubah', function () {
    $b = Dokumen::factory()->terbit()->create();
    relasikan(Dokumen::factory()->terbit()->create(), $b, JenisRelasi::Mengubah, $this->admin);
    relasikan(Dokumen::factory()->terbit()->create(), $b, JenisRelasi::Mengubah, $this->admin);
    $pencabut = relasikan(Dokumen::factory()->terbit()->create(), $b, JenisRelasi::Mencabut, $this->admin);
    expect($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Dicabut);

    app(HapusRelasiDokumen::class)->handle($pencabut, $this->admin, 'Bukan pencabut yang benar');

    expect($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Diubah)
        ->and(DokumenRelasi::count())->toBe(4);
});

test('status manual tidak_berlaku bertahan saat relasi ditambah/dihapus, lalu kembalikan otomatis menghitung ulang', function () {
    $b = Dokumen::factory()->terbit()->create();

    app(UbahStatusBerlaku::class)->handle($b, StatusBerlaku::TidakBerlaku, $this->admin, 'SK berjangka sudah berakhir.');
    expect($b->refresh()->status_berlaku)->toBe(StatusBerlaku::TidakBerlaku)->and($b->status_berlaku_manual)->toBeTrue();

    $relasi = relasikan(Dokumen::factory()->terbit()->create(), $b, JenisRelasi::Mengubah, $this->admin);
    expect($b->refresh()->status_berlaku)->toBe(StatusBerlaku::TidakBerlaku);

    app(HapusRelasiDokumen::class)->handle($relasi, $this->admin, 'Relasi keliru');
    relasikan(Dokumen::factory()->terbit()->create(), $b, JenisRelasi::Mencabut, $this->admin);
    expect($b->refresh()->status_berlaku)->toBe(StatusBerlaku::TidakBerlaku);

    app(UbahStatusBerlaku::class)->handle($b, StatusBerlaku::Berlaku, $this->admin, 'Kembalikan ke perhitungan otomatis.');

    $terakhir = RiwayatStatusDokumen::query()->where('dokumen_id', $b->id)->orderByDesc('created_at')->orderByDesc('id')->first();
    expect($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Dicabut)
        ->and($b->status_berlaku_manual)->toBeFalse()
        ->and($terakhir->catatan)->toBe('Kembalikan ke perhitungan otomatis.')
        ->and($terakhir->oleh)->toBe($this->admin->id);
});

test('status manual: catatan wajib, hanya admin, dan dicabut/diubah tidak dapat dipilih', function () {
    $b = Dokumen::factory()->terbit()->create();

    expect(fn () => app(UbahStatusBerlaku::class)->handle($b, StatusBerlaku::TidakBerlaku, $this->admin, 'pendek'))->toThrow(ValidationException::class)
        ->and(fn () => app(UbahStatusBerlaku::class)->handle($b, StatusBerlaku::Dicabut, $this->admin, 'Catatan yang cukup panjang'))->toThrow(ValidationException::class)
        ->and(fn () => app(UbahStatusBerlaku::class)->handle($b, StatusBerlaku::TidakBerlaku, penggunaDenganPeran(Peran::Kontributor), 'Catatan yang cukup panjang'))->toThrow(AuthorizationException::class)
        ->and($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Berlaku);
});

test('sumber draf lalu diterbitkan menjadikan B dicabut; diarsipkan mengembalikan, dipulihkan mencabut lagi', function () {
    $a = dokumenSiklus();
    $b = Dokumen::factory()->terbit()->create();
    $relasi = relasikan($a, $b, JenisRelasi::Mencabut, $this->admin);
    expect($relasi->efektif)->toBeFalse()->and($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Berlaku);

    app(TerbitkanDokumen::class)->handle($a, $this->admin);
    expect($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Dicabut)
        ->and($relasi->refresh()->efektif)->toBeTrue()
        ->and($relasi->pasangan()->firstOrFail()->efektif)->toBeTrue();

    app(ArsipkanDokumen::class)->handle($a, $this->admin, 'Diarsipkan karena salah unggah.');
    expect($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Berlaku)
        ->and($relasi->refresh()->efektif)->toBeFalse();

    app(PulihkanDokumen::class)->handle($a, $this->admin);
    expect($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Dicabut);
});

test('mengubah tanggal berlaku dokumen terbit menerapkan ulang relasinya', function () {
    $a = dokumenSiklus();
    app(TerbitkanDokumen::class)->handle($a, $this->admin);
    $b = Dokumen::factory()->terbit()->create();
    relasikan($a, $b, JenisRelasi::Mencabut, $this->admin);
    expect($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Dicabut);

    $a->refresh()->load('kategoriSistem');
    app(SimpanDokumen::class)->handle([
        'tanggal_berlaku' => today()->addDays(10)->toDateString(),
        'kategori_sistem' => $a->kategoriSistem->pluck('id')->all(),
    ], $this->admin, $a);

    expect($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Berlaku)
        ->and(DokumenRelasi::query()->where('dokumen_id', $a->id)->sole()->efektif_pada->toDateString())->toBe(today()->addDays(10)->toDateString());
});

test('tanggal berlaku sumber +3 hari diterapkan oleh perintah terjadwal setelah waktunya tiba', function () {
    $a = dokumenSiklus(['tanggal_berlaku' => today()->addDays(3)->toDateString()]);
    app(TerbitkanDokumen::class)->handle($a, $this->admin);
    $b = Dokumen::factory()->terbit()->create();
    $relasi = relasikan($a, $b, JenisRelasi::Mencabut, $this->admin);
    expect($relasi->efektif)->toBeFalse();

    $this->artisan('regulasi:terapkan-status')->expectsOutputToContain('0 relasi diterapkan')->assertSuccessful();
    expect($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Berlaku);

    $this->travel(3)->days();

    $this->artisan('regulasi:terapkan-status')->expectsOutputToContain('1 relasi diterapkan')->assertSuccessful();
    expect($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Dicabut)
        ->and(RiwayatStatusDokumen::query()->where('dokumen_id', $b->id)->sole()->oleh)->toBeNull();
});

test('--dry-run mencetak relasi tertunda tanpa mengubah apa pun', function () {
    $a = dokumenSiklus(['tanggal_berlaku' => today()->addDays(3)->toDateString()]);
    app(TerbitkanDokumen::class)->handle($a, $this->admin);
    $b = Dokumen::factory()->terbit()->create();
    relasikan($a, $b, JenisRelasi::Mencabut, $this->admin);
    $this->travel(5)->days();

    $this->artisan('regulasi:terapkan-status', ['--dry-run' => true])
        ->expectsOutputToContain($a->sebutan_singkat)
        ->expectsOutputToContain('1 relasi tertunda')
        ->assertSuccessful();

    expect($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Berlaku)
        ->and(DokumenRelasi::query()->where('efektif', true)->exists())->toBeFalse()
        ->and($a->refresh()->status_publikasi)->toBe(StatusPublikasi::Terbit);
});
