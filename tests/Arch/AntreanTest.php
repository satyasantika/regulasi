<?php

it('hanya memakai antrean default dan berat', function () {
    $diizinkan = ['default', 'berat'];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())) as $berkas) {
        if ($berkas->getExtension() !== 'php') {
            continue;
        }

        $isi = file_get_contents($berkas->getPathname());
        preg_match_all('/(?:onQueue|getJobQueue)\([^)]*?[\'"]([^\'"]+)[\'"]/', $isi, $cocok);
        preg_match_all('/function getJobQueue\(\)[^{]*\{\s*return\s*[\'"]([^\'"]+)[\'"]/', $isi, $cocok2);

        foreach (array_merge($cocok[1], $cocok2[1]) as $nama) {
            expect($nama)->toBeIn($diizinkan, str_replace(app_path().'/', '', $berkas->getPathname())." memakai antrean '{$nama}'");
        }
    }
});

it('tidak memakai Redis di kode aplikasi', function () {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())) as $berkas) {
        if ($berkas->getExtension() !== 'php') {
            continue;
        }

        expect(file_get_contents($berkas->getPathname()))
            ->not->toContain('Redis::')
            ->not->toContain('Illuminate\\Support\\Facades\\Redis');
    }
});
