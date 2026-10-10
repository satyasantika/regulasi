<?php

use App\Enums\JenisTautan;
use App\Enums\PenyediaTautan;
use App\Enums\Peran;
use App\Enums\StatusBerlaku;
use App\Enums\StatusEkstraksi;
use App\Filament\Admin\Pages\LaporanKelengkapan;
use App\Filament\Admin\Pages\LaporanStatistik;
use App\Jobs\BuatEksporLaporan;
use App\Models\Dokumen;
use App\Models\Ekspor;
use App\Models\EkstraksiTeks;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\Tag;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Notifications\EksporLaporanSiap;
use App\Support\Laporan\LingkupLaporan;
use App\Support\Laporan\StatistikKoleksi;
use Database\Seeders\JenisDokumenSeeder;
use Database\Seeders\KategoriSistemSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Bus\ChainedBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->seed([JenisDokumenSeeder::class, KategoriSistemSeeder::class]);
});

afterEach(function () {
    File::deleteDirectory(storage_path('app/tmp/'.BuatEksporLaporan::DIREKTORI));
});

/**
 * @param  array<string, mixed>  $atribut
 */
function koleksiDokumen(string $jenis, string $kategori = 'aset', StatusBerlaku $status = StatusBerlaku::Berlaku, array $atribut = []): Dokumen
{
    $dokumen = Dokumen::factory()->terbit()->create([
        'jenis_dokumen_id' => JenisDokumen::where('kode', $jenis)->value('id'),
        ...$atribut,
    ]);
    $dokumen->forceFill(['status_berlaku' => $status])->save();
    $dokumen->kategoriSistem()->attach(KategoriSistem::where('kode', $kategori)->value('id'));

    return $dokumen;
}

function koleksiDokumenLengkap(): Dokumen
{
    $dokumen = koleksiDokumen('PERMEN', atribut: [
        'abstrak' => 'Abstrak lengkap.',
        'tanggal_penetapan' => '2024-01-02',
        'tanggal_berlaku' => '2024-01-03',
        'sumber' => 'BN 2024 (1)',
    ]);
    $dokumen->tag()->attach(Tag::factory()->create());
    $tautan = TautanBerkas::query()->create([
        'pemilik_type' => 'dokumen',
        'pemilik_id' => $dokumen->id,
        'jenis' => JenisTautan::NaskahUtama,
        'url' => 'https://lppm.unsil.ac.id/'.fake()->uuid().'.pdf',
        'penyedia' => PenyediaTautan::Unsil,
    ]);
    EkstraksiTeks::query()->create(['tautan_berkas_id' => $tautan->id, 'status' => StatusEkstraksi::Berhasil, 'isi_teks' => 'Isi.']);

    return $dokumen;
}

function kontributorKoleksi(string $kode): User
{
    $kontributor = penggunaDenganPeran(Peran::Kontributor);
    $kontributor->kategoriSistem()->attach(KategoriSistem::where('kode', $kode)->value('id'));

    return $kontributor;
}

test('angka per jenis dan status berlaku benar', function () {
    koleksiDokumen('PERMEN');
    koleksiDokumen('PERMEN', status: StatusBerlaku::Dicabut);
    koleksiDokumen('PERMEN', 'akreditasi', StatusBerlaku::Diubah);
    koleksiDokumen('UU');
    Dokumen::factory()->create(['jenis_dokumen_id' => JenisDokumen::where('kode', 'UU')->value('id')]); // draf

    $baris = collect(StatistikKoleksi::pivot(LingkupLaporan::global(), 'jenis'))->keyBy('label');

    expect($baris['Peraturan Menteri'])->toMatchArray(['berlaku' => 1, 'diubah' => 1, 'dicabut' => 1, 'tidak_berlaku' => 0, 'total' => 3])
        ->and($baris['Undang-Undang']['total'])->toBe(1)
        ->and(collect(StatistikKoleksi::pivot(LingkupLaporan::global(), 'jenis', StatistikKoleksi::SEMUA_STATUS))->firstWhere('label', 'Undang-Undang')['total'])->toBe(2)
        ->and(collect(StatistikKoleksi::pivot(LingkupLaporan::global(), 'kategori'))->firstWhere('label', 'Aset/BMN')['total'] ?? null)->not->toBeNull();

    Livewire::actingAs(penggunaDenganPeran(Peran::AdminRegulasi))->test(LaporanStatistik::class)
        ->assertOk()
        ->assertSee('Peraturan Menteri')
        ->filterTable('dimensi', 'status_berlaku')
        ->assertSee('Berlaku dengan Perubahan');
});

test('kontributor hanya melihat statistik kategori sendiri dan tidak dapat mengekspor', function () {
    koleksiDokumen('PERMEN', 'aset');
    koleksiDokumen('UU', 'akreditasi');
    $kontributor = kontributorKoleksi('aset');

    $baris = StatistikKoleksi::pivot(LingkupLaporan::untuk($kontributor), 'jenis');

    expect(collect($baris)->pluck('label')->all())->toBe(['Peraturan Menteri']);

    $this->actingAs($kontributor);
    Livewire::test(LaporanStatistik::class)
        ->assertDontSee('Undang-Undang')
        ->assertActionHidden('eksporExcel');
    Livewire::test(LaporanKelengkapan::class)
        ->assertActionHidden(TestAction::make('eksporExcel')->table());

    Queue::fake();
    (new BuatEksporLaporan('statistik', [], $kontributor->id))->handle();
    expect(File::isDirectory(storage_path('app/tmp/ekspor')) ? File::files(storage_path('app/tmp/ekspor')) : [])->toBe([]);
});

test('filter belum lengkap menampilkan dokumen tanpa abstrak', function () {
    $lengkap = koleksiDokumenLengkap();
    $tanpaAbstrak = koleksiDokumen('PERMEN', atribut: ['abstrak' => null]);
    $draf = Dokumen::factory()->create();

    Livewire::actingAs(penggunaDenganPeran(Peran::AdminRegulasi))->test(LaporanKelengkapan::class)
        ->assertCanSeeTableRecords([$lengkap, $tanpaAbstrak])
        ->assertCanNotSeeTableRecords([$draf])
        ->assertTableColumnStateSet('skor_kelengkapan', 100, $lengkap)
        ->assertTableColumnStateSet('lengkap_abstrak', 0, $tanpaAbstrak)
        ->filterTable('belum_lengkap', true)
        ->assertCanSeeTableRecords([$tanpaAbstrak])
        ->assertCanNotSeeTableRecords([$lengkap]);
});

test('ekspor statistik masuk antrean ekspor dan menghasilkan berkas berheader bahasa Indonesia', function () {
    koleksiDokumen('PERMEN');
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);

    Queue::fake();
    Livewire::actingAs($admin)->test(LaporanStatistik::class)
        ->callAction('eksporExcel')
        ->assertNotified('Ekspor diantrekan');

    Queue::assertPushedOn('ekspor', BuatEksporLaporan::class, fn (BuatEksporLaporan $job): bool => $job->jenis === 'statistik'
        && $job->parameter['dimensi'] === 'jenis'
        && $job->penggunaId === $admin->id);

    Notification::fake();
    (new BuatEksporLaporan('statistik', ['dimensi' => 'jenis', 'status_publikasi' => 'terbit'], $admin->id))->handle();

    $berkas = File::files(storage_path('app/tmp/ekspor'));
    expect($berkas)->toHaveCount(1);

    $baris = SimpleExcelReader::create($berkas[0]->getPathname())->getRows()->all();
    expect(array_keys($baris[0]))->toBe(['Jenis dokumen', 'Berlaku', 'Berlaku dengan Perubahan', 'Dicabut', 'Tidak Berlaku', 'Total'])
        ->and($baris[0]['Jenis dokumen'])->toBe('Peraturan Menteri')
        ->and($baris[0]['Total'])->toBe(1);

    Notification::assertSentTo($admin, EksporLaporanSiap::class, function (EksporLaporanSiap $n) use ($admin, $berkas): bool {
        $url = $n->urlUnduh;
        $this->actingAs($admin)->get($url)->assertOk()->assertDownload();

        // Pengguna lain (meski memegang laporan.ekspor dan URL bertanda tangan yang sama) ditolak.
        $this->actingAs(penggunaDenganPeran(Peran::AdminRegulasi))->get($url)->assertForbidden();
        // URL diubah → tanda tangan tidak sah.
        $this->actingAs($admin)->get(str_replace($berkas[0]->getFilename(), '00000000-0000-0000-0000-000000000000.xlsx', $url))->assertForbidden();

        return true;
    });
});

test('ekspor kelengkapan lewat Filament masuk antrean ekspor', function () {
    koleksiDokumen('PERMEN');
    Queue::fake();

    Livewire::actingAs(penggunaDenganPeran(Peran::AdminRegulasi))->test(LaporanKelengkapan::class)
        ->callAction(TestAction::make('eksporExcel')->table());

    Queue::assertPushedOn('ekspor', ChainedBatch::class);
    expect(Ekspor::query()->sole()->file_disk)->toBe('tmp');
});

test('berkas ekspor kelengkapan berbahasa Indonesia dan tidak dapat diunduh pengguna lain', function () {
    Storage::fake('tmp');
    koleksiDokumenLengkap();
    koleksiDokumen('PERMEN', atribut: ['abstrak' => null]);
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);

    Livewire::actingAs($admin)->test(LaporanKelengkapan::class)
        ->callAction(TestAction::make('eksporExcel')->table());

    $ekspor = Ekspor::query()->sole();
    $berkas = collect(Storage::disk('tmp')->allFiles($ekspor->getFileDirectory()))->first(fn (string $f): bool => str_ends_with($f, '.xlsx'));
    expect($berkas)->not->toBeNull()
        ->and($ekspor->successful_rows)->toBe(2);

    $baris = SimpleExcelReader::create(Storage::disk('tmp')->path($berkas))->getRows()->all();
    expect(array_keys($baris[0]))->toContain('Sebutan', 'Abstrak', 'Naskah utama', 'Teks terekstraksi', 'Skor kelengkapan (%)');

    $url = route('filament.exports.download', ['export' => $ekspor, 'format' => 'xlsx']);
    $this->actingAs($admin)->get($url)->assertOk();
    $this->actingAs(penggunaDenganPeran(Peran::AdminRegulasi))->get($url)->assertForbidden();
    $this->actingAs(kontributorKoleksi('aset'))->get($url)->assertForbidden();
});
