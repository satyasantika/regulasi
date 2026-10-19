<?php

use App\Enums\Peran;
use App\Enums\StatusCekTautan;
use App\Enums\StatusEkstraksi;
use App\Filament\Admin\Pages\TautanBermasalah;
use App\Filament\Admin\Resources\DokumenResource\Pages\ViewDokumen;
use App\Filament\Admin\Resources\DokumenResource\RelationManagers\TautanBerkasRelationManager;
use App\Jobs\EkstrakTeksTautan;
use App\Jobs\PeriksaTautanBerkas;
use App\Models\Dokumen;
use App\Models\EkstraksiTeks;
use App\Models\TautanBerkas;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function tautanBerstatus(?StatusEkstraksi $ekstraksi, StatusCekTautan $cek = StatusCekTautan::DapatDiakses, array $tambahan = []): TautanBerkas
{
    $tautan = TautanBerkas::query()->create([
        'pemilik_type' => 'dokumen',
        'pemilik_id' => Dokumen::factory()->create()->getKey(),
        'jenis' => 'naskah_utama',
        'url' => 'https://lppm.unsil.ac.id/'.fake()->unique()->uuid().'.pdf',
        'penyedia' => 'unsil',
    ]);
    $tautan->forceFill(['status_cek' => $cek])->save();

    if ($ekstraksi !== null) {
        EkstraksiTeks::query()->create(array_merge(['tautan_berkas_id' => $tautan->getKey(), 'status' => $ekstraksi], $tambahan));
    }

    return $tautan;
}

test('halaman hanya untuk admin', function () {
    $this->actingAs(penggunaDenganPeran(Peran::Kontributor))->get('/admin/tautan-bermasalah')->assertForbidden();
    $this->actingAs(penggunaDenganPeran(Peran::AdminRegulasi))->get('/admin/tautan-bermasalah')->assertOk();
});

test('menampilkan tautan mati, ekstraksi bermasalah, dan isi berubah saja', function () {
    $mati = tautanBerstatus(null, StatusCekTautan::TidakDapatDiakses);
    $pindaian = tautanBerstatus(StatusEkstraksi::TanpaTeks);
    $gagal = tautanBerstatus(StatusEkstraksi::Gagal);
    $berubah = tautanBerstatus(StatusEkstraksi::Berhasil, tambahan: ['isi_berubah_pada' => now()]);
    $sehat = tautanBerstatus(StatusEkstraksi::Berhasil);
    $belum = tautanBerstatus(StatusEkstraksi::Belum, StatusCekTautan::Belum);

    Livewire::actingAs(penggunaDenganPeran(Peran::AdminRegulasi))->test(TautanBermasalah::class)
        ->assertCanSeeTableRecords([$mati, $pindaian, $gagal, $berubah])
        ->assertCanNotSeeTableRecords([$sehat, $belum])
        ->filterTable('masalah', 'isi_berubah')
        ->assertCanSeeTableRecords([$berubah])
        ->assertCanNotSeeTableRecords([$mati, $pindaian, $gagal]);
});

test('aksi periksa ulang, ekstrak ulang, massal, dan tandai ditinjau', function () {
    Queue::fake();
    $gagal = tautanBerstatus(StatusEkstraksi::Gagal);
    $berubah = tautanBerstatus(StatusEkstraksi::Berhasil, tambahan: ['isi_berubah_pada' => now()]);

    $halaman = Livewire::actingAs(penggunaDenganPeran(Peran::AdminRegulasi))->test(TautanBermasalah::class)
        ->callAction(TestAction::make('periksaUlang')->table($gagal))
        ->callAction(TestAction::make('ekstrakUlang')->table($gagal));

    Queue::assertPushedOn('default', PeriksaTautanBerkas::class);
    Queue::assertPushedOn('berat', EkstrakTeksTautan::class, fn (EkstrakTeksTautan $job): bool => $job->tautanId === $gagal->getKey());

    $halaman->selectTableRecords([$gagal, $berubah])
        ->callAction(TestAction::make('ekstrakUlangMassal')->table()->bulk())
        ->assertNotified('2 ekstraksi teks diantrekan');

    $halaman->callAction(TestAction::make('tandaiDitinjau')->table($berubah));

    expect($berubah->ekstraksi()->first()->isi_berubah_pada)->toBeNull();
});

test('panel kelengkapan dan relation manager membaca status ekstraksi naskah utama', function () {
    $tautan = tautanBerstatus(StatusEkstraksi::Berhasil);
    $dokumen = Dokumen::query()->findOrFail($tautan->pemilik_id);

    $this->actingAs(penggunaDenganPeran(Peran::AdminRegulasi))
        ->get("/admin/dokumen/{$dokumen->getKey()}")
        ->assertOk()
        ->assertSee('Teks terekstraksi');

    Livewire::actingAs(penggunaDenganPeran(Peran::AdminRegulasi))
        ->test(TautanBerkasRelationManager::class, [
            'ownerRecord' => $dokumen,
            'pageClass' => ViewDokumen::class,
        ])
        ->assertCanSeeTableRecords([$tautan])
        ->assertSee('Berhasil');
});
