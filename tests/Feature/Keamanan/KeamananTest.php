<?php

use App\Actions\Relasi\TambahRelasiDokumen;
use App\Enums\JenisRelasi;
use App\Enums\JenisTautan;
use App\Enums\PenyediaTautan;
use App\Enums\Peran;
use App\Enums\StatusPublikasi;
use App\Exceptions\Berkas\TautanTidakDapatDiunduh;
use App\Http\Middleware\HeaderKeamanan;
use App\Models\Dokumen;
use App\Models\KategoriSistem;
use App\Models\Tag;
use App\Models\TautanBerkas;
use App\Rules\TautanBerkasValid;
use App\Services\Berkas\PengunduhAman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

/**
 * Pengerasan keamanan F12.1 (05-UJI §3): header & CSP, cookie sesi, IDOR, tautan berbahaya/SSRF, XSS.
 */
uses(RefreshDatabase::class);

const XSS_SKRIP = '<script>alert("xss")</script>';

beforeEach(function () {
    RateLimiter::clear('buka');
});

function tautanKeamanan(Dokumen $dokumen, string $id = '1KeamananUjiTautanBerkas00001'): TautanBerkas
{
    return TautanBerkas::create([
        'pemilik_type' => 'dokumen',
        'pemilik_id' => $dokumen->id,
        'jenis' => JenisTautan::NaskahUtama,
        'url' => "https://drive.google.com/file/d/{$id}/view",
        'penyedia' => PenyediaTautan::GoogleDrive,
        'drive_file_id' => $id,
    ]);
}

test('header keamanan dan CSP terpasang di portal, panel, horizon, dan API', function (string $path, bool $superAdmin) {
    if ($superAdmin) {
        $this->actingAs(penggunaDenganPeran(Peran::SuperAdmin));
    }

    $respons = $this->get($path);
    $csp = (string) $respons->headers->get('Content-Security-Policy');
    preg_match('/frame-src ([^;]+)/', $csp, $frame);

    $respons->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', HeaderKeamanan::PERMISSIONS_POLICY)
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeaderMissing('X-Powered-By')
        ->assertHeaderMissing('Strict-Transport-Security');

    expect($frame[1] ?? null)->toBe('https://drive.google.com https://docs.google.com')
        ->and($csp)->toContain("frame-ancestors 'self'")
        ->toContain("object-src 'none'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self'")
        ->not->toContain('frame-src *');
})->with([
    'beranda' => ['/', false],
    'masuk admin' => ['/admin/login', false],
    'masuk akun' => ['/akun/login', false],
    'admin' => ['/admin', true],
    'horizon' => ['/horizon', true],
    'kesehatan api' => ['/api/health', false],
]);

test('HSTS hanya dikirim di produksi', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $this->get('/api/health')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

test('buka tautan tetap memakai Referrer-Policy no-referrer', function () {
    $tautan = tautanKeamanan(Dokumen::factory()->terbit()->create());

    $this->get("/d/{$tautan->id}/buka")->assertRedirect($tautan->url)->assertHeader('Referrer-Policy', 'no-referrer');
});

test('cookie sesi secure secara bawaan di produksi dan httponly', function () {
    $konfigurasi = file_get_contents(config_path('session.php'));

    expect($konfigurasi)->toContain("'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')")
        ->and(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax');

    config(['session.secure' => true]);
    URL::forceScheme('https');

    $cookie = collect($this->get('/')->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === config('session.cookie'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->isHttpOnly())->toBeTrue();

    URL::forceScheme(null);
});

test('IDOR: id numerik tidak diterima di rute publik, tautan, dan API', function (string $path) {
    expect($this->get($path)->getStatusCode())->toBeIn([401, 404]);
})->with([
    '/dokumen/1',
    '/dokumen/1/contoh',
    '/d/1/buka',
    '/d/1/buka-api',
    '/laporan-evaluasi/1/pdf',
    '/api/v1/dokumen/1',
]);

test('IDOR: UUID dokumen draf, ditolak, menunggu, diarsipkan, dan terhapus tidak dapat dibuka dari portal', function (StatusPublikasi $status, bool $hapus) {
    $dokumen = Dokumen::factory()->berstatus($status)->create();
    $tautan = tautanKeamanan($dokumen);

    if ($hapus) {
        $dokumen->delete();
    }

    $this->get("/dokumen/{$dokumen->id}/{$dokumen->slug}")->assertNotFound();
    $this->get("/d/{$tautan->id}/buka")->assertNotFound();
    $this->actingAs(penggunaDenganPeran(Peran::Pengguna))->get("/d/{$tautan->id}/buka")->assertNotFound();
})->with([
    'draf' => [StatusPublikasi::Draf, false],
    'ditolak' => [StatusPublikasi::Ditolak, false],
    'menunggu' => [StatusPublikasi::MenungguVerifikasi, false],
    'diarsipkan' => [StatusPublikasi::Diarsipkan, false],
    'terbit lalu dihapus' => [StatusPublikasi::Terbit, true],
]);

test('IDOR: kontributor tidak dapat membuka tautan draf kategori lain dengan menebak UUID tautan', function () {
    $aset = KategoriSistem::factory()->create();
    $lain = KategoriSistem::factory()->create();
    $kontributor = penggunaDenganPeran(Peran::Kontributor);
    $kontributor->kategoriSistem()->attach($aset);
    $dokumen = Dokumen::factory()->create();
    $dokumen->kategoriSistem()->attach($lain);

    $this->actingAs($kontributor)->get('/d/'.tautanKeamanan($dokumen)->id.'/buka')->assertNotFound();
});

test('buka tautan mengabaikan parameter tujuan (bebas open redirect)', function (string $parameter) {
    $tautan = tautanKeamanan(Dokumen::factory()->terbit()->create());

    $lokasi = $this->get("/d/{$tautan->id}/buka?{$parameter}=https://evil.example/x")->assertStatus(302)->headers->get('Location');

    expect($lokasi)->toBe($tautan->url);
})->with(['to', 'url', 'redirect', 'next', 'tujuan']);

test('buka-api tanpa tanda tangan sah ditolak', function () {
    $tautan = tautanKeamanan(Dokumen::factory()->terbit()->internal()->create());

    $this->get("/d/{$tautan->id}/buka-api?klien=apa-saja")->assertForbidden();
});

test('tautan berbahaya ditolak validasi', function (string $url) {
    expect(TautanBerkasValid::sah($url, JenisTautan::NaskahUtama))->toBeFalse();
})->with([
    'javascript' => 'javascript:alert(1)',
    'data' => 'data:application/pdf;base64,JVBERi0=',
    'http polos' => 'http://drive.google.com/file/d/1AbcDefGhiJklMnoPqr/view',
    'ip privat' => 'https://10.0.0.5/x.pdf',
    'loopback' => 'https://127.0.0.1/x.pdf',
    'localhost' => 'https://localhost/x.pdf',
    'userinfo' => 'https://drive.google.com@evil.example/file/d/1Abc/view',
    'subdomain tiruan' => 'https://drive.google.com.evil.example/file/d/1AbcDefGhiJklMnoPqr/view',
    'domain asing' => 'https://evil.example/peraturan.pdf',
    'pemendek bitly' => 'https://bit.ly/abc123',
    'pemendek s.id' => 'https://s.id/abc123',
    'folder drive' => 'https://drive.google.com/drive/folders/1AbcDefGhiJklMnoPqr',
]);

test('PengunduhAman menolak host yang resolve ke IP privat, loopback, dan metadata awan', function (string $ip) {
    dnsPalsu(['*' => [$ip]]);

    expect(fn () => app(PengunduhAman::class)->validasi('https://drive.google.com/uc?id=1AbcDefGhiJklMnoPqr&export=download'))
        ->toThrow(TautanTidakDapatDiunduh::class);
})->with(['10.0.0.5', '10.255.1.1', '127.0.0.1', '192.168.1.10', '172.16.0.1', '169.254.169.254', '::1', 'fd00::1']);

test('PengunduhAman menolak http, port selain 443, userinfo, dan alamat IP langsung', function (string $url) {
    dnsPalsu();

    expect(fn () => app(PengunduhAman::class)->validasi($url))->toThrow(TautanTidakDapatDiunduh::class);
})->with([
    'http://drive.google.com/uc?id=1Abc',
    'https://drive.google.com:8443/uc?id=1Abc',
    'https://user:pass@drive.google.com/uc?id=1Abc',
    'https://142.250.4.100/uc?id=1Abc',
]);

test('XSS: judul, abstrak, keterangan relasi, dan nama tag ter-escape di portal dan panel', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $tag = Tag::factory()->create(['nama' => 'Tag '.XSS_SKRIP]);
    $target = Dokumen::factory()->terbit()->create(['judul' => 'Target '.XSS_SKRIP, 'tanggal_penetapan' => '2020-01-01']);
    $sumber = Dokumen::factory()->terbit()->create([
        'judul' => 'Sumber '.XSS_SKRIP,
        'abstrak' => 'Abstrak '.XSS_SKRIP,
        'tanggal_penetapan' => '2024-01-01',
    ]);
    $sumber->tag()->attach($tag);
    app(TambahRelasiDokumen::class)->handle($sumber, $target, JenisRelasi::Mengubah, 'Keterangan '.XSS_SKRIP, $admin);

    $halaman = [
        'portal sumber' => $this->get("/dokumen/{$sumber->id}/{$sumber->slug}")->assertOk()->getContent(),
        'portal target' => $this->get("/dokumen/{$target->id}/{$target->slug}")->assertOk()->getContent(),
        'portal daftar' => $this->get('/dokumen')->assertOk()->getContent(),
        'admin lihat' => $this->actingAs($admin)->get("/admin/dokumen/{$sumber->id}")->assertOk()->getContent(),
        'admin daftar' => $this->actingAs($admin)->get('/admin/dokumen')->assertOk()->getContent(),
        'admin tag' => $this->actingAs($admin)->get('/admin/tag')->assertOk()->getContent(),
    ];

    foreach ($halaman as $tempat => $html) {
        expect(str_contains((string) $html, XSS_SKRIP))->toBeFalse("payload mentah tampil di {$tempat}");
    }

    expect($halaman['portal sumber'])->toContain('&lt;script&gt;');
});

test('tidak ada keluaran Blade tanpa escape ({!! !!}) di resources/views (KM-07)', function () {
    $temuan = collect(File::allFiles(resource_path('views')))
        ->filter(fn (SplFileInfo $berkas): bool => str_contains((string) file_get_contents($berkas->getPathname()), '{!!'))
        ->map(fn (SplFileInfo $berkas): string => $berkas->getRelativePathname())
        ->values()
        ->all();

    expect($temuan)->toBe([]);
});
