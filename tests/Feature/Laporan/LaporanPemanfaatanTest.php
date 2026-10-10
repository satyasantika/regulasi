<?php

use App\Actions\Relasi\TambahRelasiDokumen;
use App\Enums\JenisRelasi;
use App\Enums\JenisTautan;
use App\Enums\PenyediaTautan;
use App\Enums\Peran;
use App\Filament\Admin\Pages\LaporanPemanfaatan;
use App\Filament\Admin\Pages\LaporanPenggunaanApi;
use App\Filament\Admin\Pages\LaporanRelasiStatus;
use App\Filament\Admin\Widgets\PembukaanHarianChart;
use App\Jobs\BuatEksporLaporan;
use App\Models\Dokumen;
use App\Models\DokumenRelasi;
use App\Models\KategoriSistem;
use App\Models\KlienApi;
use App\Models\LogBukaTautan;
use App\Models\TautanBerkas;
use App\Support\Laporan\LingkupLaporan;
use App\Support\Laporan\PembukaanTautan;
use Database\Seeders\KategoriSistemSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    // Direktori ekspor terpisah per proses uji paralel.
    config(['regulasi.direktori_ekspor' => 'ekspor-uji-'.(ParallelTesting::token() ?: '0')]);
    $this->seed(KategoriSistemSeeder::class);
});

afterEach(function () {
    File::deleteDirectory(storage_path('app/tmp/'.BuatEksporLaporan::direktori()));
});

function pemanfaatanDokumen(string $kategori = 'aset'): Dokumen
{
    $dokumen = Dokumen::factory()->terbit()->create();
    $dokumen->kategoriSistem()->attach(KategoriSistem::where('kode', $kategori)->value('id'));
    TautanBerkas::query()->create([
        'pemilik_type' => 'dokumen',
        'pemilik_id' => $dokumen->id,
        'jenis' => JenisTautan::NaskahUtama,
        'url' => 'https://lppm.unsil.ac.id/'.fake()->uuid().'.pdf',
        'penyedia' => PenyediaTautan::Unsil,
    ]);

    return $dokumen;
}

function pemanfaatanBuka(Dokumen $dokumen, int $kali, string $saluran = 'web', ?KlienApi $klien = null): void
{
    $tautanId = TautanBerkas::query()->where('pemilik_id', $dokumen->id)->value('id');

    for ($i = 0; $i < $kali; $i++) {
        LogBukaTautan::query()->create([
            'dokumen_id' => $dokumen->id,
            'tautan_berkas_id' => $tautanId,
            'klien_api_id' => $klien?->id,
            'saluran' => $saluran,
            'ip_hash' => 'hash-rahasia-'.$i,
            'user_agent' => 'AgenRahasia/1.0',
        ]);
    }
}

test('penjumlahan per saluran benar dan sama dengan COUNT log', function () {
    $a = pemanfaatanDokumen();
    $b = pemanfaatanDokumen('akreditasi');
    pemanfaatanBuka($a, 3);
    pemanfaatanBuka($a, 2, 'api');
    pemanfaatanBuka($b, 1);

    $total = PembukaanTautan::total(LingkupLaporan::global(), PembukaanTautan::saringan([]));
    expect($total)->toBe(['web' => 4, 'api' => 2, 'total' => 6])
        ->and($total['total'])->toBe((int) DB::table('log_buka_tautan')->where('created_at', '>=', now()->subDays(29)->startOfDay())->count());

    $this->actingAs(penggunaDenganPeran(Peran::AdminRegulasi));
    Livewire::test(LaporanPemanfaatan::class)
        ->assertCanSeeTableRecords([$a, $b], inOrder: true)
        ->assertTableColumnStateSet('dibuka_web', 3, $a)
        ->assertTableColumnStateSet('dibuka_api', 2, $a)
        ->assertTableColumnStateSet('dibuka_total', 5, $a)
        ->assertDontSee('hash-rahasia')
        ->assertDontSee('AgenRahasia')
        ->filterTable('saluran', 'api')
        ->assertCanSeeTableRecords([$a])
        ->assertCanNotSeeTableRecords([$b]);

    Livewire::test(PembukaanHarianChart::class, ['tableFilters' => []])->assertOk();

    foreach (['pemanfaatan', 'relasi-status', 'statistik', 'kelengkapan'] as $halaman) {
        $this->get("/admin/laporan/{$halaman}")->assertOk();
    }

    $this->get('/admin/laporan/pemanfaatan')->assertSeeLivewire(PembukaanHarianChart::class);
});

test('rentang tanggal bekerja', function () {
    $lama = pemanfaatanDokumen();
    $baru = pemanfaatanDokumen();

    $this->travel(-40)->days();
    pemanfaatanBuka($lama, 2);
    $this->travelBack();
    pemanfaatanBuka($baru, 1);

    $this->actingAs(penggunaDenganPeran(Peran::AdminRegulasi));
    Livewire::test(LaporanPemanfaatan::class)
        ->assertCanSeeTableRecords([$baru])
        ->assertCanNotSeeTableRecords([$lama])
        ->filterTable('periode', ['dari' => now()->subDays(45)->toDateString(), 'sampai' => now()->subDays(35)->toDateString()])
        ->assertCanSeeTableRecords([$lama])
        ->assertCanNotSeeTableRecords([$baru])
        ->assertTableColumnStateSet('dibuka_total', 2, $lama);
});

test('kontributor hanya melihat pembukaan dokumen kategorinya', function () {
    $aset = pemanfaatanDokumen('aset');
    $akreditasi = pemanfaatanDokumen('akreditasi');
    pemanfaatanBuka($aset, 1);
    pemanfaatanBuka($akreditasi, 1);
    $kontributor = penggunaDenganPeran(Peran::Kontributor);
    $kontributor->kategoriSistem()->attach(KategoriSistem::where('kode', 'aset')->value('id'));

    $this->actingAs($kontributor);
    Livewire::test(LaporanPemanfaatan::class)
        ->assertCanSeeTableRecords([$aset])
        ->assertCanNotSeeTableRecords([$akreditasi])
        ->assertActionHidden('eksporExcel');
});

test('relasi tertunda tampil beserta tanggal efektifnya', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $target = Dokumen::factory()->terbit()->create(['tanggal_penetapan' => '2019-01-01']);
    $pencabut = Dokumen::factory()->terbit()->create(['tanggal_penetapan' => '2025-01-01']);
    $pengubahMasaDepan = Dokumen::factory()->terbit()->create(['tanggal_penetapan' => now()->toDateString(), 'tanggal_berlaku' => now()->addMonth()->toDateString()]);
    $diubah = Dokumen::factory()->terbit()->create(['tanggal_penetapan' => '2020-01-01']);

    $efektif = app(TambahRelasiDokumen::class)->handle($pencabut, $target, JenisRelasi::Mencabut, null, $admin);
    $tertunda = app(TambahRelasiDokumen::class)->handle($pengubahMasaDepan, $diubah, JenisRelasi::Mengubah, null, $admin);

    expect($tertunda->efektif)->toBeFalse();
    $sisiTarget = DokumenRelasi::query()->whereKey($tertunda->pasangan_id)->sole();

    $this->actingAs($admin);
    Livewire::test(LaporanRelasiStatus::class)
        ->assertCanSeeTableRecords([$efektif, $tertunda])
        ->assertCanNotSeeTableRecords([$sisiTarget])
        ->filterTable('keadaan', 'tertunda')
        ->assertCanSeeTableRecords([$tertunda])
        ->assertCanNotSeeTableRecords([$efektif])
        ->assertSee($diubah->sebutan_singkat)
        ->assertSee($pengubahMasaDepan->sebutan_singkat)
        ->assertSee(now()->addMonth()->translatedFormat('d F Y'));

    Queue::fake();
    Livewire::test(LaporanRelasiStatus::class)
        ->filterTable('keadaan', 'tertunda')
        ->callAction('eksporExcel');
    Queue::assertPushedOn('ekspor', BuatEksporLaporan::class, fn (BuatEksporLaporan $job): bool => $job->jenis === 'relasi-status' && $job->parameter === ['keadaan' => 'tertunda']);

    (new BuatEksporLaporan('relasi-status', ['keadaan' => 'tertunda'], $admin->id))->handle();
    $baris = SimpleExcelReader::create(File::files(storage_path('app/tmp/'.BuatEksporLaporan::direktori()))[0]->getPathname())->getRows()->all();
    expect($baris[0]['Dokumen'])->toBe($diubah->sebutan_singkat)
        ->and($baris[0]['Relasi'])->toBe('Diubah oleh')
        ->and($baris[0]['Keadaan'])->toBe('Tertunda');
});

test('ekspor pembukaan tautan menghasilkan berkas berheader bahasa Indonesia', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = pemanfaatanDokumen();
    pemanfaatanBuka($dokumen, 2, 'api');

    (new BuatEksporLaporan('pembukaan-tautan', [], $admin->id))->handle();

    $baris = SimpleExcelReader::create(File::files(storage_path('app/tmp/'.BuatEksporLaporan::direktori()))[0]->getPathname())->getRows()->all();
    expect(array_keys($baris[0]))->toBe(['Sebutan', 'Judul', 'Jenis dokumen', 'Dibuka via web', 'Dibuka via API', 'Total'])
        ->and($baris[0]['Dibuka via API'])->toBe(2);
});

test('penggunaan API hanya untuk admin, kontributor tidak melihatnya', function () {
    $klien = KlienApi::factory()->create(['terakhir_dipakai_pada' => now()->subHour()]);
    $klien->createToken('uji', ['dokumen:baca'], now()->addDays(90));
    $klien->createToken('kedaluwarsa', ['dokumen:baca'], now()->subDay());
    $lain = KlienApi::factory()->create();
    pemanfaatanBuka(pemanfaatanDokumen(), 3, 'api', $klien);

    $this->actingAs(penggunaDenganPeran(Peran::Kontributor))->get('/admin/laporan/penggunaan-api')->assertForbidden();
    expect(LaporanPenggunaanApi::canAccess())->toBeFalse();

    $this->actingAs(penggunaDenganPeran(Peran::AdminRegulasi))->get('/admin/laporan/penggunaan-api')->assertOk();
    Livewire::test(LaporanPenggunaanApi::class)
        ->assertCanSeeTableRecords([$klien, $lain], inOrder: true)
        ->assertTableColumnStateSet('dibuka_api', 3, $klien)
        ->assertTableColumnStateSet('token_aktif', 1, $klien)
        ->assertTableColumnStateSet('dibuka_api', 0, $lain);
});
