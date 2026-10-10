<?php

use Filament\Forms\Components\FileUpload;

/**
 * STANDAR-TEKNIS §1a, BR-13: berkas = tautan, bukan unggahan. Satu-satunya FileUpload yang boleh adalah
 * impor Excel/CSV di App\Filament\Imports.
 */
arch('tidak ada FileUpload di Filament selain impor')
    ->expect('App\Filament')
    ->not->toUse(FileUpload::class)
    ->ignoring('App\Filament\Imports');

/**
 * @return list<string>
 */
function berkasAplikasi(): array
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app', FilesystemIterator::SKIP_DOTS));
    $berkas = [];

    foreach ($iterator as $f) {
        if ($f->getExtension() === 'php') {
            $berkas[] = $f->getPathname();
        }
    }

    return $berkas;
}

test('tidak ada penulisan berkas dokumen ke disk penyimpanan', function () {
    $pola = [
        '/Storage::disk\([^)]*\)\s*->\s*(put|putFile|putFileAs|writeStream|store)\b/',
        '/->\s*(storeAs|storePublicly|storePubliclyAs)\s*\(/',
        '/FileUpload::make/',
        '/SpatieMediaLibraryFileUpload|InteractsWithMedia/',
    ];

    $pelanggaran = [];

    foreach (berkasAplikasi() as $berkas) {
        if (str_contains($berkas, '/app/Filament/Imports/')) {
            continue;
        }

        $isi = (string) file_get_contents($berkas);

        foreach ($pola as $p) {
            if (preg_match($p, $isi) === 1) {
                $pelanggaran[] = basename($berkas).' memuat '.$p;
            }
        }
    }

    expect($pelanggaran)->toBe([]);
});

test('tidak ada migrasi kolom path atau disk berkas', function () {
    $pelanggaran = [];

    foreach (glob(dirname(__DIR__, 2).'/database/migrations/*.php') as $berkas) {
        if (preg_match("/->string\('(path|disk|nama_berkas|file_path)'/", (string) file_get_contents($berkas)) === 1) {
            $pelanggaran[] = basename($berkas);
        }
    }

    expect($pelanggaran)->toBe([]);
});
