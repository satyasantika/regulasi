<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi khusus Regulasi FKIP Unsil (02-ARSITEKTUR §5–§6)
    |--------------------------------------------------------------------------
    |
    | Sistem tidak menerima unggah berkas (STANDAR-TEKNIS §1a), sehingga tidak ada pengaturan ukuran/MIME
    | unggahan di sini. Pengaturan tautan berkas berada di config/berkas.php (F4.3).
    |
    */

    'versi' => env('APP_VERSION', 'dev'),

    // Reverse proxy tepercaya (STANDAR-TEKNIS §2.4): produksi = IP reverse proxy, lokal = *.
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    // Super-admin awal untuk PenggunaAwalSeeder (03-SKEMA §4.1). Wajib diisi di produksi.
    'seed_superadmin' => [
        'email' => env('SEED_SUPERADMIN_EMAIL'),
        'password' => env('SEED_SUPERADMIN_PASSWORD'),
    ],

    // Registrasi panel akun hanya untuk alamat surel domain ini.
    'domain_registrasi' => 'unsil.ac.id',

    // isi_teks di indeks pencarian dipotong ke N kata pertama (02-ARSITEKTUR §5.1).
    'potong_isi_teks_kata' => 60000,

    // Teks hasil ekstraksi di bawah ambang (karakter per 10 halaman) → status tanpa_teks.
    'ambang_tanpa_teks' => 200,

    // Kunci atomik alur publikasi `lock:publikasi:{id}` (02-ARSITEKTUR §3.3): masa kunci & lama menunggu (detik).
    'kunci_publikasi' => [
        'detik' => 10,
        'tunggu' => 5,
    ],

    // Retensi log buka tautan (BR-17).
    'retensi_log_buka_bulan' => 24,

    // Sinonim pencarian Meilisearch (02-ARSITEKTUR §5.1).
    'sinonim' => [
        'uu' => ['undang-undang'],
        'pp' => ['peraturan pemerintah'],
        'perpres' => ['peraturan presiden'],
        'permen' => ['peraturan menteri'],
        'permendikbud' => ['peraturan menteri pendidikan dan kebudayaan'],
        'permendikbudristek' => ['peraturan menteri pendidikan kebudayaan riset dan teknologi'],
        'permendiktisaintek' => ['peraturan menteri pendidikan tinggi sains dan teknologi'],
        'pmk' => ['peraturan menteri keuangan'],
        'bmn' => ['barang milik negara'],
        'tnd' => ['tata naskah dinas'],
        'iaps' => ['instrumen akreditasi program studi'],
        'spmi' => ['sistem penjaminan mutu internal'],
        'sk' => ['surat keputusan'],
        'se' => ['surat edaran'],
    ],

];
