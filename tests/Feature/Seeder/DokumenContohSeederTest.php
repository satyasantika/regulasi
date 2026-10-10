<?php

use App\Actions\Dokumen\SimpanDokumen;
use App\Actions\Dokumen\TerbitkanDokumen;
use App\Enums\JenisRelasi;
use App\Enums\Peran;
use App\Enums\StatusBerlaku;
use App\Enums\StatusPublikasi;
use App\Filament\Admin\Resources\DokumenResource\Pages\ViewDokumen;
use App\Filament\Admin\Resources\DokumenResource\RelationManagers\RelasiRelationManager;
use App\Models\BadanPenetap;
use App\Models\Dokumen;
use App\Models\DokumenRelasi;
use App\Models\JenisDokumen;
use App\Models\RiwayatStatusDokumen;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Support\RingkasanStatus;
use Database\Seeders\BadanPenetapSeeder;
use Database\Seeders\DokumenContohSeeder;
use Database\Seeders\JenisDokumenSeeder;
use Database\Seeders\KategoriSistemSeeder;
use Database\Seeders\TagSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const JUMLAH_DOKUMEN_CONTOH = 26;
const JUMLAH_RUJUKAN = 9;
const JUMLAH_RELASI = 7;

beforeEach(function () {
    Queue::fake();
    $this->seed([JenisDokumenSeeder::class, BadanPenetapSeeder::class, KategoriSistemSeeder::class, TagSeeder::class]);
    $this->superAdmin = penggunaDenganPeran(Peran::SuperAdmin);
    config(['regulasi.seed_tautan_lokal' => storage_path('framework/testing/tautan-contoh-'.uniqid().'.php')]);
});

afterEach(function () {
    @unlink((string) config('regulasi.seed_tautan_lokal'));
});

/**
 * Dokumen contoh menurut identitas (singkatan badan, kode jenis, nomor, tahun).
 */
function dokumenContoh(string $badan, string $jenis, string $nomor, int $tahun): Dokumen
{
    return Dokumen::query()
        ->where('badan_penetap_id', BadanPenetap::query()->where('singkatan', $badan)->orWhere('nama', $badan)->value('id'))
        ->where('jenis_dokumen_id', JenisDokumen::query()->where('kode', $jenis)->value('id'))
        ->where('nomor', $nomor)
        ->where('tahun', $tahun)
        ->sole();
}

/**
 * Tulis berkas URL naskah lokal (pengganti PDF yang disalin): kunci => URL.
 *
 * @param  array<string, string>  $tautan
 */
function tulisTautanLokal(array $tautan): void
{
    file_put_contents((string) config('regulasi.seed_tautan_lokal'), '<?php return '.var_export($tautan, true).';');
}

test('tanpa tautan naskah seeder tidak gagal: dokumen tetap draf, rujukan terbit, relasi rujukan berlaku', function () {
    $this->artisan('db:seed', ['--class' => DokumenContohSeeder::class])
        ->expectsOutputToContain('Tautan naskah belum diisi')
        ->assertSuccessful();

    expect(Dokumen::count())->toBe(JUMLAH_DOKUMEN_CONTOH + JUMLAH_RUJUKAN)
        ->and(Dokumen::query()->where('tanpa_berkas', true)->where('status_publikasi', StatusPublikasi::Terbit->value)->count())->toBe(JUMLAH_RUJUKAN)
        ->and(Dokumen::query()->where('tanpa_berkas', false)->where('status_publikasi', StatusPublikasi::Draf->value)->count())->toBe(JUMLAH_DOKUMEN_CONTOH)
        ->and(TautanBerkas::count())->toBe(0)
        ->and(DokumenRelasi::count())->toBe(JUMLAH_RELASI * 2);

    // Sumber draf → belum efektif; R8 (rujukan terbit) mengubah R7.
    expect(dokumenContoh('BAN-PT', 'PERLEMBAGA', '5', 2019)->status_berlaku)->toBe(StatusBerlaku::Berlaku)
        ->and(dokumenContoh('BAN-PT', 'PERLEMBAGA', '22', 2025)->status_berlaku)->toBe(StatusBerlaku::Diubah);

    // Fakta yang tidak pasti tidak dikarang.
    $pp28 = dokumenContoh('Pemerintah Republik Indonesia', 'PP', '28', 2020);
    $permen56 = dokumenContoh('Kemendikbudristek', 'PERMEN', '56', 2022);
    expect($pp28->tanggal_penetapan)->toBeNull()
        ->and($pp28->catatan)->toContain('perlu verifikasi')
        ->and($permen56->judul)->not->toContain('perlu verifikasi')
        ->and($permen56->catatan)->toContain('perlu verifikasi');

    // Relasi 39/2025 → 53/2023 sengaja tidak dibuat (perlu verifikasi).
    $permen39 = dokumenContoh('Kemendiktisaintek', 'PERMEN', '39', 2025);
    expect(DokumenRelasi::query()->where('dokumen_id', $permen39->id)->exists())->toBeFalse();
});

test('dengan tautan naskah: BAN-PT 5/2019 & 5/2023 dicabut, PP 27/2014 diubah setelah PP 28/2020 diterbitkan', function () {
    tulisTautanLokal([
        'banpt-13-2025' => 'https://drive.google.com/file/d/1BanPt13Tahun2025NaskahUtamaAbcdef/view',
        'pp-28-2020' => 'https://peraturan.go.id/files/pp28-2020.pdf',
        'permendiktisaintek-42-2025' => 'https://drive.google.com/file/d/1Permen42Tahun2025NaskahUtamaXyz/view',
        'lamdik-5-2025' => 'https://drive.google.com/file/d/1Lamdik5Tahun2025NaskahUtamaQwerty/view',
    ]);

    $this->seed(DokumenContohSeeder::class);

    expect(Dokumen::count())->toBe(JUMLAH_DOKUMEN_CONTOH + JUMLAH_RUJUKAN)
        ->and(TautanBerkas::count())->toBe(4)
        ->and(dokumenContoh('BAN-PT', 'PERLEMBAGA', '13', 2025)->status_publikasi)->toBe(StatusPublikasi::Terbit)
        ->and(dokumenContoh('BAN-PT', 'PERLEMBAGA', '5', 2019)->status_berlaku)->toBe(StatusBerlaku::Dicabut)
        ->and(dokumenContoh('BAN-PT', 'PERLEMBAGA', '5', 2023)->status_berlaku)->toBe(StatusBerlaku::Dicabut)
        ->and(dokumenContoh('LAMDIK', 'PERLEMBAGA', '3', 2025)->status_berlaku)->toBe(StatusBerlaku::Dicabut)
        ->and(dokumenContoh('LAMDIK', 'PERLEMBAGA', '4', 2025)->status_berlaku)->toBe(StatusBerlaku::Dicabut);

    // PP 28/2020 belum punya tanggal penetapan → tetap draf, PP 27/2014 tetap berlaku.
    $pp28 = dokumenContoh('Pemerintah Republik Indonesia', 'PP', '28', 2020);
    $pp27 = dokumenContoh('Pemerintah Republik Indonesia', 'PP', '27', 2014);
    expect($pp28->status_publikasi)->toBe(StatusPublikasi::Draf)
        ->and($pp27->status_berlaku)->toBe(StatusBerlaku::Berlaku);

    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $pp28->load(['kategoriSistem', 'tag']);
    app(SimpanDokumen::class)->handle([
        'tanggal_penetapan' => '2020-05-01',
        'kategori_sistem' => $pp28->kategoriSistem->pluck('id')->all(),
    ], $admin, $pp28);
    app(TerbitkanDokumen::class)->handle($pp28->refresh(), $admin);

    expect($pp27->refresh()->status_berlaku)->toBe(StatusBerlaku::Diubah);

    // Permendikbud 3/2021 dicabut dan halaman relasinya menyebut Permendiktisaintek 42/2025.
    $permen3 = dokumenContoh('Kemendikbud', 'PERMEN', '3', 2021);
    expect($permen3->status_berlaku)->toBe(StatusBerlaku::Dicabut)
        ->and(RingkasanStatus::untuk($permen3)['kalimat'])->toBe('Dicabut oleh Permendiktisaintek 42/2025 sejak 15 Oktober 2025.')
        ->and(DokumenRelasi::query()->where('dokumen_id', $permen3->id)->sole()->keterangan)->toBe('Untuk lingkungan Kemendiktisaintek (pasal penutup perlu verifikasi)');

    Filament::setCurrentPanel('admin');
    Livewire::actingAs($admin)
        ->test(RelasiRelationManager::class, ['ownerRecord' => $permen3, 'pageClass' => ViewDokumen::class])
        ->assertSee('Permendiktisaintek 42/2025')
        ->assertSee(JenisRelasi::DicabutOleh->label());
});

test('menjalankan seeder dua kali tidak menggandakan data', function () {
    $this->seed(DokumenContohSeeder::class);
    $jumlah = [Dokumen::count(), DokumenRelasi::count(), RiwayatStatusDokumen::count()];

    $this->seed(DokumenContohSeeder::class);

    expect([Dokumen::count(), DokumenRelasi::count(), RiwayatStatusDokumen::count()])->toBe($jumlah)
        ->and($jumlah[0])->toBe(JUMLAH_DOKUMEN_CONTOH + JUMLAH_RUJUKAN);
});

test('mode demo menerbitkan semua dokumen hanya di lingkungan lokal', function () {
    config(['regulasi.seed_terbitkan_semua' => true]);

    $this->seed(DokumenContohSeeder::class);
    expect(Dokumen::query()->where('status_publikasi', StatusPublikasi::Draf->value)->count())->toBe(JUMLAH_DOKUMEN_CONTOH);

    app()->detectEnvironment(fn (): string => 'local');
    $this->seed(DokumenContohSeeder::class);

    expect(Dokumen::query()->where('status_publikasi', '!=', StatusPublikasi::Terbit->value)->count())->toBe(0)
        ->and(dokumenContoh('BAN-PT', 'PERLEMBAGA', '5', 2019)->status_berlaku)->toBe(StatusBerlaku::Dicabut)
        ->and(dokumenContoh('Pemerintah Republik Indonesia', 'PP', '27', 2014)->status_berlaku)->toBe(StatusBerlaku::Diubah)
        ->and(RiwayatStatusDokumen::query()->where('catatan', DokumenContohSeeder::CATATAN_DEMO)->count())->toBe(JUMLAH_DOKUMEN_CONTOH);
});

test('tanpa super-admin seeder melewati dokumen contoh tanpa gagal', function () {
    $this->superAdmin->removeRole(Peran::SuperAdmin->value);

    $this->artisan('db:seed', ['--class' => DokumenContohSeeder::class])->assertSuccessful();

    expect(Dokumen::count())->toBe(0)->and(User::count())->toBe(1);
});
