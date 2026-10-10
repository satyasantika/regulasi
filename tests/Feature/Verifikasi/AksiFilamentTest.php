<?php

use App\Enums\JenisTautan;
use App\Enums\KelompokJenisDokumen;
use App\Enums\PenyediaTautan;
use App\Enums\Peran;
use App\Enums\StatusPublikasi;
use App\Filament\Admin\Pages\AntreanVerifikasi;
use App\Filament\Admin\Resources\DokumenResource\Pages\EditDokumen;
use App\Filament\Admin\Resources\DokumenResource\Pages\ViewDokumen;
use App\Filament\Admin\Resources\DokumenResource\RelationManagers\RiwayatStatusRelationManager;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\RiwayatStatusDokumen;
use App\Models\TautanBerkas;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

/**
 * @param  array<string, mixed>  $atribut
 */
function dokumenSiapTerbit(?User $pembuat = null, ?KategoriSistem $kategori = null, array $atribut = [], bool $naskah = true): Dokumen
{
    $dokumen = Dokumen::factory()->create(array_merge([
        'jenis_dokumen_id' => JenisDokumen::factory()->create(['kelompok' => KelompokJenisDokumen::Peraturan]),
        'tanggal_penetapan' => '2025-02-01',
        'dibuat_oleh' => $pembuat?->getKey(),
    ], $atribut));
    $dokumen->kategoriSistem()->attach($kategori ?? KategoriSistem::factory()->create());

    if ($naskah) {
        TautanBerkas::query()->create([
            'pemilik_type' => 'dokumen',
            'pemilik_id' => $dokumen->getKey(),
            'jenis' => JenisTautan::NaskahUtama,
            'url' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJiHgFeDcBa9'.fake()->unique()->numberBetween(10000, 99999).'/view',
            'penyedia' => PenyediaTautan::GoogleDrive,
        ]);
    }

    return $dokumen;
}

/**
 * @return array{0: User, 1: KategoriSistem}
 */
function kontributorBerkategori(): array
{
    $kategori = KategoriSistem::factory()->create();
    $kontributor = penggunaDenganPeran(Peran::Kontributor);
    $kontributor->kategoriSistem()->attach($kategori);

    return [$kontributor, $kategori];
}

test('kontributor hanya melihat tombol Ajukan verifikasi pada drafnya, bukan Terbitkan/Tolak/Arsipkan', function () {
    [$kontributor, $kategori] = kontributorBerkategori();
    $dokumen = dokumenSiapTerbit($kontributor, $kategori);

    foreach ([EditDokumen::class, ViewDokumen::class] as $halaman) {
        Livewire::actingAs($kontributor)->test($halaman, ['record' => $dokumen->getKey()])
            ->assertActionVisible('ajukanVerifikasi')
            ->assertActionHidden('terbitkan')
            ->assertActionHidden('tolak')
            ->assertActionHidden('arsipkan')
            ->assertActionHidden('pulihkan');
    }
});

test('admin melihat Terbitkan pada draf dan Arsipkan pada dokumen terbit', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $draf = dokumenSiapTerbit();
    $terbit = dokumenSiapTerbit(atribut: ['status_publikasi' => StatusPublikasi::Terbit]);

    Livewire::actingAs($admin)->test(ViewDokumen::class, ['record' => $draf->getKey()])
        ->assertActionVisible('terbitkan')
        ->assertActionHidden('tolak')
        ->assertActionHidden('arsipkan');

    Livewire::actingAs($admin)->test(ViewDokumen::class, ['record' => $terbit->getKey()])
        ->assertActionHidden('terbitkan')
        ->assertActionVisible('arsipkan')
        ->assertActionHidden('pulihkan');
});

test('kontributor mengajukan dari halaman ubah lalu dialihkan ke halaman lihat', function () {
    [$kontributor, $kategori] = kontributorBerkategori();
    $dokumen = dokumenSiapTerbit($kontributor, $kategori);

    Livewire::actingAs($kontributor)->test(EditDokumen::class, ['record' => $dokumen->getKey()])
        ->callAction('ajukanVerifikasi')
        ->assertNotified('Dokumen diajukan untuk verifikasi')
        ->assertRedirect();

    expect($dokumen->refresh()->status_publikasi)->toBe(StatusPublikasi::MenungguVerifikasi);
});

test('ajukan dokumen tidak lengkap menampilkan notifikasi gagal dan status tetap draf', function () {
    [$kontributor, $kategori] = kontributorBerkategori();
    $dokumen = dokumenSiapTerbit($kontributor, $kategori, naskah: false);

    Livewire::actingAs($kontributor)->test(EditDokumen::class, ['record' => $dokumen->getKey()])
        ->callAction('ajukanVerifikasi')
        ->assertNotified('Gagal');

    expect($dokumen->refresh()->status_publikasi)->toBe(StatusPublikasi::Draf);
});

test('admin menolak lewat aksi dengan catatan sehingga status ditolak; tanpa catatan ditolak validasi', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = dokumenSiapTerbit(atribut: ['status_publikasi' => StatusPublikasi::MenungguVerifikasi]);

    Livewire::actingAs($admin)->test(ViewDokumen::class, ['record' => $dokumen->getKey()])
        ->callAction('tolak', ['catatan' => ''])
        ->assertHasActionErrors(['catatan' => 'required']);

    expect($dokumen->refresh()->status_publikasi)->toBe(StatusPublikasi::MenungguVerifikasi);

    Livewire::actingAs($admin)->test(ViewDokumen::class, ['record' => $dokumen->getKey()])
        ->callAction('tolak', ['catatan' => 'Tanggal penetapan tidak sesuai naskah.'])
        ->assertHasNoActionErrors()
        ->assertNotified('Dokumen ditolak');

    expect($dokumen->refresh()->status_publikasi)->toBe(StatusPublikasi::Ditolak)
        ->and($dokumen->catatan_verifikasi)->toBe('Tanggal penetapan tidak sesuai naskah.');
});

test('kontributor melihat banner catatan penolakan di halaman ubah', function () {
    [$kontributor, $kategori] = kontributorBerkategori();
    $dokumen = dokumenSiapTerbit($kontributor, $kategori, [
        'status_publikasi' => StatusPublikasi::Ditolak,
        'catatan_verifikasi' => 'Lampiran belum ditautkan & nomor salah.',
    ]);

    $this->actingAs($kontributor)->get("/admin/dokumen/{$dokumen->id}/edit")
        ->assertOk()
        ->assertSee('Dokumen ditolak verifikator')
        ->assertSee('Lampiran belum ditautkan &amp; nomor salah.', false);

    $draf = dokumenSiapTerbit($kontributor, $kategori);

    $this->actingAs($kontributor)->get("/admin/dokumen/{$draf->id}/edit")
        ->assertOk()
        ->assertDontSee('Dokumen ditolak verifikator');
});

test('halaman lihat menampilkan panel kelengkapan dan riwayat status', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = dokumenSiapTerbit();

    Livewire::actingAs($admin)->test(ViewDokumen::class, ['record' => $dokumen->getKey()])
        ->callAction('terbitkan', ['catatan' => 'Langsung terbit'])
        ->assertNotified('Dokumen diterbitkan');

    $this->actingAs($admin)->get("/admin/dokumen/{$dokumen->id}")
        ->assertOk()
        ->assertSee('Kelengkapan')
        ->assertSee('Tautan naskah utama')
        ->assertSee('Teks terekstraksi');

    Livewire::actingAs($admin)->test(RiwayatStatusRelationManager::class, [
        'ownerRecord' => $dokumen->refresh(),
        'pageClass' => ViewDokumen::class,
    ])
        ->assertCanSeeTableRecords(RiwayatStatusDokumen::query()->get())
        ->assertSee('Draf → Terbit')
        ->assertSee('Langsung terbit')
        ->assertSee($admin->name);
});

test('aksi massal menerbitkan dua dari tiga dan melaporkan satu gagal', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $lengkap = collect([
        dokumenSiapTerbit(atribut: ['status_publikasi' => StatusPublikasi::MenungguVerifikasi]),
        dokumenSiapTerbit(atribut: ['status_publikasi' => StatusPublikasi::MenungguVerifikasi]),
    ]);
    $kurang = dokumenSiapTerbit(atribut: ['status_publikasi' => StatusPublikasi::MenungguVerifikasi], naskah: false);

    Livewire::actingAs($admin)->test(AntreanVerifikasi::class)
        ->assertCanSeeTableRecords([...$lengkap, $kurang])
        ->selectTableRecords([...$lengkap, $kurang])
        ->callAction(TestAction::make('terbitkanMassal')->table()->bulk())
        ->assertNotified('2 dokumen diterbitkan, 1 gagal');

    expect($lengkap->every(fn (Dokumen $d): bool => $d->refresh()->status_publikasi === StatusPublikasi::Terbit))->toBeTrue()
        ->and($kurang->refresh()->status_publikasi)->toBe(StatusPublikasi::MenungguVerifikasi);
});

test('antrean menampilkan aksi baris terbitkan dan tolak serta kelengkapan x/y', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $kurang = dokumenSiapTerbit(atribut: ['status_publikasi' => StatusPublikasi::MenungguVerifikasi], naskah: false);

    Livewire::actingAs($admin)->test(AntreanVerifikasi::class)
        ->assertTableColumnStateSet('kelengkapan', '7/8', $kurang)
        ->callAction(TestAction::make('tolak')->table($kurang), ['catatan' => 'Naskah utama belum ditautkan.'])
        ->assertNotified('Dokumen ditolak');

    expect($kurang->refresh()->status_publikasi)->toBe(StatusPublikasi::Ditolak);
});

test('halaman antrean 403 untuk kontributor dan dapat diakses admin', function () {
    [$kontributor] = kontributorBerkategori();

    $this->actingAs($kontributor)->get('/admin/antrean-verifikasi')->assertForbidden();
    $this->actingAs(penggunaDenganPeran(Peran::AdminRegulasi))->get('/admin/antrean-verifikasi')->assertOk();
});

test('badge navigasi antrean menunjukkan jumlah dokumen menunggu verifikasi', function () {
    $this->actingAs(penggunaDenganPeran(Peran::AdminRegulasi));

    expect(AntreanVerifikasi::getNavigationBadge())->toBeNull();

    Dokumen::factory()->count(3)->berstatus(StatusPublikasi::MenungguVerifikasi)->create();
    Dokumen::factory()->berstatus(StatusPublikasi::Draf)->create();
    Dokumen::factory()->terbit()->create();

    expect(AntreanVerifikasi::getNavigationBadge())->toBe('3');
});
