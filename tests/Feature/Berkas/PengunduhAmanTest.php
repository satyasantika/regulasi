<?php

use App\Enums\StatusCekTautan;
use App\Exceptions\Berkas\AksesTerbatas;
use App\Exceptions\Berkas\BerkasTerlaluBesar;
use App\Exceptions\Berkas\BukanPdf;
use App\Exceptions\Berkas\TautanTidakDapatDiunduh;
use App\Services\Berkas\PengunduhAman;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

const PDF_MINI = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

beforeEach(function () {
    // Folder sementara terpisah per uji (aman untuk --parallel), tetap di bawah storage/app/tmp.
    $this->folder = 'tmp/ekstraksi-uji-'.bin2hex(random_bytes(4));
    config(['berkas.folder_sementara' => $this->folder]);
    dnsPalsu();
});

afterEach(function () {
    File::deleteDirectory(storage_path('app/'.$this->folder));
});

function sisaBerkasSementara(string $folder): array
{
    return glob(storage_path("app/{$folder}/*")) ?: [];
}

function pengunduh(): PengunduhAman
{
    return app(PengunduhAman::class);
}

test('PDF publik diunduh ke berkas sementara 0600 lalu dapat dihapus', function () {
    Http::fake(['drive.google.com/*' => Http::response(PDF_MINI, 200, ['Content-Type' => 'application/pdf'])]);

    $berkas = pengunduh()->unduh('https://drive.google.com/uc?export=download&id=1AbCdEfGhIjKlMn');

    expect($berkas->path)->toStartWith(storage_path('app/'.$this->folder.'/'))
        ->and($berkas->path)->toEndWith('.pdf')
        ->and(file_get_contents($berkas->path))->toBe(PDF_MINI)
        ->and(fileperms($berkas->path) & 0777)->toBe(0600)
        ->and($berkas->ukuran)->toBe(strlen(PDF_MINI))
        ->and($berkas->hashSha256)->toBe(hash('sha256', PDF_MINI));

    $berkas->hapus();

    expect(sisaBerkasSementara($this->folder))->toBe([]);

    Http::assertSent(fn (Request $r): bool => $r->header('User-Agent') === [PengunduhAman::USER_AGENT]
        && ! $r->hasHeader('Cookie')
        && ! $r->hasHeader('Authorization'));
});

test('host di luar daftar putih ditolak tanpa permintaan jaringan', function (string $url) {
    Http::fake();

    expect(fn () => pengunduh()->unduh($url))->toThrow(TautanTidakDapatDiunduh::class);

    Http::assertNothingSent();
    expect(sisaBerkasSementara($this->folder))->toBe([]);
})->with([
    'domain asing' => 'https://evil.example.com/naskah.pdf',
    'http biasa' => 'http://drive.google.com/uc?id=1AbCdEfGhIjKlMn',
    'alamat IP' => 'https://142.250.4.100/naskah.pdf',
    'userinfo' => 'https://user@drive.google.com/uc?id=1AbCdEfGhIjKlMn',
    'port lain' => 'https://drive.google.com:8443/uc?id=1AbCdEfGhIjKlMn',
    'jdih dua label' => 'https://jdih.a.b.go.id/naskah.pdf',
]);

test('host yang diresolusi ke IP privat atau khusus ditolak', function (array $ip) {
    dnsPalsu(['drive.google.com' => $ip]);
    Http::fake();

    expect(fn () => pengunduh()->unduh('https://drive.google.com/uc?export=download&id=1AbCdEfGhIjKlMn'))
        ->toThrow(TautanTidakDapatDiunduh::class);

    Http::assertNothingSent();
    expect(sisaBerkasSementara($this->folder))->toBe([]);
})->with([
    'loopback' => [['127.0.0.1']],
    'privat 10/8' => [['10.0.0.5']],
    'salah satu privat' => [['142.250.4.100', '192.168.1.10']],
    'link-local' => [['169.254.169.254']],
    'CGNAT' => [['100.64.0.1']],
    'IPv6 loopback' => [['::1']],
    'IPv6 ULA' => [['fd00::1']],
    'IPv4-mapped' => [['::ffff:127.0.0.1']],
    'tidak teresolusi' => [[]],
]);

test('ipTerlarang membedakan IP publik dan khusus', function (string $ip, bool $terlarang) {
    expect(PengunduhAman::ipTerlarang($ip))->toBe($terlarang);
})->with([
    ['142.250.4.100', false],
    ['2001:4860:4860::8888', false],
    ['0.0.0.0', true],
    ['172.16.5.4', true],
    ['172.32.0.1', false],
    ['fe80::1', true],
    ['::ffff:8.8.8.8', true],
    ['bukan-ip', true],
]);

test('sambungan dipaku ke IP tervalidasi tanpa redirect otomatis dan tanpa cookie', function () {
    $opsi = pengunduh()->opsiSambungan('drive.google.com', '142.250.4.100');

    // Tanpa opsi stream: Guzzle akan memakai StreamHandler yang menolak opsi cURL (pemakuan IP tak berlaku).
    expect($opsi)->not->toHaveKey('stream')
        ->and($opsi['allow_redirects'])->toBeFalse()
        ->and($opsi['cookies'])->toBeFalse()
        ->and($opsi['curl'][CURLOPT_RESOLVE])->toBe(['drive.google.com:443:142.250.4.100'])
        ->and(pengunduh()->opsiSambungan('drive.google.com', '2001:4860::1')['curl'][CURLOPT_RESOLVE])->toBe(['drive.google.com:443:[2001:4860::1]']);
});

test('redirect ke domain unduhan tambahan diikuti dan divalidasi ulang', function () {
    Http::fake([
        'drive.google.com/*' => Http::response('', 303, ['Location' => 'https://drive.usercontent.google.com/download?id=1AbCdEfGhIjKlMn&export=download']),
        'drive.usercontent.google.com/*' => Http::response(PDF_MINI, 200, ['Content-Type' => 'application/pdf']),
    ]);

    $berkas = pengunduh()->unduh('https://drive.google.com/uc?export=download&id=1AbCdEfGhIjKlMn');

    expect($berkas->urlAkhir)->toStartWith('https://drive.usercontent.google.com/download');
    $berkas->hapus();
});

test('redirect ke host asing ditolak', function () {
    Http::fake([
        'drive.google.com/*' => Http::response('', 302, ['Location' => 'https://evil.example.com/naskah.pdf']),
        '*' => Http::response(PDF_MINI, 200, ['Content-Type' => 'application/pdf']),
    ]);

    expect(fn () => pengunduh()->unduh('https://drive.google.com/uc?export=download&id=1AbCdEfGhIjKlMn'))
        ->toThrow(TautanTidakDapatDiunduh::class);

    Http::assertSentCount(1);
    expect(sisaBerkasSementara($this->folder))->toBe([]);
});

test('redirect ke host berizin yang mengarah ke IP privat ditolak (validasi ulang DNS)', function () {
    dnsPalsu(['drive.google.com' => ['142.250.4.100'], 'drive.usercontent.google.com' => ['10.0.0.5']]);
    Http::fake([
        'drive.google.com/*' => Http::response('', 302, ['Location' => 'https://drive.usercontent.google.com/download?id=x']),
        '*' => Http::response(PDF_MINI, 200, ['Content-Type' => 'application/pdf']),
    ]);

    expect(fn () => pengunduh()->unduh('https://drive.google.com/uc?export=download&id=1AbCdEfGhIjKlMn'))
        ->toThrow(TautanTidakDapatDiunduh::class);

    Http::assertSentCount(1);
});

test('tiga redirect diikuti, redirect ke-4 ditolak', function () {
    Http::fake([
        'lppm.unsil.ac.id/1' => Http::response('', 302, ['Location' => '/2']),
        'lppm.unsil.ac.id/2' => Http::response('', 302, ['Location' => 'https://lppm.unsil.ac.id/3']),
        'lppm.unsil.ac.id/3' => Http::response('', 301, ['Location' => '/4']),
        'lppm.unsil.ac.id/4' => Http::response('', 302, ['Location' => '/5']),
        'lppm.unsil.ac.id/5' => Http::response(PDF_MINI, 200, ['Content-Type' => 'application/pdf']),
    ]);

    $berkas = pengunduh()->unduh('https://lppm.unsil.ac.id/2');
    expect($berkas->urlAkhir)->toBe('https://lppm.unsil.ac.id/5');
    $berkas->hapus();

    expect(fn () => pengunduh()->unduh('https://lppm.unsil.ac.id/1'))->toThrow(TautanTidakDapatDiunduh::class, 'Pengalihan melebihi 3 kali.');
    expect(sisaBerkasSementara($this->folder))->toBe([]);
});

test('respons HTML Drive (halaman login/konfirmasi) ditolak sebagai tidak dapat diunduh', function () {
    Http::fake(['drive.google.com/*' => Http::response('<html><title>Google Drive - Virus scan warning</title></html>', 200, ['Content-Type' => 'text/html; charset=utf-8'])]);

    expect(fn () => pengunduh()->unduh('https://drive.google.com/uc?export=download&id=1AbCdEfGhIjKlMn'))
        ->toThrow(TautanTidakDapatDiunduh::class);

    expect(sisaBerkasSementara($this->folder))->toBe([]);
});

test('pengalihan ke halaman masuk Google berarti akses terbatas', function () {
    Http::fake(['drive.google.com/*' => Http::response('', 302, ['Location' => 'https://accounts.google.com/ServiceLogin?continue=x'])]);

    expect(fn () => pengunduh()->unduh('https://drive.google.com/uc?export=download&id=1AbCdEfGhIjKlMn'))
        ->toThrow(AksesTerbatas::class);

    Http::assertSentCount(1);
});

test('Content-Length 40 MB ditolak sebelum isi dibaca', function () {
    Http::fake(['drive.google.com/*' => Http::response(PDF_MINI, 200, ['Content-Type' => 'application/pdf', 'Content-Length' => (string) (40 * 1024 * 1024)])]);

    expect(fn () => pengunduh()->unduh('https://drive.google.com/uc?export=download&id=1AbCdEfGhIjKlMn'))
        ->toThrow(BerkasTerlaluBesar::class);

    expect(sisaBerkasSementara($this->folder))->toBe([]);
});

test('isi melebihi batas saat streaming dihentikan dan berkas sementara dihapus', function () {
    config(['berkas.ukuran_maks_unduhan_mb' => 1]);
    Http::fake(['drive.google.com/*' => Http::response(PDF_MINI.str_repeat('x', 1024 * 1024 + 10), 200, ['Content-Type' => 'application/pdf'])]);

    expect(fn () => pengunduh()->unduh('https://drive.google.com/uc?export=download&id=1AbCdEfGhIjKlMn'))
        ->toThrow(BerkasTerlaluBesar::class);

    expect(sisaBerkasSementara($this->folder))->toBe([]);
});

test('magic bytes bukan %PDF- ditolak sebagai bukan PDF', function () {
    Http::fake(['drive.google.com/*' => Http::response("PK\x03\x04 isi docx", 200, ['Content-Type' => 'application/octet-stream'])]);

    expect(fn () => pengunduh()->unduh('https://drive.google.com/uc?export=download&id=1AbCdEfGhIjKlMn'))
        ->toThrow(BukanPdf::class);

    expect(sisaBerkasSementara($this->folder))->toBe([]);
});

test('401/403/404 menjadi tidak dapat diunduh', function (int $status) {
    Http::fake(['drive.google.com/*' => Http::response('', $status)]);

    expect(fn () => pengunduh()->unduh('https://drive.google.com/uc?export=download&id=1AbCdEfGhIjKlMn'))
        ->toThrow(TautanTidakDapatDiunduh::class);
})->with([401, 403, 404]);

test('periksa memakai HEAD lalu GET Range 0-1023 tanpa cookie', function () {
    Http::fake(['drive.google.com/*' => Http::sequence()->push('', 405)->push('isi', 206)]);

    expect(pengunduh()->periksa('https://drive.google.com/file/d/1AbCdEfGhIjKlMn/view'))->toBe(StatusCekTautan::DapatDiakses);

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $r): bool => $r->method() === 'HEAD' && ! $r->hasHeader('Cookie'));
    Http::assertSent(fn (Request $r): bool => $r->method() === 'GET' && $r->header('Range') === ['bytes=0-1023']);
});

test('periksa: 404, host privat → tidak dapat diakses; login Google → dapat diakses', function () {
    Http::fake([
        'drive.google.com/file/d/1Hilang*' => Http::response('', 404),
        'drive.google.com/file/d/1Terbatas*' => Http::response('', 302, ['Location' => 'https://accounts.google.com/ServiceLogin']),
    ]);

    expect(pengunduh()->periksa('https://drive.google.com/file/d/1HilangAbCdEf/view'))->toBe(StatusCekTautan::TidakDapatDiakses)
        ->and(pengunduh()->periksa('https://drive.google.com/file/d/1TerbatasAbCd/view'))->toBe(StatusCekTautan::DapatDiakses);

    dnsPalsu(['*' => ['127.0.0.1']]);
    expect(pengunduh()->periksa('https://drive.google.com/file/d/1TerbatasAbCd/view'))->toBe(StatusCekTautan::TidakDapatDiakses);
});
