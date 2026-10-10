<?php

use App\Enums\StatusEkstraksi;
use App\Jobs\EkstrakTeksTautan;
use App\Models\Dokumen;
use App\Models\EkstraksiTeks;
use App\Models\TautanBerkas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function tautanBerekstraksi(StatusEkstraksi $status, int $percobaan = 0): TautanBerkas
{
    $tautan = TautanBerkas::query()->create([
        'pemilik_type' => 'dokumen',
        'pemilik_id' => Dokumen::factory()->create()->getKey(),
        'jenis' => 'lampiran',
        'url' => 'https://lppm.unsil.ac.id/'.fake()->unique()->uuid().'.pdf',
        'penyedia' => 'unsil',
    ]);
    EkstraksiTeks::query()->create(['tautan_berkas_id' => $tautan->getKey(), 'status' => $status, 'percobaan' => $percobaan]);

    return $tautan;
}

test('--gagal hanya mengantrekan ekstraksi gagal dengan percobaan di bawah 5', function () {
    Queue::fake();
    $ulang = tautanBerekstraksi(StatusEkstraksi::Gagal, 4);
    tautanBerekstraksi(StatusEkstraksi::Gagal, 5);
    tautanBerekstraksi(StatusEkstraksi::TidakDapatDiunduh);
    tautanBerekstraksi(StatusEkstraksi::Berhasil);

    $this->artisan('regulasi:ekstraksi-ulang', ['--gagal' => true])->expectsOutput('1 ekstraksi diantrekan ulang.')->assertSuccessful();

    Queue::assertPushedOn('ekstraksi', EkstrakTeksTautan::class, fn (EkstrakTeksTautan $job): bool => $job->tautanId === $ulang->getKey());
    Queue::assertPushed(EkstrakTeksTautan::class, 1);
});

test('--tanpa-teks dan --tautan mengantrekan sasaran yang dipilih', function () {
    Queue::fake();
    $pindaian = tautanBerekstraksi(StatusEkstraksi::TanpaTeks);
    $tertentu = tautanBerekstraksi(StatusEkstraksi::TerlaluBesar);
    tautanBerekstraksi(StatusEkstraksi::Gagal, 1);

    $this->artisan('regulasi:ekstraksi-ulang', ['--tanpa-teks' => true, '--tautan' => [$tertentu->getKey()]])->assertSuccessful();

    Queue::assertPushed(EkstrakTeksTautan::class, 2);
    Queue::assertPushed(EkstrakTeksTautan::class, fn (EkstrakTeksTautan $job): bool => $job->tautanId === $pindaian->getKey());
    Queue::assertPushed(EkstrakTeksTautan::class, fn (EkstrakTeksTautan $job): bool => $job->tautanId === $tertentu->getKey());
});

test('tanpa opsi perintah ekstraksi-ulang menolak berjalan', function () {
    Queue::fake();

    $this->artisan('regulasi:ekstraksi-ulang')->assertExitCode(2);

    Queue::assertNothingPushed();
});

test('berkas:bersihkan-tmp menghapus berkas sementara berumur lebih dari 24 jam saja', function () {
    $folder = storage_path('app/tmp/uji-bersih-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($folder);
    $lama = "{$folder}/lama.pdf";
    $baru = "{$folder}/baru.pdf";
    File::put($lama, '%PDF-');
    File::put($baru, '%PDF-');
    touch($lama, now()->subHours(25)->getTimestamp());

    $this->artisan('berkas:bersihkan-tmp')->assertSuccessful();

    expect(File::exists($lama))->toBeFalse()->and(File::exists($baru))->toBeTrue();
    File::deleteDirectory($folder);
});
