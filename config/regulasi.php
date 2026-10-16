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

    // Subdirektori storage/app/tmp untuk berkas ekspor Excel antrean (F11.2), dihapus ≤ 24 jam.
    'direktori_ekspor' => 'ekspor',

    // Alamat di kaki portal publik (perlu verifikasi oleh FKIP sebelum produksi).
    'alamat' => env('REGULASI_ALAMAT', 'Jl. Siliwangi No. 24, Kota Tasikmalaya, Jawa Barat 46115'),

    // Reverse proxy tepercaya (STANDAR-TEKNIS §2.4): produksi = IP reverse proxy, lokal = *.
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    // Super-admin awal untuk PenggunaAwalSeeder (idempoten). Surel bawaan fkip@unsil.ac.id; kata sandi HANYA dari .env
    // (REGULASI_SUPERADMIN_PASSWORD, wajib di produksi). Akun baru ditandai wajib ganti sandi saat masuk pertama.
    'seed_superadmin' => [
        'email' => env('REGULASI_SUPERADMIN_EMAIL', 'fkip@unsil.ac.id'),
        'password' => env('REGULASI_SUPERADMIN_PASSWORD'),
        'nama' => env('REGULASI_SUPERADMIN_NAMA', 'Super Admin FKIP'),
    ],

    // Autentikasi dua langkah (MFA, aplikasi autentikator) di kedua panel. true (bawaan): MFA tersedia dan WAJIB bagi
    // super-admin & admin-regulasi. false: MFA dimatikan seluruhnya (tidak diminta saat masuk, tidak tampil di profil).
    // Rekomendasi: tetap true di produksi (docs/keputusan/0002-flag-mfa-aktif.md).
    'mfa_aktif' => (bool) env('MFA_AKTIF', true),

    // Seeder dokumen contoh (03-SKEMA §4.5): URL naskah lokal (di-.gitignore) yang mengisi tautan kosong, dan
    // demo lokal yang menerbitkan semua dokumen contoh melewati BR-05 (hanya bila APP_ENV=local).
    'seed_tautan_lokal' => database_path('seeders/data/tautan-contoh.local.php'),
    'seed_terbitkan_semua' => (bool) env('REGULASI_SEED_TERBITKAN_SEMUA', false),

    // Akun uji UAT (regulasi:siapkan-uat, hanya non-produksi): kata sandi bersama; kosong = acak & ditampilkan sekali.
    'uat' => [
        'password' => env('UAT_PASSWORD'),
    ],

    // Akun demo panduan (PanduanSeeder, hanya APP_ENV=local) & tangkapan layar Playwright (F12.6).
    'panduan' => [
        'password' => env('PANDUAN_PASSWORD'),
    ],

    // Registrasi panel akun hanya untuk alamat surel domain ini.
    'domain_registrasi' => 'unsil.ac.id',

    // Panjang token minimum cadangan FULLTEXT; samakan dengan innodb_ft_min_token_size MariaDB (bawaan 3; produksi
    // docker/mariadb/regulasi.cnf = 2 agar PP/UU/SE tercari).
    'ft_min_token' => (int) env('REGULASI_FT_MIN_TOKEN', 3),

    // isi_teks di indeks pencarian dipotong ke N kata pertama (02-ARSITEKTUR §5.1).
    'potong_isi_teks_kata' => 60000,

    // Ekstraksi teks PDF (F7.1, spatie/pdf-to-text + poppler-utils). pdfinfo dicari di folder yang sama.
    'pdftotext_bin' => env('PDFTOTEXT_BIN', '/usr/bin/pdftotext'),
    'pdfinfo_bin' => env('PDFINFO_BIN', '/usr/bin/pdfinfo'),

    // Teks hasil ekstraksi di bawah ambang (karakter per 10 halaman) → status tanpa_teks.
    'ambang_tanpa_teks' => 200,

    // Batas percobaan ulang ekstraksi berstatus gagal (03-SKEMA §3.10a) dan laju unduh per host tujuan
    // (02-ARSITEKTUR §3.4, `unduh-host:{host}`): job dilepas ulang bila batas terlampaui.
    'maks_percobaan_ekstraksi' => 5,
    'unduh_per_menit_per_host' => 30,

    // Kunci atomik alur publikasi `lock:publikasi:{id}` (02-ARSITEKTUR §3.3): masa kunci & lama menunggu (detik).
    'kunci_publikasi' => [
        'detik' => 10,
        'tunggu' => 5,
    ],

    // Kunci atomik status berlaku `lock:status-dokumen:{id}` (02-ARSITEKTUR §3.3), diambil berurutan id menaik.
    'kunci_status' => [
        'detik' => 15,
        'tunggu' => 5,
    ],

    // Retensi log buka tautan (BR-17).
    'retensi_log_buka_bulan' => 24,

    // Masa berlaku URL buka tautan bertanda tangan untuk klien API (dokumen internal, F9.2).
    'masa_tautan_api_menit' => 10,

    // Sinonim pencarian awal (02-ARSITEKTUR §5.1); dimuat dua arah ke tabel sinonim_pencarian oleh SinonimPencarianSeeder.
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
