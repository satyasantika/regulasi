<?php

use Illuminate\Database\Eloquent\Concerns\HasUuids;

arch('semua model memakai HasUuids')
    ->expect('App\Models')
    ->classes()
    ->toUseTrait(HasUuids::class);

test('migrasi aplikasi tidak memakai kunci auto-increment', function () {
    // Tabel infrastruktur kerangka kerja yang diizinkan (STANDAR-TEKNIS §4a butir 6).
    $infrastruktur = ['create_cache_table', 'create_jobs_table'];

    $dilarang = [
        '/->id\(/',
        '/foreignId\(/',
        '/(?<!uuid)(?<!Uuid)(?<![A-Za-z])morphs\(/',
        '/bigIncrements\(/',
        '/(?<![A-Za-z])increments\(/',
    ];

    $pelanggaran = [];

    foreach (glob(dirname(__DIR__, 2).'/database/migrations/*.php') as $berkas) {
        if (collect($infrastruktur)->contains(fn ($nama) => str_contains($berkas, $nama))) {
            continue;
        }

        $isi = file_get_contents($berkas);

        foreach ($dilarang as $pola) {
            if (preg_match($pola, $isi)) {
                $pelanggaran[] = basename($berkas).' memuat '.$pola;
            }
        }
    }

    expect($pelanggaran)->toBe([]);
});

test('migrasi tidak memakai char/binary eksplisit untuk kolom id, FK, atau morph', function () {
    // kolom id/FK/morph wajib lewat helper Laravel (uuid, foreignUuid, uuidMorphs) agar tipenya sama
    // (STANDAR-TEKNIS §4a butir 1: MariaDB menolak FK antar tipe berbeda).
    $larangan = [
        '/->char\(\s*[\'"][a-z_]*id[a-z_]*[\'"]\s*,\s*36/' => "char('..._id', 36)",
        '/->binary\(\s*[\'"][a-z_]*id[a-z_]*[\'"]\s*,\s*16/' => "binary('..._id', 16)",
    ];

    foreach (glob(dirname(__DIR__, 2).'/database/migrations/*.php') as $berkas) {
        $isi = file_get_contents($berkas);

        foreach ($larangan as $pola => $nama) {
            expect(preg_match($pola, $isi))->toBe(0, basename($berkas)." memuat {$nama}");
        }
    }
});
