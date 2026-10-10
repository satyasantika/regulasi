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
