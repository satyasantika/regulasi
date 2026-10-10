<?php

use App\Actions\Relasi\TambahRelasiDokumen;
use App\Enums\JenisRelasi;
use App\Enums\KelompokJenisDokumen;
use App\Enums\Peran;
use App\Enums\StatusBerlaku;
use App\Enums\StatusPublikasi;
use App\Filament\Admin\Resources\DokumenResource\Pages\EditDokumen;
use App\Filament\Admin\Resources\DokumenResource\Pages\ViewDokumen;
use App\Filament\Admin\Resources\DokumenResource\RelationManagers\RelasiRelationManager;
use App\Models\BadanPenetap;
use App\Models\Dokumen;
use App\Models\DokumenRelasi;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\User;
use App\Support\RingkasanStatus;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = penggunaDenganPeran(Peran::AdminRegulasi);
});

function relasiManager(User $pengguna, Dokumen $dokumen, string $halaman = ViewDokumen::class): mixed
{
    return Livewire::actingAs($pengguna)->test(RelasiRelationManager::class, ['ownerRecord' => $dokumen, 'pageClass' => $halaman]);
}

test('admin menambah relasi lewat relation manager dan status target berubah', function () {
    $pp28 = Dokumen::factory()->terbit()->create(['sebutan_singkat' => 'PP 28/2020', 'judul' => 'Perubahan atas PP 27/2014']);
    $pp27 = Dokumen::factory()->terbit()->create(['sebutan_singkat' => 'PP 27/2014']);

    // Dicatat dari sisi PP 27/2014: "Dokumen ini diubah oleh PP 28/2020".
    relasiManager($this->admin, $pp27)
        ->assertActionVisible(TestAction::make('tambahRelasi')->table())
        ->callAction(TestAction::make('tambahRelasi')->table(), [
            'jenis_relasi' => JenisRelasi::DiubahOleh->value,
            'dokumen_terkait_id' => $pp28->id,
            'keterangan' => 'Perubahan pertama',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Relasi ditambahkan');

    expect($pp27->refresh()->status_berlaku)->toBe(StatusBerlaku::Diubah)
        ->and(DokumenRelasi::query()->where('dokumen_id', $pp28->id)->sole()->jenis_relasi)->toBe(JenisRelasi::Mengubah);

    // Kedua halaman menampilkan relasinya.
    relasiManager($this->admin, $pp28)->assertSee('PP 27/2014')->assertSee('Mengubah');
    relasiManager($this->admin, $pp27)->assertSee('PP 28/2020')->assertSee('Diubah oleh');
});

test('relasi yang melanggar BR-10 ditampilkan sebagai notifikasi gagal', function () {
    $a = Dokumen::factory()->terbit()->create();
    $b = Dokumen::factory()->terbit()->create();
    app(TambahRelasiDokumen::class)->handle($a, $b, JenisRelasi::Mencabut, null, $this->admin);

    relasiManager($this->admin, $a)
        ->callAction(TestAction::make('tambahRelasi')->table(), [
            'jenis_relasi' => JenisRelasi::Mencabut->value,
            'dokumen_terkait_id' => $b->id,
        ])
        ->assertNotified('Gagal');

    expect(DokumenRelasi::count())->toBe(2);
});

test('kontributor melihat tab relasi tanpa tombol aksi', function () {
    $kategori = KategoriSistem::factory()->create();
    $kontributor = penggunaDenganPeran(Peran::Kontributor);
    $kontributor->kategoriSistem()->attach($kategori);
    $a = Dokumen::factory()->terbit()->create(['sebutan_singkat' => 'SE 1/2025']);
    $a->kategoriSistem()->attach($kategori);
    $b = Dokumen::factory()->terbit()->create(['sebutan_singkat' => 'SE 9/2020']);
    $relasi = app(TambahRelasiDokumen::class)->handle($a, $b, JenisRelasi::Mencabut, null, $this->admin);

    expect(RelasiRelationManager::canViewForRecord($a, ViewDokumen::class))->toBeFalse();
    $this->actingAs($kontributor);
    expect(RelasiRelationManager::canViewForRecord($a, ViewDokumen::class))->toBeTrue();

    relasiManager($kontributor, $a)
        ->assertSee('SE 9/2020')
        ->assertActionHidden(TestAction::make('tambahRelasi')->table())
        ->assertActionHidden(TestAction::make('tambahRujukan')->table())
        ->assertActionHidden(TestAction::make('hapusRelasi')->table($relasi));

    Livewire::actingAs($kontributor)->test(ViewDokumen::class, ['record' => $a->getKey()])
        ->assertActionHidden('ubahStatusBerlaku');
});

test('pencarian dokumen terkait tidak menampilkan dokumen itu sendiri', function () {
    $ini = Dokumen::factory()->create(['sebutan_singkat' => 'PP 28/2020', 'judul' => 'Perubahan atas Peraturan Pemerintah']);
    $lain = Dokumen::factory()->create(['sebutan_singkat' => 'PP 27/2014', 'judul' => 'Pengelolaan Barang Milik Negara/Daerah']);

    $hasil = relasiManager($this->admin, $ini)->instance()->cariDokumen('PP');

    expect($hasil)->toHaveKey($lain->id)
        ->not->toHaveKey($ini->id)
        ->and($hasil[$lain->id])->toBe('PP 27/2014 — Pengelolaan Barang Milik Negara/Daerah');
});

test('Tambah dokumen rujukan membuat dokumen tanpa berkas yang langsung terbit dan dapat langsung dihubungkan', function () {
    $ini = Dokumen::factory()->terbit()->create(['tanggal_penetapan' => '2025-06-16']);
    $jenis = JenisDokumen::factory()->create(['kelompok' => KelompokJenisDokumen::Peraturan]);
    $badan = BadanPenetap::factory()->create();
    $kategori = KategoriSistem::factory()->create();

    relasiManager($this->admin, $ini)
        ->callAction(TestAction::make('tambahRujukan')->table(), [
            'jenis_dokumen_id' => $jenis->id,
            'nomor' => '5',
            'tahun' => 2019,
            'badan_penetap_id' => $badan->id,
            'judul' => 'Instrumen Akreditasi Program Studi',
            'kategori_sistem' => [$kategori->id],
            'jenis_relasi' => JenisRelasi::Mencabut->value,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Dokumen rujukan dibuat dan diterbitkan');

    $rujukan = Dokumen::query()->where('nomor', '5')->where('tahun', 2019)->sole();
    expect($rujukan->tanpa_berkas)->toBeTrue()
        ->and($rujukan->status_publikasi)->toBe(StatusPublikasi::Terbit)
        ->and($rujukan->status_berlaku)->toBe(StatusBerlaku::Dicabut);
});

test('hapus relasi tanpa alasan gagal, dengan alasan berhasil', function () {
    $a = Dokumen::factory()->terbit()->create();
    $b = Dokumen::factory()->terbit()->create();
    $relasi = app(TambahRelasiDokumen::class)->handle($a, $b, JenisRelasi::Mencabut, null, $this->admin);

    relasiManager($this->admin, $a)
        ->callAction(TestAction::make('hapusRelasi')->table($relasi), ['alasan' => ''])
        ->assertHasFormErrors(['alasan' => 'required']);
    expect(DokumenRelasi::count())->toBe(2);

    relasiManager($this->admin, $a, EditDokumen::class)
        ->callAction(TestAction::make('hapusRelasi')->table($relasi), ['alasan' => 'Salah pilih dokumen'])
        ->assertHasNoFormErrors();
    expect(DokumenRelasi::count())->toBe(0)->and($b->refresh()->status_berlaku)->toBe(StatusBerlaku::Berlaku);
});

test('kalimat ringkas status sesuai relasi dan relasi tertunda diperingatkan', function () {
    $banpt13 = Dokumen::factory()->terbit()->create(['sebutan_singkat' => 'BAN-PT 13/2025', 'tanggal_berlaku' => '2025-08-18']);
    $banpt5 = Dokumen::factory()->terbit()->create(['sebutan_singkat' => 'BAN-PT 5/2019']);
    $pp28 = Dokumen::factory()->terbit()->create(['sebutan_singkat' => 'PP 28/2020']);
    $pp27 = Dokumen::factory()->terbit()->create(['sebutan_singkat' => 'PP 27/2014']);
    $baru = Dokumen::factory()->terbit()->create(['sebutan_singkat' => 'BAN-PT 99/2026', 'tanggal_berlaku' => '2027-01-01']);

    app(TambahRelasiDokumen::class)->handle($banpt13, $banpt5, JenisRelasi::Mencabut, null, $this->admin);
    app(TambahRelasiDokumen::class)->handle($pp28, $pp27, JenisRelasi::Mengubah, null, $this->admin);
    app(TambahRelasiDokumen::class)->handle($baru, $pp27, JenisRelasi::Mencabut, null, $this->admin);

    expect(RingkasanStatus::untuk($banpt5->refresh())['kalimat'])->toBe('Dicabut oleh BAN-PT 13/2025 sejak 18 Agustus 2025.')
        ->and(RingkasanStatus::untuk($pp27->refresh())['kalimat'])->toBe('Berlaku; diubah oleh PP 28/2020.')
        ->and(RingkasanStatus::untuk($pp27)['tertunda'])->toBe(['Akan dicabut oleh BAN-PT 99/2026 mulai 1 Januari 2027.'])
        ->and(RingkasanStatus::untuk($baru)['tertunda'])->toBe(['Dokumen ini akan mencabut PP 27/2014 mulai 1 Januari 2027.'])
        ->and(RingkasanStatus::untuk($pp28)['kalimat'])->toBe('Berlaku.');

    Livewire::actingAs($this->admin)->test(ViewDokumen::class, ['record' => $pp27->getKey()])
        ->assertSee('Berlaku; diubah oleh PP 28/2020.')
        ->assertSee('Relasi tertunda')
        ->assertSee('Akan dicabut oleh BAN-PT 99/2026 mulai 1 Januari 2027.');
});

test('admin mengubah status berlaku manual lewat halaman lihat, catatan wajib', function () {
    $dokumen = Dokumen::factory()->terbit()->create();

    Livewire::actingAs($this->admin)->test(ViewDokumen::class, ['record' => $dokumen->getKey()])
        ->assertActionVisible('ubahStatusBerlaku')
        ->callAction('ubahStatusBerlaku', ['pilihan' => ViewDokumen::PILIHAN_TIDAK_BERLAKU, 'catatan' => ''])
        ->assertHasFormErrors(['catatan' => 'required']);
    expect($dokumen->refresh()->status_berlaku)->toBe(StatusBerlaku::Berlaku);

    Livewire::actingAs($this->admin)->test(ViewDokumen::class, ['record' => $dokumen->getKey()])
        ->callAction('ubahStatusBerlaku', ['pilihan' => ViewDokumen::PILIHAN_TIDAK_BERLAKU, 'catatan' => 'SK berjangka sudah berakhir.'])
        ->assertHasNoFormErrors();

    expect($dokumen->refresh()->status_berlaku)->toBe(StatusBerlaku::TidakBerlaku)
        ->and($dokumen->status_berlaku_manual)->toBeTrue()
        ->and(RingkasanStatus::untuk($dokumen)['kalimat'])->toBe('Tidak berlaku (ditetapkan manual oleh admin).');
});
