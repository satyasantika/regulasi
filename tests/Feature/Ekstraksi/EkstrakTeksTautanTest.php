<?php

use App\Actions\Berkas\SimpanTautanBerkas;
use App\Enums\Peran;
use App\Enums\StatusEkstraksi;
use App\Events\IsiBerkasBerubah;
use App\Events\TeksTautanDiekstraksi;
use App\Jobs\EkstrakTeksTautan;
use App\Jobs\PeriksaTautanBerkas;
use App\Models\Dokumen;
use App\Models\EkstraksiTeks;
use App\Models\TautanBerkas;
use App\Services\Berkas\PengekstrakTeksPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

const ID_EKSTRAKSI = '1TataNaskahDinasAbCdEf';

beforeEach(function () {
    $this->folder = 'tmp/ekstraksi-uji-'.bin2hex(random_bytes(4));
    config(['berkas.folder_sementara' => $this->folder]);
    dnsPalsu();
});

afterEach(function () {
    File::deleteDirectory(storage_path('app/'.$this->folder));
});

function pdfBerteks(string $kalimat): string
{
    $paragraf = str_repeat("<p>{$kalimat}. Pedoman ini mengatur tata naskah dinas di lingkungan fakultas.</p>", 8);

    return Pdf::loadHTML("<html><body><h1>{$kalimat}</h1>{$paragraf}</body></html>")->output();
}

function pdfTanpaTeks(): string
{
    return Pdf::loadHTML('<html><body><div style="width:400px;height:300px;background:#333"></div></body></html>')->output();
}

/**
 * Tautan Drive yang uc?export=download-nya dialihkan ke drive.usercontent.google.com; tiap unduhan berikutnya
 * menerima isi berikutnya dari $isi.
 *
 * @param  list<string>  $isi
 */
function palsukanDrive(array $isi, string $tipe = 'application/pdf'): void
{
    $urutan = Http::sequence();

    foreach ($isi as $butir) {
        $urutan->push($butir, 200, ['Content-Type' => $tipe]);
    }

    Http::fake([
        'drive.google.com/uc*' => Http::response('', 303, ['Location' => 'https://drive.usercontent.google.com/download?id='.ID_EKSTRAKSI.'&export=download']),
        'drive.usercontent.google.com/*' => $urutan,
    ]);
}

function tautanEkstraksi(string $jenis = 'naskah_utama'): TautanBerkas
{
    Queue::fake();
    $tautan = app(SimpanTautanBerkas::class)->handle(Dokumen::factory()->create(), [
        'url' => 'https://drive.google.com/file/d/'.ID_EKSTRAKSI.'/view?usp=sharing',
        'jenis' => $jenis,
    ], penggunaDenganPeran(Peran::AdminRegulasi))['tautan'];

    return $tautan;
}

function jalankanEkstraksi(TautanBerkas $tautan): EkstrakTeksTautan
{
    $job = (new EkstrakTeksTautan($tautan->getKey()))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    return $job;
}

function ekstraksiDari(TautanBerkas $tautan): EkstraksiTeks
{
    return EkstraksiTeks::query()->where('tautan_berkas_id', $tautan->getKey())->firstOrFail();
}

$butuhPdftotext = fn (): bool => ! is_executable((string) config('regulasi.pdftotext_bin'));

test('PDF berteks diekstraksi, isi memuat frasa, berkas sementara terhapus', function () {
    Event::fake([TeksTautanDiekstraksi::class, IsiBerkasBerubah::class]);
    $tautan = tautanEkstraksi();
    palsukanDrive([pdfBerteks('Tata Naskah Dinas')]);

    jalankanEkstraksi($tautan)->assertNotFailed()->assertNotReleased();

    $ekstraksi = ekstraksiDari($tautan);
    expect($ekstraksi->status)->toBe(StatusEkstraksi::Berhasil)
        ->and($ekstraksi->isi_teks)->toContain('Tata Naskah Dinas')
        ->and($ekstraksi->jumlah_karakter)->toBeGreaterThan(200)
        ->and($ekstraksi->jumlah_halaman)->toBe(1)
        ->and($ekstraksi->ukuran_unduhan)->toBeGreaterThan(0)
        ->and($ekstraksi->hash_sha256)->toHaveLength(64)
        ->and($ekstraksi->diekstraksi_pada)->not->toBeNull()
        ->and($ekstraksi->isi_berubah_pada)->toBeNull()
        ->and(glob(storage_path("app/{$this->folder}/*")))->toBe([]);

    Event::assertDispatched(TeksTautanDiekstraksi::class, fn (TeksTautanDiekstraksi $e): bool => $e->tautan->is($tautan));
    Event::assertNotDispatched(IsiBerkasBerubah::class);
})->skip($butuhPdftotext, 'pdftotext tidak tersedia');

test('PDF tanpa lapisan teks (pindaian) berstatus tanpa_teks', function () {
    $tautan = tautanEkstraksi();
    palsukanDrive([pdfTanpaTeks()]);

    jalankanEkstraksi($tautan);

    expect(ekstraksiDari($tautan)->status)->toBe(StatusEkstraksi::TanpaTeks)
        ->and(glob(storage_path("app/{$this->folder}/*")))->toBe([]);
})->skip($butuhPdftotext, 'pdftotext tidak tersedia');

test('respons login Drive menjadi tidak_dapat_diunduh tanpa retry', function () {
    $tautan = tautanEkstraksi();
    Http::fake(['drive.google.com/*' => Http::response('', 302, ['Location' => 'https://accounts.google.com/ServiceLogin?continue=x'])]);

    jalankanEkstraksi($tautan)->assertNotFailed()->assertNotReleased();

    $ekstraksi = ekstraksiDari($tautan);
    expect($ekstraksi->status)->toBe(StatusEkstraksi::TidakDapatDiunduh)
        ->and($ekstraksi->pesan)->toContain('masuk akun Google')
        ->and($ekstraksi->percobaan)->toBe(0);
});

test('halaman konfirmasi Drive (HTML), berkas besar, dan bukan PDF menjadi status terkendali', function (string $isi, string $tipe, StatusEkstraksi $status) {
    $tautan = tautanEkstraksi();
    palsukanDrive([$isi], $tipe);

    jalankanEkstraksi($tautan)->assertNotFailed()->assertNotReleased();

    expect(ekstraksiDari($tautan)->status)->toBe($status)
        ->and(glob(storage_path("app/{$this->folder}/*")))->toBe([]);
})->with([
    'HTML' => ['<html>Virus scan warning</html>', 'text/html', StatusEkstraksi::TidakDapatDiunduh],
    'bukan PDF' => ["PK\x03\x04docx", 'application/octet-stream', StatusEkstraksi::BukanPdf],
]);

test('ekstraksi kedua dengan isi berbeda mengisi isi_berubah_pada dan memicu IsiBerkasBerubah', function () {
    Event::fake([IsiBerkasBerubah::class, TeksTautanDiekstraksi::class]);
    $tautan = tautanEkstraksi();

    palsukanDrive([pdfBerteks('Tata Naskah Dinas versi pertama'), pdfBerteks('Tata Naskah Dinas versi kedua yang diganti')]);

    jalankanEkstraksi($tautan);
    $hashPertama = ekstraksiDari($tautan)->hash_sha256;
    expect(ekstraksiDari($tautan)->isi_berubah_pada)->toBeNull();

    jalankanEkstraksi($tautan);

    $ekstraksi = ekstraksiDari($tautan);
    expect($ekstraksi->hash_sha256)->not->toBe($hashPertama)
        ->and($ekstraksi->isi_berubah_pada)->not->toBeNull()
        ->and($ekstraksi->isi_teks)->toContain('versi kedua');

    Event::assertDispatched(IsiBerkasBerubah::class, fn (IsiBerkasBerubah $e): bool => $e->hashLama === $hashPertama);
})->skip($butuhPdftotext, 'pdftotext tidak tersedia');

test('galat tak terduga diulang lalu failed() menandai gagal dan menambah percobaan', function () {
    $tautan = tautanEkstraksi();
    Http::fake(['drive.google.com/*' => Http::response('', 503)]);

    $job = new EkstrakTeksTautan($tautan->getKey());

    expect(fn () => app()->call([$job, 'handle']))->toThrow(RuntimeException::class, 'HTTP 503');
    expect(ekstraksiDari($tautan)->status)->toBe(StatusEkstraksi::Diproses);

    $job->failed(new RuntimeException(str_repeat('galat ', 200)));

    $ekstraksi = ekstraksiDari($tautan);
    expect($ekstraksi->status)->toBe(StatusEkstraksi::Gagal)
        ->and(mb_strlen((string) $ekstraksi->pesan))->toBe(500)
        ->and($ekstraksi->percobaan)->toBe(1)
        ->and($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(300)
        ->and($job->backoff())->toBe([60, 300, 900])
        ->and($job->queue)->toBe('berat')
        ->and(glob(storage_path("app/{$this->folder}/*")))->toBe([]);
});

test('menyimpan tautan naskah mengantrekan pemeriksaan dan ekstraksi; jenis lainnya tidak diekstraksi', function () {
    Queue::fake();
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $dokumen = Dokumen::factory()->create();

    $naskah = app(SimpanTautanBerkas::class)->handle($dokumen, ['url' => 'https://drive.google.com/file/d/'.ID_EKSTRAKSI.'/view', 'jenis' => 'naskah_utama'], $admin)['tautan'];

    Queue::assertPushedOn('default', PeriksaTautanBerkas::class);
    Queue::assertPushedOn('berat', EkstrakTeksTautan::class, fn (EkstrakTeksTautan $job): bool => $job->tautanId === $naskah->getKey());
    expect(ekstraksiDari($naskah)->status)->toBe(StatusEkstraksi::Belum);

    Queue::fake();
    $sumber = app(SimpanTautanBerkas::class)->handle($dokumen, ['url' => 'https://peraturan.go.id/id/permendikbudristek-no-53-tahun-2023', 'jenis' => 'halaman_sumber'], $admin)['tautan'];

    Queue::assertPushed(PeriksaTautanBerkas::class);
    Queue::assertNotPushed(EkstrakTeksTautan::class);
    expect(ekstraksiDari($sumber)->status)->toBe(StatusEkstraksi::TidakBerlaku);
});

test('perubahan URL mereset status cek dan ekstraksi lalu mengantrekan ulang', function () {
    $tautan = tautanEkstraksi();
    ekstraksiDari($tautan)->forceFill(['status' => StatusEkstraksi::Berhasil, 'isi_teks' => 'lama', 'hash_sha256' => str_repeat('a', 64)])->save();
    $tautan->forceFill(['status_cek' => 'dapat_diakses'])->save();

    Queue::fake();
    app(SimpanTautanBerkas::class)->handle($tautan->pemilik()->first(), ['url' => 'https://drive.google.com/file/d/1BerkasBaruXyZ123/view'], penggunaDenganPeran(Peran::AdminRegulasi), $tautan->fresh());

    $ekstraksi = ekstraksiDari($tautan);
    expect($tautan->fresh()->status_cek->value)->toBe('belum')
        ->and($ekstraksi->status)->toBe(StatusEkstraksi::Belum)
        ->and($ekstraksi->isi_teks)->toBeNull()
        ->and($ekstraksi->hash_sha256)->toBeNull();
    Queue::assertPushed(EkstrakTeksTautan::class);
});

test('job ekstraksi untuk jenis lainnya menandai tidak_berlaku tanpa unduhan', function () {
    $tautan = tautanEkstraksi('lainnya');
    Http::fake();

    jalankanEkstraksi($tautan);

    expect(ekstraksiDari($tautan)->status)->toBe(StatusEkstraksi::TidakBerlaku);
    Http::assertNothingSent();
});

test('batas unduh per host melepas ulang job 60 detik', function () {
    $tautan = tautanEkstraksi();
    config(['regulasi.unduh_per_menit_per_host' => 1]);
    Http::fake(['drive.google.com/*' => Http::response('', 404)]);

    jalankanEkstraksi($tautan)->assertNotReleased();
    jalankanEkstraksi($tautan)->assertReleased(60);
});

test('normalisasi teks membuang karakter kontrol dan merapatkan spasi', function () {
    expect(PengekstrakTeksPdf::normalisasi("  Tata\x00  Naskah\t\tDinas \r\n\n\n\n\x0cPasal 1  "))->toBe("Tata Naskah Dinas\n\nPasal 1");
});
