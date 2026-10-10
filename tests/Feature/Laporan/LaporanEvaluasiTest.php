<?php

use App\Actions\Laporan\FinalkanLaporan;
use App\Actions\Laporan\HitungRingkasanLaporan;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Exceptions\LaporanFinalTidakDapatDiubah;
use App\Filament\Admin\Resources\LaporanEvaluasiResource\Pages\CreateLaporanEvaluasi;
use App\Filament\Admin\Resources\LaporanEvaluasiResource\Pages\ViewLaporanEvaluasi;
use App\Models\Dokumen;
use App\Models\LaporanEvaluasi;
use App\Models\User;
use App\Services\Berkas\PengekstrakTeksPdf;
use App\Support\Laporan\RingkasanEvaluasi;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function laporanEvaluasi(User $pembuat, array $atribut = []): LaporanEvaluasi
{
    $laporan = new LaporanEvaluasi([
        'judul' => 'Laporan Evaluasi Semester Ganjil 2026/2027',
        'periode_mulai' => '2026-08-01',
        'periode_selesai' => '2027-01-31',
        'analisis' => "Sebagian dokumen lama belum berabstrak.\nTautan Drive lama perlu dipindahkan.",
        'tindak_lanjut' => 'Melengkapi abstrak dokumen akreditasi.',
        'dibuat_oleh' => $pembuat->id,
        ...$atribut,
    ]);
    $laporan->forceFill(['ringkasan' => RingkasanEvaluasi::hitung($laporan->periode_mulai, $laporan->periode_selesai)])->save();

    return $laporan;
}

/**
 * Berkas PDF di storage, kecuali storage/app/tmp/ekstraksi yang dipakai sesaat oleh uji ekstraksi paralel.
 *
 * @return list<string>
 */
function berkasStorage(): array
{
    return collect(File::allFiles(storage_path()))
        ->map(fn (SplFileInfo $f): string => $f->getPathname())
        ->filter(fn (string $jalur): bool => str_ends_with(strtolower($jalur), '.pdf')
            && ! str_starts_with($jalur, storage_path('app/tmp/ekstraksi')))
        ->sort()
        ->values()
        ->all();
}

test('ringkasan menghitung penambahan periode dengan benar', function () {
    Dokumen::factory()->terbit()->create(['dipublikasikan_pada' => '2026-08-01 08:00:00']);
    Dokumen::factory()->terbit()->create(['dipublikasikan_pada' => '2027-01-31 23:00:00']);
    Dokumen::factory()->terbit()->create(['dipublikasikan_pada' => '2026-07-31 23:59:00']);
    Dokumen::factory()->terbit()->create(['dipublikasikan_pada' => '2027-02-01 00:01:00']);
    Dokumen::factory()->create();

    $ringkasan = RingkasanEvaluasi::hitung(CarbonImmutable::parse('2026-08-01'), CarbonImmutable::parse('2027-01-31'));

    expect($ringkasan['penambahan']['jumlah'])->toBe(2)
        ->and($ringkasan['koleksi']['total_terbit'])->toBe(4)
        ->and(array_sum(array_column($ringkasan['penambahan']['per_jenis'], 'jumlah')))->toBe(2)
        ->and($ringkasan)->toHaveKeys(['koleksi', 'penambahan', 'perubahan_status', 'kelengkapan', 'kesehatan_tautan', 'pembukaan', 'penggunaan_api']);
});

test('admin membuat laporan lewat form dan ringkasan terisi', function () {
    Dokumen::factory()->terbit()->create(['dipublikasikan_pada' => '2026-09-01 10:00:00']);
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);

    Livewire::actingAs($admin)->test(CreateLaporanEvaluasi::class)
        ->fillForm([
            'judul' => 'Laporan Evaluasi Semester Ganjil 2026/2027',
            'periode_mulai' => '2026-08-01',
            'periode_selesai' => '2027-01-31',
            'analisis' => 'Temuan.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $laporan = LaporanEvaluasi::query()->sole();
    expect($laporan->status)->toBe(StatusLaporan::Draf)
        ->and($laporan->dibuat_oleh)->toBe($admin->id)
        ->and($laporan->ringkasan['penambahan']['jumlah'])->toBe(1);

    Livewire::actingAs($admin)->test(CreateLaporanEvaluasi::class)
        ->fillForm(['judul' => 'Salah', 'periode_mulai' => '2027-01-31', 'periode_selesai' => '2026-08-01'])
        ->call('create')
        ->assertHasFormErrors(['periode_selesai']);
});

test('laporan final tidak dapat diedit atau dihitung ulang', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $super = penggunaDenganPeran(Peran::SuperAdmin);
    $laporan = laporanEvaluasi($admin);

    Livewire::actingAs($admin)->test(ViewLaporanEvaluasi::class, ['record' => $laporan->id])
        ->assertActionVisible('hitungRingkasan')
        ->callAction('finalkan')
        ->assertActionHidden('finalkan')
        ->assertActionHidden('hitungRingkasan')
        ->assertActionHidden('edit')
        ->assertActionVisible('unduhPdf');

    $laporan->refresh();
    expect($laporan->status)->toBe(StatusLaporan::Final)
        ->and($laporan->difinalkan_pada)->not->toBeNull();

    $this->actingAs($admin)->get("/admin/laporan-evaluasi/{$laporan->id}/edit")->assertForbidden();
    $this->actingAs($super)->get("/admin/laporan-evaluasi/{$laporan->id}/edit")->assertForbidden();

    expect(fn () => app(HitungRingkasanLaporan::class)->handle($laporan, $admin))->toThrow(AuthorizationException::class)
        ->and(fn () => app(HitungRingkasanLaporan::class)->handle($laporan, $super))->toThrow(LaporanFinalTidakDapatDiubah::class)
        ->and(fn () => app(FinalkanLaporan::class)->handle($laporan, $super))->toThrow(LaporanFinalTidakDapatDiubah::class)
        ->and(fn () => $laporan->update(['judul' => 'Diubah']))->toThrow(LaporanFinalTidakDapatDiubah::class)
        ->and(fn () => $laporan->delete())->toThrow(LaporanFinalTidakDapatDiubah::class)
        ->and($laporan->refresh()->judul)->toBe('Laporan Evaluasi Semester Ganjil 2026/2027');
});

test('unduh PDF menghasilkan application/pdf berisi enam bab tanpa berkas tersimpan', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $laporan = laporanEvaluasi($admin);
    app(FinalkanLaporan::class)->handle($laporan, $admin);
    $sebelum = berkasStorage();

    $respons = $this->actingAs($admin)->get("/laporan-evaluasi/{$laporan->id}/pdf");

    $respons->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $isi = $respons->streamedContent();
    expect($isi)->toStartWith('%PDF')
        ->and(berkasStorage())->toBe($sebelum);

    $sementara = tempnam(sys_get_temp_dir(), 'uji-evaluasi-');

    try {
        file_put_contents($sementara, $isi);
        $teks = preg_replace('/\s+/u', ' ', app(PengekstrakTeksPdf::class)->teks($sementara));
    } finally {
        @unlink($sementara);
    }

    expect($teks)->toContain('Fakultas Keguruan dan Ilmu Pendidikan')
        ->toContain('BAB I PENDAHULUAN')
        ->toContain('BAB II KOLEKSI')
        ->toContain('BAB III PENGOLAHAN DAN KELENGKAPAN')
        ->toContain('BAB IV PEMANFAATAN')
        ->toContain('BAB V KENDALA')
        ->toContain('BAB VI RENCANA TINDAK LANJUT')
        ->toContain('LAMPIRAN')
        ->toContain('Tautan Drive lama perlu dipindahkan.')
        ->not->toContain('DRAF');
});

test('kontributor tidak dapat membuat maupun mengunduh laporan evaluasi', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $laporan = laporanEvaluasi($admin);
    $kontributor = penggunaDenganPeran(Peran::Kontributor);

    $this->actingAs($kontributor)->get('/admin/laporan-evaluasi/create')->assertForbidden();
    $this->actingAs($kontributor)->get('/admin/laporan-evaluasi')->assertForbidden();
    $this->actingAs($kontributor)->get("/laporan-evaluasi/{$laporan->id}/pdf")->assertForbidden();
    $this->actingAs(penggunaDenganPeran(Peran::Pengguna))->get("/laporan-evaluasi/{$laporan->id}/pdf")->assertForbidden();

    expect($kontributor->can('create', LaporanEvaluasi::class))->toBeFalse()
        ->and(fn () => app(HitungRingkasanLaporan::class)->handle($laporan, $kontributor))->toThrow(AuthorizationException::class);
});
