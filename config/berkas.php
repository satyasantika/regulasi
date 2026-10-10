<?php

/*
|--------------------------------------------------------------------------
| Kebijakan berkas = tautan, bukan unggahan (STANDAR-TEKNIS §1a, 02-ARSITEKTUR §4.2, BR-13)
|--------------------------------------------------------------------------
|
| Pola "*" dicocokkan tepat satu label domain (bukan str_contains): "jdih.*.go.id" menerima
| "jdih.kemenkeu.go.id" tetapi tidak "jdih.evil.com" maupun "jdih.a.b.go.id".
|
*/

return [

    'mode' => env('BERKAS_MODE', 'tautan'),

    // Host yang boleh diisikan pengguna.
    'domain_diizinkan' => [
        'drive.google.com', 'docs.google.com', '*.unsil.ac.id', 'unsil.ac.id',
        // khusus regulasi: sumber resmi pemerintah
        'peraturan.go.id', 'www.peraturan.go.id', 'jdih.*.go.id', 'peraturan.bpk.go.id',
        // usulan, aktifkan setelah dikonfirmasi admin: 'banpt.or.id', 'www.banpt.or.id', 'lamdik.or.id'
    ],

    // Hanya sebagai tujuan redirect saat mengunduh (PengunduhAman, F7.1), bukan input pengguna.
    'domain_unduhan_tambahan' => [
        'drive.usercontent.google.com', '*.googleusercontent.com',
    ],

    'pemendek_ditolak' => ['bit.ly', 's.id', 'tinyurl.com', 'shorturl.at', 'cutt.ly', 'goo.gl', 't.ly'],

    'panjang_maks' => 2048,

    'ukuran_maks_unduhan_mb' => 30,

    'maks_redirect' => 3,

    'timeout_cek_detik' => 10,

    'timeout_unduh_detik' => 30,

    // Relatif terhadap storage/app.
    'folder_sementara' => 'tmp/ekstraksi',

];
