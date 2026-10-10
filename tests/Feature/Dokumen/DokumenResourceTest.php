<?php

use App\Actions\Dokumen\SimpanDokumen;
use App\Enums\Peran;
use App\Enums\StatusPublikasi;
use App\Filament\Admin\Resources\DokumenResource\Pages\CreateDokumen;
use App\Filament\Admin\Resources\DokumenResource\Pages\EditDokumen;
use App\Filament\Admin\Resources\DokumenResource\Pages\ListDokumen;
use App\Models\Aktivitas;
use App\Models\BadanPenetap;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\User;
use Database\Seeders\BadanPenetapSeeder;
use Database\Seeders\JenisDokumenSeeder;
use Database\Seeders\KategoriSistemSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->seed([JenisDokumenSeeder::class, BadanPenetapSeeder::class, KategoriSistemSeeder::class]);
});

function kategori(string $kode): KategoriSistem
{
    return KategoriSistem::where('kode', $kode)->firstOrFail();
}

function kontributorKategori(string $kode): User
{
    $kontributor = penggunaDenganPeran(Peran::Kontributor);
    $kontributor->kategoriSistem()->attach(kategori($kode));

    return $kontributor;
}

/**
 * @param  array<string, mixed>  $atribut
 */
function dokumenKategori(string $kode, array $atribut = [], ?StatusPublikasi $status = null): Dokumen
{
    $dokumen = Dokumen::factory()->berstatus($status ?? StatusPublikasi::Draf)->create($atribut);
    $dokumen->kategoriSistem()->attach(kategori($kode));

    return $dokumen;
}

/**
 * @return array<string, mixed>
 */
function isianDokumen(string $kodeJenis, array $timpa = []): array
{
    return array_merge([
        'jenis_dokumen_id' => JenisDokumen::where('kode', $kodeJenis)->value('id'),
        'badan_penetap_id' => BadanPenetap::where('slug', 'kemenkeu')->value('id'),
        'nomor' => '181/PMK.06/2016',
        'tahun' => 2016,
        'judul' => 'Penatausahaan Barang Milik Negara',
        'kategori_sistem' => [kategori('aset')->id],
        'visibilitas' => 'publik',
    ], $timpa);
}

test('kontributor aset hanya melihat dokumen kategori aset', function () {
    $kontributor = kontributorKategori('aset');
    $aset = dokumenKategori('aset');
    $akreditasi = dokumenKategori('akreditasi');

    Livewire::actingAs($kontributor)->test(ListDokumen::class)
        ->assertCanSeeTableRecords([$aset])
        ->assertCanNotSeeTableRecords([$akreditasi]);

    $this->actingAs($kontributor)->get("/admin/dokumen/{$akreditasi->id}")->assertNotFound();
    $this->actingAs($kontributor)->get("/admin/dokumen/{$aset->id}")->assertOk();
});

test('admin melihat semua dokumen dan tab status menyaring', function () {
    $draf = dokumenKategori('aset');
    $terbit = dokumenKategori('akreditasi', status: StatusPublikasi::Terbit);

    Livewire::actingAs(penggunaDenganPeran(Peran::AdminRegulasi))->test(ListDokumen::class)
        ->assertCanSeeTableRecords([$draf, $terbit])
        ->set('activeTab', 'terbit')
        ->assertCanSeeTableRecords([$terbit])
        ->assertCanNotSeeTableRecords([$draf]);
});

test('kontributor membuat dokumen di kategorinya lewat form', function () {
    $kontributor = kontributorKategori('aset');

    Livewire::actingAs($kontributor)->test(CreateDokumen::class)
        ->fillForm(isianDokumen('PERMEN'))
        ->call('create')
        ->assertHasNoFormErrors();

    $dokumen = Dokumen::with('kategoriSistem')->firstOrFail();

    expect($dokumen->dibuat_oleh)->toBe($kontributor->id)
        ->and($dokumen->status_publikasi)->toBe(StatusPublikasi::Draf)
        ->and($dokumen->sebutan)->toBe('Peraturan Menteri Keuangan Nomor 181/PMK.06/2016')
        ->and($dokumen->sebutan_singkat)->toBe('PMK 181/PMK.06/2016')
        ->and($dokumen->slug)->toBe('pmk-181-pmk-06-2016-penatausahaan-barang-milik-negara')
        ->and($dokumen->kategoriSistem->pluck('kode')->all())->toBe(['aset']);
});

test('kontributor tidak dapat membuat dokumen berkategori akreditasi', function () {
    $kontributor = kontributorKategori('aset');

    expect(fn () => app(SimpanDokumen::class)->handle(isianDokumen('PERMEN', ['kategori_sistem' => [kategori('akreditasi')->id]]), $kontributor))
        ->toThrow(ValidationException::class);

    expect(Dokumen::count())->toBe(0);
});

test('kontributor tidak dapat mengedit dokumen kontributor lain walau sekategori', function () {
    $pemilik = kontributorKategori('aset');
    $lain = kontributorKategori('aset');
    $dokumen = dokumenKategori('aset', ['dibuat_oleh' => $pemilik->id]);

    $this->actingAs($lain)->get("/admin/dokumen/{$dokumen->id}/edit")->assertForbidden();
    $this->actingAs($pemilik)->get("/admin/dokumen/{$dokumen->id}/edit")->assertOk();
});

test('kontributor tidak dapat mengedit dokumen menunggu verifikasi atau terbit (BR-16)', function (StatusPublikasi $status) {
    $kontributor = kontributorKategori('aset');
    $dokumen = dokumenKategori('aset', ['dibuat_oleh' => $kontributor->id], $status);

    $this->actingAs($kontributor)->get("/admin/dokumen/{$dokumen->id}/edit")->assertForbidden();
    expect($kontributor->can('delete', $dokumen))->toBeFalse();
})->with([StatusPublikasi::MenungguVerifikasi, StatusPublikasi::Terbit]);

test('admin dapat mengedit dokumen terbit dan perubahan tercatat', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = dokumenKategori('aset', array_diff_key(isianDokumen('PERMEN'), ['kategori_sistem' => true]) + ['sebutan' => 'S', 'sebutan_singkat' => 'S'], StatusPublikasi::Terbit);

    Livewire::actingAs($admin)->test(EditDokumen::class, ['record' => $dokumen->id])
        ->fillForm(['abstrak' => 'Abstrak baru dokumen.'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($dokumen->refresh()->abstrak)->toBe('Abstrak baru dokumen.')
        ->and($dokumen->status_publikasi)->toBe(StatusPublikasi::Terbit);

    $log = Aktivitas::query()->where('subject_id', $dokumen->id)->where('event', 'updated')->latest()->firstOrFail();
    expect($log->causer_id)->toBe($admin->id)
        ->and($log->attribute_changes->get('attributes'))->toHaveKey('abstrak');
});

test('admin tidak dapat mengedit dokumen diarsipkan', function () {
    $dokumen = dokumenKategori('aset', status: StatusPublikasi::Diarsipkan);

    $this->actingAs(penggunaDenganPeran(Peran::AdminRegulasi))->get("/admin/dokumen/{$dokumen->id}/edit")->assertForbidden();
});

test('nomor wajib untuk jenis PERMEN tetapi tidak untuk PEDOMAN', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);

    Livewire::actingAs($admin)->test(CreateDokumen::class)
        ->fillForm(isianDokumen('PERMEN', ['nomor' => null]))
        ->call('create')
        ->assertHasFormErrors(['nomor' => 'required']);

    Livewire::actingAs($admin)->test(CreateDokumen::class)
        ->fillForm(isianDokumen('PEDOMAN', ['nomor' => null, 'judul' => 'Pedoman Akreditasi']))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Dokumen::firstOrFail()->sebutan)->toBe('Pedoman/Panduan Tahun 2016');
});

test('tanggal pengundangan tidak boleh sebelum penetapan', function () {
    Livewire::actingAs(penggunaDenganPeran(Peran::AdminRegulasi))->test(CreateDokumen::class)
        ->fillForm(isianDokumen('PERMEN', ['tanggal_penetapan' => '2016-12-01', 'tanggal_pengundangan' => '2016-11-01']))
        ->call('create')
        ->assertHasFormErrors(['tanggal_pengundangan']);
});

test('duplikat jenis badan nomor tahun ditolak dengan pesan validasi', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    app(SimpanDokumen::class)->handle(isianDokumen('PERMEN'), $admin);

    expect(fn () => app(SimpanDokumen::class)->handle(isianDokumen('PERMEN'), $admin))
        ->toThrow(ValidationException::class, 'sudah ada');
});

test('slug berubah saat judul diubah', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = app(SimpanDokumen::class)->handle(isianDokumen('PERMEN'), $admin);
    $slugLama = $dokumen->slug;

    Livewire::actingAs($admin)->test(EditDokumen::class, ['record' => $dokumen->id])
        ->fillForm(['judul' => 'Tata Cara Pelaksanaan Penatausahaan'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($dokumen->refresh()->slug)->not->toBe($slugLama)
        ->and($dokumen->slug)->toBe('pmk-181-pmk-06-2016-tata-cara-pelaksanaan-penatausahaan');
});

test('status publikasi tidak dapat diubah lewat form', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = app(SimpanDokumen::class)->handle(isianDokumen('PERMEN') + ['status_publikasi' => 'terbit'], $admin);

    expect($dokumen->status_publikasi)->toBe(StatusPublikasi::Draf);
});

test('pengguna biasa tidak dapat membuka daftar dokumen admin', function () {
    $this->actingAs(penggunaDenganPeran(Peran::Pengguna))->get('/admin/dokumen')->assertForbidden();
});
