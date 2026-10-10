<?php

use App\Enums\Peran;
use App\Models\Dokumen;
use App\Models\User;
use App\Support\Panduan;
use Database\Seeders\PanduanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Panduan HTML per peran di public/panduan (F12.6, STANDAR-TEKNIS §2.5).
 */
uses(RefreshDatabase::class);

function folderPanduan(): string
{
    return public_path('panduan');
}

test('setiap peran di seeder peran memiliki halaman panduan dan tercantum di index.html', function () {
    $index = (string) file_get_contents(folderPanduan().'/index.html');

    foreach (array_keys(PeranDanIzinSeeder::pemetaan()) as $peran) {
        expect(is_file(folderPanduan()."/{$peran}.html"))->toBeTrue("{$peran}.html tidak ada")
            ->and($index)->toContain("href=\"{$peran}.html\"");
    }
});

test('setiap gambar ada dan ber-alt deskriptif; tidak ada href/src absolut atau localhost', function () {
    foreach (glob(folderPanduan().'/*.html') as $berkas) {
        $html = (string) file_get_contents($berkas);
        preg_match_all('/<img\b[^>]*>/i', $html, $img);

        expect($img[0])->not->toBeEmpty(basename($berkas));

        foreach ($img[0] as $tag) {
            preg_match('/src="([^"]+)"/', $tag, $src);
            expect(is_file(folderPanduan().'/'.$src[1]))->toBeTrue(basename($berkas).": {$src[1]} tidak ada")
                ->and($tag)->toMatch('/alt="[^"]{15,}"/');
        }

        preg_match_all('/(?:href|src)="([^"]*)"/i', $html, $url);

        foreach ($url[1] as $u) {
            expect(str_starts_with($u, '/'))->toBeFalse(basename($berkas).": {$u}")
                ->and(str_starts_with($u, 'http://localhost'))->toBeFalse(basename($berkas).": {$u}");
        }
    }
});

test('halaman panduan mandiri: tanpa CDN atau skrip, responsif, siap cetak, berbahasa Indonesia, bertanggal & berversi', function () {
    foreach (glob(folderPanduan().'/*.html') as $berkas) {
        $html = (string) file_get_contents($berkas);

        expect($html)->toContain('<html lang="id">')
            ->toContain('@media print')
            ->toContain('name="viewport"')
            ->toContain('versi 1.0.0')
            ->toContain('Kontak')
            ->not->toMatch('/<script\b/i')
            ->not->toMatch('/<link\b[^>]*href="https?:/i');
    }
});

test('ukuran total public/panduan ≤ 15 MB', function () {
    $total = 0;

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(folderPanduan(), FilesystemIterator::SKIP_DOTS)) as $berkas) {
        $total += $berkas->getSize();
    }

    expect($total)->toBeLessThanOrEqual(15 * 1024 * 1024);
});

test('landing page, halaman masuk panel, dan menu pengguna menautkan panduan', function () {
    $this->get('/')->assertOk()->assertSee(asset('panduan/index.html'), false)->assertSee('Panduan');
    $this->get('/admin/login')->assertOk()->assertSee(asset('panduan/index.html'), false)->assertSee('Panduan Pengguna');
    $this->get('/akun/login')->assertOk()->assertSee(asset('panduan/index.html'), false);

    $kontributor = penggunaDenganPeran(Peran::Kontributor);
    $this->actingAs($kontributor)->get('/admin')->assertOk()->assertSee(asset('panduan/kontributor.html'), false);

    expect(Panduan::urlUntuk(penggunaDenganPeran(Peran::AdminRegulasi)))->toBe(asset('panduan/admin-regulasi.html'))
        ->and(Panduan::urlUntuk(null))->toBe(asset('panduan/index.html'));
});

test('PanduanSeeder menolak APP_ENV selain local', function (string $lingkungan) {
    $this->app->detectEnvironment(fn () => $lingkungan);
    config(['regulasi.panduan.password' => 'kata-sandi-panduan']);

    expect(fn () => app(PanduanSeeder::class)->run())->toThrow(RuntimeException::class, 'APP_ENV=local');
})->with(['production', 'staging', 'testing']);

test('PanduanSeeder lokal membuat akun demo per peran tanpa MFA wajib dan data rekaan berlabel', function () {
    $this->app->detectEnvironment(fn () => 'local');
    config(['regulasi.panduan.password' => 'kata-sandi-panduan', 'regulasi.seed_tautan_lokal' => '']);

    app(PanduanSeeder::class)->run();

    foreach (array_keys(PanduanSeeder::AKUN) as $peran) {
        $akun = User::query()->where('email', PanduanSeeder::surel($peran))->firstOrFail();

        expect($akun->hasRole($peran))->toBeTrue()
            ->and($akun->wajibMfa())->toBeFalse()
            ->and(str_ends_with($akun->email, '@contoh.test'))->toBeTrue();
    }

    expect(Dokumen::query()->where('judul', 'like', '%'.PanduanSeeder::AWALAN.'%')->count())->toBe(4);
});

test('pengecualian MFA hanya untuk akun demo @contoh.test di lingkungan local', function () {
    $demo = penggunaDenganPeran(Peran::AdminRegulasi, ['email' => 'panduan.admin-regulasi@contoh.test']);
    $biasa = penggunaDenganPeran(Peran::AdminRegulasi, ['email' => 'admin@unsil.ac.id']);

    expect($demo->wajibMfa())->toBeTrue('akun demo di luar local tetap wajib MFA');

    $this->app->detectEnvironment(fn () => 'local');

    expect($demo->wajibMfa())->toBeFalse()
        ->and($biasa->wajibMfa())->toBeTrue();
});
