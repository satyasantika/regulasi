<?php

use App\Enums\StatusCekTautan;
use App\Jobs\PeriksaTautanBerkas;
use App\Models\Dokumen;
use App\Models\TautanBerkas;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    dnsPalsu();
});

function tautanUntukDiperiksa(string $id = '1PeriksaAbCdEfGh', StatusCekTautan $status = StatusCekTautan::Belum): TautanBerkas
{
    $tautan = TautanBerkas::query()->create([
        'pemilik_type' => 'dokumen',
        'pemilik_id' => Dokumen::factory()->create()->getKey(),
        'jenis' => 'naskah_utama',
        'url' => "https://drive.google.com/file/d/{$id}/view",
        'penyedia' => 'google_drive',
        'drive_file_id' => $id,
    ]);
    $tautan->forceFill(['status_cek' => $status])->save();

    return $tautan;
}

function jalankanPemeriksaan(TautanBerkas $tautan): PeriksaTautanBerkas
{
    $job = (new PeriksaTautanBerkas($tautan))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    return $job;
}

test('tautan yang menjawab HEAD 200 berstatus dapat diakses', function () {
    Http::fake(['drive.google.com/*' => Http::response('', 200)]);
    $tautan = tautanUntukDiperiksa();

    jalankanPemeriksaan($tautan);

    expect($tautan->fresh()->status_cek)->toBe(StatusCekTautan::DapatDiakses)
        ->and($tautan->fresh()->dicek_pada)->not->toBeNull();
    Http::assertSent(fn ($r): bool => $r->method() === 'HEAD');
});

test('berubah menjadi tidak dapat diakses mencatat peringatan, tidak berulang bila sudah', function () {
    Log::spy();
    Http::fake(['drive.google.com/*' => Http::response('', 404)]);

    $baru = tautanUntukDiperiksa();
    jalankanPemeriksaan($baru);
    expect($baru->fresh()->status_cek)->toBe(StatusCekTautan::TidakDapatDiakses);
    Log::shouldHaveReceived('warning')->once();

    $lama = tautanUntukDiperiksa('1SudahMatiAbCdE', StatusCekTautan::TidakDapatDiakses);
    jalankanPemeriksaan($lama);
    Log::shouldHaveReceived('warning')->once();
});

test('host yang mengarah ke IP privat dianggap tidak dapat diakses tanpa permintaan', function () {
    dnsPalsu(['*' => ['10.0.0.5']]);
    Http::fake();
    $tautan = tautanUntukDiperiksa();

    jalankanPemeriksaan($tautan);

    expect($tautan->fresh()->status_cek)->toBe(StatusCekTautan::TidakDapatDiakses);
    Http::assertNothingSent();
});

test('job memakai antrean tautan, unik per tautan, dan dilepas ulang saat batas host tercapai', function () {
    config(['regulasi.unduh_per_menit_per_host' => 1]);
    Http::fake(['drive.google.com/*' => Http::response('', 200)]);
    $tautan = tautanUntukDiperiksa();

    $job = new PeriksaTautanBerkas($tautan);
    expect($job->queue)->toBe('default')
        ->and($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe($tautan->getKey());

    jalankanPemeriksaan($tautan)->assertNotReleased();
    jalankanPemeriksaan($tautan)->assertReleased(60);
});

test('tautan yang sudah dihapus dilewati', function () {
    Http::fake();
    $tautan = tautanUntukDiperiksa();
    $tautan->delete();

    jalankanPemeriksaan($tautan);

    Http::assertNothingSent();
});
