<?php

use App\Actions\Relasi\TambahRelasiDokumen;
use App\Enums\JenisRelasi;
use App\Enums\JenisRiwayatStatus;
use App\Enums\Peran;
use App\Enums\StatusBerlaku;
use App\Events\StatusBerlakuBerubah;
use App\Models\Dokumen;
use App\Models\DokumenRelasi;
use App\Models\RiwayatStatusDokumen;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function adminRelasi(): User
{
    return penggunaDenganPeran(Peran::AdminRegulasi);
}

function tambahRelasi(Dokumen $sumber, Dokumen $target, JenisRelasi $jenis, ?User $oleh = null, ?string $keterangan = null): DokumenRelasi
{
    return app(TambahRelasiDokumen::class)->handle($sumber, $target, $jenis, $keterangan, $oleh ?? adminRelasi());
}

test('A mencabut B membuat dua baris saling berpasangan, B dicabut, dan riwayat B mencatat relasi pemicu', function () {
    $admin = adminRelasi();
    $a = Dokumen::factory()->terbit()->create(['tanggal_penetapan' => '2025-06-16', 'tanggal_berlaku' => '2025-08-18']);
    $b = Dokumen::factory()->terbit()->create(['tanggal_penetapan' => '2019-01-01']);
    Cache::put('beranda:statistik', ['lama'], 3600);

    $relasi = tambahRelasi($a, $b, JenisRelasi::Mencabut, $admin, 'Pasal penutup');

    expect(DokumenRelasi::count())->toBe(2);
    $pasangan = DokumenRelasi::query()->whereKey($relasi->pasangan_id)->firstOrFail();

    expect($relasi->dokumen_id)->toBe($a->id)
        ->and($relasi->jenis_relasi)->toBe(JenisRelasi::Mencabut)
        ->and($relasi->efektif)->toBeTrue()
        ->and($relasi->efektif_pada->toDateString())->toBe('2025-08-18')
        ->and($pasangan->dokumen_id)->toBe($b->id)
        ->and($pasangan->jenis_relasi)->toBe(JenisRelasi::DicabutOleh)
        ->and($pasangan->pasangan_id)->toBe($relasi->id)
        ->and($pasangan->keterangan)->toBe('Pasal penutup')
        ->and($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Dicabut)
        ->and(Cache::has('beranda:statistik'))->toBeFalse();

    $riwayat = RiwayatStatusDokumen::query()->where('dokumen_id', $b->id)->sole();
    expect($riwayat->jenis)->toBe(JenisRiwayatStatus::Berlaku)
        ->and($riwayat->dari)->toBe('berlaku')
        ->and($riwayat->ke)->toBe('dicabut')
        ->and($riwayat->dokumen_relasi_id)->toBe($pasangan->id)
        ->and($riwayat->oleh)->toBe($admin->id);
});

test('memilih dicabut_oleh dari sisi B menghasilkan data identik', function () {
    $a = Dokumen::factory()->terbit()->create();
    $b = Dokumen::factory()->terbit()->create();

    $relasi = tambahRelasi($b, $a, JenisRelasi::DicabutOleh);

    expect($relasi->dokumen_id)->toBe($a->id)
        ->and($relasi->dokumen_terkait_id)->toBe($b->id)
        ->and($relasi->jenis_relasi)->toBe(JenisRelasi::Mencabut)
        ->and(DokumenRelasi::query()->where('dokumen_id', $b->id)->sole()->jenis_relasi)->toBe(JenisRelasi::DicabutOleh)
        ->and($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Dicabut)
        ->and($a->refresh()->status_berlaku)->toBe(StatusBerlaku::Berlaku);
});

test('mengubah membuat target diubah, lalu pencabutan menjadikannya dicabut', function () {
    Event::fake([StatusBerlakuBerubah::class]);
    $pengubah = Dokumen::factory()->terbit()->create();
    $pencabut = Dokumen::factory()->terbit()->create();
    $b = Dokumen::factory()->terbit()->create();

    tambahRelasi($pengubah, $b, JenisRelasi::Mengubah);
    expect($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Diubah);

    tambahRelasi($pencabut, $b, JenisRelasi::Mencabut);
    expect($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Dicabut)
        ->and(RiwayatStatusDokumen::query()->where('dokumen_id', $b->id)->count())->toBe(2);

    Event::assertDispatchedTimes(StatusBerlakuBerubah::class, 2);
    Event::assertDispatched(StatusBerlakuBerubah::class, fn (StatusBerlakuBerubah $e): bool => $e->dari === StatusBerlaku::Diubah && $e->ke === StatusBerlaku::Dicabut);
});

test('melaksanakan tidak mengubah status target', function () {
    $a = Dokumen::factory()->terbit()->create();
    $b = Dokumen::factory()->terbit()->create();

    tambahRelasi($a, $b, JenisRelasi::Melaksanakan);

    expect($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Berlaku)
        ->and(DokumenRelasi::query()->where('dokumen_id', $b->id)->sole()->jenis_relasi)->toBe(JenisRelasi::DilaksanakanOleh);
});

test('relasi ke diri sendiri, ganda, siklik, dan sumber lebih lama ditolak', function () {
    $admin = adminRelasi();
    $a = Dokumen::factory()->terbit()->create(['tanggal_penetapan' => '2025-01-01']);
    $b = Dokumen::factory()->terbit()->create(['tanggal_penetapan' => '2020-01-01']);

    expect(fn () => tambahRelasi($a, $a, JenisRelasi::Mencabut, $admin))->toThrow(ValidationException::class);

    tambahRelasi($a, $b, JenisRelasi::Mencabut, $admin);

    expect(fn () => tambahRelasi($a, $b, JenisRelasi::Mencabut, $admin))->toThrow(ValidationException::class, 'sudah ada')
        ->and(fn () => tambahRelasi($b, $a, JenisRelasi::DicabutOleh, $admin))->toThrow(ValidationException::class, 'sudah ada')
        ->and(fn () => tambahRelasi($b, $a, JenisRelasi::Mencabut, $admin))->toThrow(ValidationException::class)
        ->and(fn () => tambahRelasi($b, $a, JenisRelasi::Mengubah, $admin))->toThrow(ValidationException::class, 'lebih awal');

    $c = Dokumen::factory()->terbit()->create();
    $d = Dokumen::factory()->terbit()->create();
    tambahRelasi($c, $d, JenisRelasi::Mencabut, $admin);
    expect(fn () => tambahRelasi($d, $c, JenisRelasi::Mencabut, $admin))->toThrow(ValidationException::class, 'siklik');

    expect(DokumenRelasi::count())->toBe(4);
});

test('sumber masih draf: relasi tidak efektif dan target tetap berlaku', function () {
    $a = Dokumen::factory()->create();
    $b = Dokumen::factory()->terbit()->create();

    $relasi = tambahRelasi($a, $b, JenisRelasi::Mencabut);

    expect($relasi->efektif)->toBeFalse()
        ->and($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Berlaku)
        ->and(RiwayatStatusDokumen::query()->where('dokumen_id', $b->id)->exists())->toBeFalse();
});

test('tanggal berlaku sumber besok: relasi belum efektif', function () {
    $a = Dokumen::factory()->terbit()->create(['tanggal_berlaku' => today()->addDay()]);
    $b = Dokumen::factory()->terbit()->create();

    $relasi = tambahRelasi($a, $b, JenisRelasi::Mencabut);

    expect($relasi->efektif)->toBeFalse()
        ->and($relasi->efektif_pada->toDateString())->toBe(today()->addDay()->toDateString())
        ->and($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Berlaku);
});

test('status manual tidak_berlaku pada target tidak ditimpa', function () {
    $a = Dokumen::factory()->terbit()->create();
    $b = Dokumen::factory()->terbit()->create();
    $b->forceFill(['status_berlaku' => StatusBerlaku::TidakBerlaku, 'status_berlaku_manual' => true])->save();

    tambahRelasi($a, $b, JenisRelasi::Mencabut);

    expect($b->refresh()->status_berlaku)->toBe(StatusBerlaku::TidakBerlaku);
});

test('kontributor tidak boleh menambah relasi', function () {
    $kontributor = penggunaDenganPeran(Peran::Kontributor);
    $a = Dokumen::factory()->terbit()->create();
    $b = Dokumen::factory()->terbit()->create();

    expect(fn () => tambahRelasi($a, $b, JenisRelasi::Mencabut, $kontributor))->toThrow(AuthorizationException::class)
        ->and(DokumenRelasi::count())->toBe(0);
});

test('kegagalan setelah baris pertama disisipkan membatalkan seluruh transaksi', function () {
    $admin = adminRelasi();
    $a = Dokumen::factory()->terbit()->create();
    $b = Dokumen::factory()->terbit()->create();

    DokumenRelasi::creating(function (DokumenRelasi $relasi): void {
        if ($relasi->jenis_relasi === JenisRelasi::DicabutOleh) {
            throw new RuntimeException('Gagal disengaja');
        }
    });

    expect(fn () => tambahRelasi($a, $b, JenisRelasi::Mencabut, $admin))->toThrow(RuntimeException::class, 'Gagal disengaja');

    expect(DokumenRelasi::count())->toBe(0)
        ->and($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Berlaku)
        ->and(Cache::lock('lock:status-dokumen:'.$a->id, 1)->get())->toBeTrue();
});
