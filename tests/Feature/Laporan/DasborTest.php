<?php

use App\Enums\JenisTautan;
use App\Enums\PenyediaTautan;
use App\Enums\Peran;
use App\Enums\StatusBerlaku;
use App\Enums\StatusPublikasi;
use App\Filament\Admin\Pages\Dasbor;
use App\Filament\Admin\Widgets\AntreanVerifikasiWidget;
use App\Filament\Admin\Widgets\DibukaTerpopulerWidget;
use App\Filament\Admin\Widgets\DrafSayaWidget;
use App\Filament\Admin\Widgets\StatistikDokumenOverview;
use App\Models\Dokumen;
use App\Models\KategoriSistem;
use App\Models\LogBukaTautan;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Support\CacheRegulasi;
use App\Support\Laporan\LingkupLaporan;
use App\Support\Laporan\StatistikDasbor;
use Database\Seeders\KategoriSistemSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->seed(KategoriSistemSeeder::class);
});

function dasborKategori(string $kode): KategoriSistem
{
    return KategoriSistem::where('kode', $kode)->firstOrFail();
}

/**
 * @param  array<string, mixed>  $atribut
 */
function dasborDokumen(string $kode, StatusPublikasi $status = StatusPublikasi::Terbit, array $atribut = []): Dokumen
{
    $dokumen = Dokumen::factory()->berstatus($status)->create($atribut);
    $dokumen->kategoriSistem()->attach(dasborKategori($kode));

    return $dokumen;
}

function dasborBuka(Dokumen $dokumen, int $kali, string $saluran = 'web', ?DateTimeInterface $pada = null): void
{
    $tautan = TautanBerkas::query()->firstOrCreate(
        ['pemilik_type' => 'dokumen', 'pemilik_id' => $dokumen->id, 'jenis' => JenisTautan::NaskahUtama->value],
        ['url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz'.substr(md5($dokumen->id), 0, 6).'/view', 'penyedia' => PenyediaTautan::GoogleDrive, 'drive_file_id' => '1AbCdEfGhIjKlMnOpQrStUvWxYz'.substr(md5($dokumen->id), 0, 6)],
    );

    for ($i = 0; $i < $kali; $i++) {
        $log = LogBukaTautan::query()->create([
            'dokumen_id' => $dokumen->id,
            'tautan_berkas_id' => $tautan->id,
            'saluran' => $saluran,
            'ip_hash' => hash('sha256', (string) $i),
        ]);

        if ($pada !== null) {
            $log->forceFill(['created_at' => $pada])->save();
        }
    }
}

function kontributorDasbor(string $kode): User
{
    $kontributor = penggunaDenganPeran(Peran::Kontributor);
    $kontributor->kategoriSistem()->attach(dasborKategori($kode));

    return $kontributor;
}

test('admin melihat angka global', function () {
    $asetTerbit = dasborDokumen('aset', atribut: ['abstrak' => 'Ringkasan isi.', 'tahun' => (int) now()->year]);
    dasborDokumen('akreditasi');
    $dicabut = dasborDokumen('akreditasi');
    $dicabut->forceFill(['status_berlaku' => StatusBerlaku::Dicabut])->save();
    dasborDokumen('aset', StatusPublikasi::MenungguVerifikasi);
    dasborDokumen('akreditasi', StatusPublikasi::MenungguVerifikasi);
    dasborBuka($asetTerbit, 3);
    dasborBuka($dicabut, 2, 'api');

    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $data = StatistikDasbor::untuk($admin);

    expect($data['total_terbit'])->toBe(3)
        ->and($data['menunggu_verifikasi'])->toBe(2)
        ->and($data['dicabut'])->toBe(1)
        ->and($data['dibuka_30_hari'])->toBe(5)
        ->and($data['persen_abstrak'])->toBe(33)
        ->and($data['per_tahun'][(int) now()->year])->toBeGreaterThanOrEqual(1)
        ->and($data['terpopuler'])->toBe([$asetTerbit->id => 3, $dicabut->id => 2]);

    $this->actingAs($admin);
    Livewire::test(StatistikDokumenOverview::class)
        ->assertSee('Dokumen terbit')
        ->assertSee('Menunggu verifikasi');
    Livewire::test(DibukaTerpopulerWidget::class)
        ->assertCanSeeTableRecords([$asetTerbit, $dicabut], inOrder: true);
});

test('kontributor aset hanya melihat angka kategori aset', function () {
    $aset = dasborDokumen('aset');
    dasborDokumen('aset', StatusPublikasi::MenungguVerifikasi);
    $akreditasi = dasborDokumen('akreditasi');
    dasborDokumen('akreditasi');
    dasborBuka($aset, 1);
    dasborBuka($akreditasi, 4);

    $kontributor = kontributorDasbor('aset');
    $data = StatistikDasbor::untuk($kontributor);

    expect($data['total_terbit'])->toBe(1)
        ->and($data['menunggu_verifikasi'])->toBe(1)
        ->and($data['dibuka_30_hari'])->toBe(1)
        ->and($data['terpopuler'])->toBe([$aset->id => 1])
        ->and(StatistikDasbor::kunci(LingkupLaporan::untuk($kontributor)))->toStartWith('dasbor:kontributor:')
        ->and(StatistikDasbor::kunci(LingkupLaporan::untuk(penggunaDenganPeran(Peran::AdminRegulasi))))->toBe('dasbor:admin-regulasi:semua');

    $this->actingAs($kontributor);
    Livewire::test(DibukaTerpopulerWidget::class)
        ->assertCanSeeTableRecords([$aset])
        ->assertCanNotSeeTableRecords([$akreditasi]);
});

test('kontributor tanpa kategori melihat nol', function () {
    dasborDokumen('aset');

    expect(StatistikDasbor::untuk(penggunaDenganPeran(Peran::Kontributor))['total_terbit'])->toBe(0);
});

test('widget antrean tidak dirender untuk kontributor, diganti draf miliknya', function () {
    $kontributor = kontributorDasbor('aset');
    $drafSaya = dasborDokumen('aset', StatusPublikasi::Draf, ['dibuat_oleh' => $kontributor->id]);
    $ditolakSaya = dasborDokumen('aset', StatusPublikasi::Ditolak, ['dibuat_oleh' => $kontributor->id]);
    $drafOrangLain = dasborDokumen('aset', StatusPublikasi::Draf);

    $this->actingAs($kontributor)->get('/admin')->assertOk();

    Livewire::test(Dasbor::class)
        ->assertDontSeeLivewire(AntreanVerifikasiWidget::class)
        ->assertSeeLivewire(DrafSayaWidget::class);

    Livewire::test(DrafSayaWidget::class)
        ->assertCanSeeTableRecords([$drafSaya, $ditolakSaya])
        ->assertCanNotSeeTableRecords([$drafOrangLain]);

    expect(AntreanVerifikasiWidget::canView())->toBeFalse();
});

test('admin melihat antrean verifikasi lima tertua', function () {
    $menunggu = collect(range(6, 1))->map(function (int $hari): Dokumen {
        $dokumen = dasborDokumen('aset', StatusPublikasi::MenungguVerifikasi);
        $dokumen->forceFill(['updated_at' => now()->subDays($hari)])->saveQuietly();

        return $dokumen;
    });
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);

    $this->actingAs($admin)->get('/admin')->assertOk();

    Livewire::test(Dasbor::class)
        ->assertSeeLivewire(AntreanVerifikasiWidget::class)
        ->assertDontSeeLivewire(DrafSayaWidget::class);

    Livewire::test(AntreanVerifikasiWidget::class)
        ->assertCanSeeTableRecords($menunggu->take(5), inOrder: true)
        ->assertCanNotSeeTableRecords([$menunggu->last()]);
});

test('pratinjau sematan Drive dan log lama tidak dihitung', function () {
    $dokumen = dasborDokumen('aset');
    TautanBerkas::query()->create([
        'pemilik_type' => 'dokumen',
        'pemilik_id' => $dokumen->id,
        'jenis' => JenisTautan::NaskahUtama,
        'url' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJiHgFeDcBa98765/view',
        'penyedia' => PenyediaTautan::GoogleDrive,
        'drive_file_id' => '1ZyXwVuTsRqPoNmLkJiHgFeDcBa98765',
    ]);

    // Halaman detail memuat pratinjau iframe Drive; tidak ada log buka tautan (BR-17).
    $this->get("/dokumen/{$dokumen->id}/{$dokumen->slug}")->assertOk()->assertSee('drive.google.com', false);
    dasborBuka($dokumen, 2, pada: now()->subDays(31));

    $data = StatistikDasbor::untuk(penggunaDenganPeran(Peran::AdminRegulasi));

    expect(LogBukaTautan::count())->toBe(2)
        ->and($data['dibuka_30_hari'])->toBe(0)
        ->and($data['terpopuler'])->toBe([]);
});

test('cache dasbor dibersihkan oleh lupakanPublik', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    dasborDokumen('aset');
    expect(StatistikDasbor::untuk($admin)['total_terbit'])->toBe(1);

    dasborDokumen('aset');
    expect(StatistikDasbor::untuk($admin)['total_terbit'])->toBe(1);

    CacheRegulasi::lupakanPublik();

    expect(StatistikDasbor::untuk($admin)['total_terbit'])->toBe(2)
        ->and(Cache::has('dasbor:admin-regulasi:semua'))->toBeTrue();
});
