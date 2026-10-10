<?php

use App\Enums\JenisTautan;
use App\Enums\PenyediaTautan;
use App\Enums\Peran;
use App\Filament\Admin\Resources\DokumenResource\Pages\ListDokumen;
use App\Models\Dokumen;
use App\Models\TautanBerkas;
use Database\Seeders\PerfSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/**
 * F12.2: data uji kinerja hanya lokal, indeks daftar publik, dan tabel admin ramping tanpa N+1.
 */
uses(RefreshDatabase::class);

test('PerfSeeder menolak lingkungan selain local', function (string $lingkungan) {
    $this->app->detectEnvironment(fn () => $lingkungan);

    expect(fn () => app(PerfSeeder::class)->run())->toThrow(RuntimeException::class, 'APP_ENV=local');
    expect(Dokumen::query()->count())->toBe(0);
})->with(['testing', 'production', 'staging']);

test('indeks daftar publik terbaru tersedia', function () {
    expect(collect(Schema::getIndexes('dokumen'))->firstWhere('name', 'idx_dokumen_publik_terbaru')['columns'] ?? null)
        ->toBe(['status_publikasi', 'visibilitas', 'dipublikasikan_pada']);
});

test('tabel admin dokumen memuat kolom terbatas, jumlah lampiran, dan query tetap konstan', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = Dokumen::factory()->count(3)->create(['abstrak' => 'Abstrak panjang yang tidak perlu dimuat tabel.']);

    foreach (range(1, 2) as $i) {
        TautanBerkas::create([
            'pemilik_type' => 'dokumen',
            'pemilik_id' => $dokumen[0]->id,
            'jenis' => JenisTautan::Lampiran,
            'url' => "https://drive.google.com/file/d/1KinerjaLampiran000000{$i}/view",
            'penyedia' => PenyediaTautan::GoogleDrive,
            'drive_file_id' => "1KinerjaLampiran000000{$i}",
        ]);
    }

    $this->actingAs($admin);
    DB::enableQueryLog();
    $komponen = Livewire::test(ListDokumen::class)->assertCanSeeTableRecords($dokumen);
    $jumlahQuery = count(DB::getQueryLog());
    $rekaman = $komponen->instance()->getTableRecords()->keyBy('id');

    expect($rekaman[$dokumen[0]->id]->jumlah_lampiran)->toBe(2)
        ->and($rekaman[$dokumen[1]->id]->jumlah_lampiran)->toBe(0)
        ->and(array_key_exists('abstrak', $rekaman[$dokumen[0]->id]->getAttributes()))->toBeFalse();

    Dokumen::factory()->count(10)->create();
    DB::flushQueryLog();
    Livewire::test(ListDokumen::class);

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual($jumlahQuery + 2);
});

test('hapus dari tabel admin memakai model lengkap sehingga atribut tidak hilang', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = Dokumen::factory()->create(['abstrak' => 'Tetap utuh']);

    Livewire::actingAs($admin)->test(ListDokumen::class)->callTableAction('delete', $dokumen);

    $terhapus = Dokumen::withTrashed()->findOrFail($dokumen->id);

    expect($terhapus->trashed())->toBeTrue()
        ->and($terhapus->abstrak)->toBe('Tetap utuh');
});
