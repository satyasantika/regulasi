<?php

/**
 * STANDAR-TEKNIS §2.4: dilarang URL absolut berawalan "/" di Blade/JS.
 * Semua URL lewat route(), url(), asset() atau path relatif.
 */
function berkasFrontend(): array
{
    $akar = dirname(__DIR__, 2);
    $berkas = [];

    foreach (['resources/views', 'resources/js'] as $folder) {
        if (! is_dir("$akar/$folder")) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$akar/$folder", FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $f) {
            if (in_array($f->getExtension(), ['php', 'js', 'ts', 'vue', 'html'], true)) {
                $berkas[] = $f->getPathname();
            }
        }
    }

    return $berkas;
}

test('tidak ada url absolut berawalan slash di blade dan js', function () {
    $pola = [
        '/\b(?:href|src|action)\s*=\s*["\']\/(?!\/)/i',
        '/\bfetch\(\s*["\'`]\/(?!\/)/',
        '/\baxios(?:\.\w+)?\(\s*["\'`]\/(?!\/)/',
    ];

    $pelanggaran = [];

    foreach (berkasFrontend() as $berkas) {
        $isi = file_get_contents($berkas);

        foreach ($pola as $p) {
            if (preg_match($p, $isi, $m)) {
                $pelanggaran[] = str_replace(dirname(__DIR__, 2).'/', '', $berkas).' : '.$m[0];
            }
        }
    }

    expect($pelanggaran)->toBe([]);
});
