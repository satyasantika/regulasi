<?php

use App\Actions\Berkas\HapusTautanBerkas;
use App\Actions\Berkas\SimpanTautanBerkas;
use App\Enums\JenisTautan;
use App\Enums\PenyediaTautan;
use App\Enums\Peran;
use App\Enums\StatusCekTautan;
use App\Filament\Admin\Resources\DokumenResource\Pages\CreateDokumen;
use App\Filament\Admin\Resources\DokumenResource\Pages\EditDokumen;
use App\Filament\Admin\Resources\DokumenResource\RelationManagers\TautanBerkasRelationManager;
use App\Jobs\PeriksaTautanBerkas;
use App\Models\Aktivitas;
use App\Models\BadanPenetap;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Rules\TautanBerkasValid;
use Database\Seeders\BadanPenetapSeeder;
use Database\Seeders\JenisDokumenSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const ID_DRIVE = '1AbCdEfGhIjKlMnOpQrStUvWxYz012345';

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    Queue::fake();
});

function adminTautan(): User
{
    return penggunaDenganPeran(Peran::AdminRegulasi);
}

function simpanTautan(Dokumen $dokumen, array $data, ?User $oleh = null): array
{
    return app(SimpanTautanBerkas::class)->handle($dokumen, $data, $oleh ?? adminTautan());
}

test('tautan Drive /file/d/{id}/view diterima dengan drive_file_id benar dan pemeriksaan diantrekan', function () {
    $dokumen = Dokumen::factory()->create();

    $hasil = simpanTautan($dokumen, ['url' => 'https://drive.google.com/file/d/'.ID_DRIVE.'/view?usp=sharing', 'jenis' => 'naskah_utama', 'label' => 'Naskah PDF']);
    $tautan = $hasil['tautan'];

    expect($tautan->penyedia)->toBe(PenyediaTautan::GoogleDrive)
        ->and($tautan->drive_file_id)->toBe(ID_DRIVE)
        ->and($tautan->jenis)->toBe(JenisTautan::NaskahUtama)
        ->and($tautan->status_cek)->toBe(StatusCekTautan::Belum)
        ->and($tautan->pemilik_type)->toBe('dokumen')
        ->and($hasil['peringatan'])->toBeNull()
        ->and($dokumen->naskahUtama()->first()?->is($tautan))->toBeTrue();

    Queue::assertPushedOn('tautan', PeriksaTautanBerkas::class);
});

test('pola Drive open?id, uc?id, dan Google Docs terurai', function (string $url, PenyediaTautan $penyedia) {
    $tautan = simpanTautan(Dokumen::factory()->create(), ['url' => $url, 'jenis' => 'lampiran'])['tautan'];

    expect($tautan->drive_file_id)->toBe(ID_DRIVE)->and($tautan->penyedia)->toBe($penyedia);
})->with([
    ['https://drive.google.com/open?id='.ID_DRIVE, PenyediaTautan::GoogleDrive],
    ['https://drive.google.com/uc?id='.ID_DRIVE.'&export=download', PenyediaTautan::GoogleDrive],
    ['https://docs.google.com/document/d/'.ID_DRIVE.'/edit', PenyediaTautan::GoogleDocs],
]);

test('tautan peraturan.go.id dan jdih diterima dengan penyedia sumber resmi', function (string $url) {
    $tautan = simpanTautan(Dokumen::factory()->create(), ['url' => $url, 'jenis' => 'halaman_sumber'])['tautan'];

    expect($tautan->penyedia)->toBe(PenyediaTautan::SumberResmi)->and($tautan->drive_file_id)->toBeNull();
})->with([
    'https://peraturan.go.id/id/permendikbudristek-no-53-tahun-2023',
    'https://jdih.kemenkeu.go.id/in/dokumen/peraturan/x',
    'https://peraturan.bpk.go.id/Details/123456',
]);

test('tautan tidak sah ditolak dengan pesan jelas', function (string $url, string $pesan) {
    expect(TautanBerkasValid::periksa($url))->toContain($pesan);

    expect(fn () => simpanTautan(Dokumen::factory()->create(), ['url' => $url, 'jenis' => 'lampiran']))
        ->toThrow(ValidationException::class);
})->with([
    'http' => ['http://drive.google.com/file/d/'.ID_DRIVE.'/view', 'https://'],
    'pemendek' => ['https://bit.ly/abc', 'Pemendek URL'],
    'javascript' => ['javascript:alert(1)', 'https://'],
    'ip' => ['https://1.2.3.4/x.pdf', 'bukan alamat IP'],
    'userinfo' => ['https://user@drive.google.com/file/d/'.ID_DRIVE.'/view', 'nama pengguna'],
    'port' => ['https://drive.google.com:8443/file/d/'.ID_DRIVE.'/view', 'port'],
    'folder' => ['https://drive.google.com/drive/folders/'.ID_DRIVE, 'bukan tautan folder'],
    'domain asing' => ['https://evil.com/x.pdf', 'tidak termasuk daftar'],
    'akhiran palsu' => ['https://drive.google.com.evil.com/file/d/x', 'tidak termasuk daftar'],
    'wildcard bertingkat' => ['https://jdih.a.b.go.id/x', 'tidak termasuk daftar'],
    'subdomain unsil palsu' => ['https://unsil.ac.id.evil.com/x', 'tidak termasuk daftar'],
]);

test('naskah utama di Drive wajib menunjuk satu berkas', function () {
    expect(TautanBerkasValid::periksa('https://drive.google.com/drive/my-drive', JenisTautan::NaskahUtama))->toContain('satu berkas')
        ->and(TautanBerkasValid::periksa('https://drive.google.com/drive/my-drive', JenisTautan::Lainnya))->toBeNull();
});

test('URL atau drive_file_id ganda dalam dokumen yang sama ditolak', function () {
    $dokumen = Dokumen::factory()->create();
    simpanTautan($dokumen, ['url' => 'https://drive.google.com/file/d/'.ID_DRIVE.'/view', 'jenis' => 'naskah_utama']);

    expect(fn () => simpanTautan($dokumen, ['url' => 'https://drive.google.com/file/d/'.ID_DRIVE.'/view', 'jenis' => 'lampiran']))
        ->toThrow(ValidationException::class, 'sudah ada pada dokumen ini');

    expect(fn () => simpanTautan($dokumen, ['url' => 'https://drive.google.com/open?id='.ID_DRIVE, 'jenis' => 'lampiran']))
        ->toThrow(ValidationException::class, 'sudah ada pada dokumen ini');

    expect(TautanBerkas::count())->toBe(1);
});

test('tautan yang sama pada dokumen lain menimbulkan peringatan', function () {
    $lain = Dokumen::factory()->create(['sebutan_singkat' => 'PMK 181/PMK.06/2016']);
    simpanTautan($lain, ['url' => 'https://drive.google.com/file/d/'.ID_DRIVE.'/view', 'jenis' => 'naskah_utama']);

    $hasil = simpanTautan(Dokumen::factory()->create(), ['url' => 'https://drive.google.com/file/d/'.ID_DRIVE.'/view', 'jenis' => 'naskah_utama']);

    expect($hasil['peringatan'])->toContain('PMK 181/PMK.06/2016');
});

test('naskah utama kedua menurunkan yang lama menjadi lampiran dengan catatan aktivitas', function () {
    $dokumen = Dokumen::factory()->create();
    $lama = simpanTautan($dokumen, ['url' => 'https://drive.google.com/file/d/'.ID_DRIVE.'/view', 'jenis' => 'naskah_utama'])['tautan'];
    $baru = simpanTautan($dokumen, ['url' => 'https://peraturan.go.id/files/naskah.pdf', 'jenis' => 'naskah_utama'])['tautan'];

    expect($lama->refresh()->jenis)->toBe(JenisTautan::Lampiran)
        ->and($dokumen->naskahUtama()->first()?->is($baru))->toBeTrue()
        ->and($dokumen->tautanBerkas()->pluck('id')->first())->toBe($baru->id)
        ->and(Aktivitas::query()->where('subject_id', $lama->id)->where('event', 'naskah-utama-diganti')->exists())->toBeTrue();
});

test('perubahan URL tercatat activitylog dan mereset status cek', function () {
    $dokumen = Dokumen::factory()->create();
    $admin = adminTautan();
    $tautan = simpanTautan($dokumen, ['url' => 'https://peraturan.go.id/a.pdf', 'jenis' => 'lampiran'], $admin)['tautan'];
    $tautan->forceFill(['status_cek' => StatusCekTautan::DapatDiakses])->save();

    app(SimpanTautanBerkas::class)->handle($dokumen, ['url' => 'https://peraturan.go.id/b.pdf'], $admin, $tautan);

    $log = Aktivitas::query()->where('subject_id', $tautan->id)->where('event', 'updated')->latest()->firstOrFail();

    expect($tautan->refresh()->status_cek)->toBe(StatusCekTautan::Belum)
        ->and($log->attribute_changes->get('old'))->toBe(['url' => 'https://peraturan.go.id/a.pdf'])
        ->and($log->attribute_changes->get('attributes'))->toBe(['url' => 'https://peraturan.go.id/b.pdf']);
});

test('kontributor tidak bisa menambah tautan ke dokumen terbit', function () {
    $kontributor = penggunaDenganPeran(Peran::Kontributor);
    $kategori = KategoriSistem::factory()->create();
    $kontributor->kategoriSistem()->attach($kategori);
    $dokumen = Dokumen::factory()->terbit()->create(['dibuat_oleh' => $kontributor->id]);
    $dokumen->kategoriSistem()->attach($kategori);

    expect(fn () => simpanTautan($dokumen, ['url' => 'https://peraturan.go.id/a.pdf', 'jenis' => 'lampiran'], $kontributor))
        ->toThrow(AuthorizationException::class);

    Livewire::actingAs($kontributor)
        ->test(TautanBerkasRelationManager::class, ['ownerRecord' => $dokumen, 'pageClass' => EditDokumen::class])
        ->assertActionHidden(TestAction::make('create')->table());
});

test('kontributor dapat menambah dan menghapus tautan pada draf miliknya lewat relation manager', function () {
    $kontributor = penggunaDenganPeran(Peran::Kontributor);
    $kategori = KategoriSistem::factory()->create();
    $kontributor->kategoriSistem()->attach($kategori);
    $dokumen = Dokumen::factory()->create(['dibuat_oleh' => $kontributor->id]);
    $dokumen->kategoriSistem()->attach($kategori);

    Livewire::actingAs($kontributor)
        ->test(TautanBerkasRelationManager::class, ['ownerRecord' => $dokumen, 'pageClass' => EditDokumen::class])
        ->callAction(TestAction::make('create')->table(), ['url' => 'https://bit.ly/abc', 'jenis' => 'naskah_utama'])
        ->assertHasFormErrors(['url']);

    Livewire::actingAs($kontributor)
        ->test(TautanBerkasRelationManager::class, ['ownerRecord' => $dokumen, 'pageClass' => EditDokumen::class])
        ->callAction(TestAction::make('create')->table(), ['url' => 'https://drive.google.com/file/d/'.ID_DRIVE.'/view', 'jenis' => 'naskah_utama', 'label' => 'Naskah'])
        ->assertHasNoFormErrors();

    $tautan = TautanBerkas::firstOrFail();
    expect($tautan->drive_file_id)->toBe(ID_DRIVE)->and($tautan->ditambahkan_oleh)->toBe($kontributor->id);

    app(HapusTautanBerkas::class)->handle($tautan, $kontributor);
    expect(TautanBerkas::count())->toBe(0)->and(TautanBerkas::withTrashed()->count())->toBe(1);
});

test('pengguna lain tidak dapat menghapus tautan dokumen yang bukan miliknya', function () {
    $tautan = simpanTautan(Dokumen::factory()->create(), ['url' => 'https://peraturan.go.id/a.pdf', 'jenis' => 'lampiran'])['tautan'];

    expect(fn () => app(HapusTautanBerkas::class)->handle($tautan, penggunaDenganPeran(Peran::Kontributor)))
        ->toThrow(AuthorizationException::class);
});

test('dokumen baru dengan tautan sumber resmi menyimpan tautan halaman sumber', function () {
    $this->seed([JenisDokumenSeeder::class, BadanPenetapSeeder::class]);
    $admin = adminTautan();

    Livewire::actingAs($admin)->test(CreateDokumen::class)
        ->fillForm([
            'jenis_dokumen_id' => JenisDokumen::where('kode', 'PERPRES')->value('id'),
            'badan_penetap_id' => BadanPenetap::where('slug', 'presiden-republik-indonesia')->value('id'),
            'nomor' => '33',
            'tahun' => 2012,
            'judul' => 'Jaringan Dokumentasi dan Informasi Hukum Nasional',
            'kategori_sistem' => [KategoriSistem::factory()->create()->id],
            'visibilitas' => 'publik',
            'tautan_sumber' => 'https://peraturan.go.id/id/perpres-no-33-tahun-2012',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $tautan = TautanBerkas::firstOrFail();
    expect($tautan->jenis)->toBe(JenisTautan::HalamanSumber)->and($tautan->penyedia)->toBe(PenyediaTautan::SumberResmi);
});
