<?php

/**
 * Data dokumen contoh (03-SKEMA §4.5). Hanya fakta yang tercantum di tabel skema; tanggal/judul yang tidak pasti
 * dibiarkan NULL/umum dan ditandai "perlu verifikasi" di `keterangan` (catatan internal), bukan di judul.
 *
 * Kolom entri: kunci, jenis (kode), nomor, tahun, judul, badan (singkatan atau nama), kategori (kode[]), tag (nama[]),
 * tanggal_penetapan, tanggal_berlaku, sumber, keterangan (catatan internal), tautan (URL naskah utama — kosong sampai
 * diisi Admin Regulasi, atau lewat tautan-contoh.local.php), tautan_sumber (halaman sumber resmi, opsional),
 * terbit (diterbitkan bila lolos BR-05), tanpa_berkas (dokumen rujukan).
 */
$lengkapi = 'Lengkapi tanggal penetapan/pengundangan dan abstrak dari naskah (perlu verifikasi).';
$judulKosong = 'Judul belum diisi (lengkapi dari naskah)';

$entri = fn (array $data): array => array_merge([
    'nomor' => null,
    'tag' => [],
    'tanggal_penetapan' => null,
    'tanggal_berlaku' => null,
    'sumber' => null,
    'keterangan' => $lengkapi,
    'tautan' => '',
    'tautan_sumber' => '',
    'terbit' => false,
    'tanpa_berkas' => false,
], $data);

$rujukan = fn (array $data): array => $entri(array_merge([
    'keterangan' => 'Dokumen rujukan (metadata saja); naskah belum tersedia di repositori.',
    'terbit' => true,
    'tanpa_berkas' => true,
], $data));

$iapsk = fn (string $kunci, string $jenis, string $judul, array $tag = []): array => $entri([
    'kunci' => $kunci,
    'jenis' => $jenis,
    'tahun' => 2025,
    'judul' => $judul,
    'badan' => 'LAMDIK',
    'kategori' => ['akreditasi'],
    'tag' => ['IAPSK 3.0', 'LAMDIK', ...$tag],
    'tanggal_penetapan' => '2025-12-02',
    'keterangan' => 'Abstrak: lengkapi dari naskah.',
    'terbit' => true,
]);

return [
    'dokumen' => [
        $entri([
            'kunci' => 'banpt-13-2025', 'jenis' => 'PERLEMBAGA', 'nomor' => '13', 'tahun' => 2025,
            'judul' => 'Instrumen Akreditasi Program Studi (IAPS) 5.0',
            'badan' => 'BAN-PT', 'kategori' => ['akreditasi'], 'tag' => ['Akreditasi Program Studi'],
            'tanggal_penetapan' => '2025-06-16', 'tanggal_berlaku' => '2025-08-18',
            'keterangan' => 'Judul persis sesuaikan naskah (perlu verifikasi).',
            'terbit' => true,
        ]),
        $entri([
            'kunci' => 'permendikbudristek-53-2023', 'jenis' => 'PERMEN', 'nomor' => '53', 'tahun' => 2023,
            'judul' => 'Penjaminan Mutu Pendidikan Tinggi',
            'badan' => 'Kemendikbudristek', 'kategori' => ['akreditasi', 'umum'], 'tag' => ['Penjaminan Mutu'],
        ]),
        $entri([
            'kunci' => 'pmk-181-2016', 'jenis' => 'PERMEN', 'nomor' => '181/PMK.06/2016', 'tahun' => 2016,
            'judul' => 'Penatausahaan Barang Milik Negara',
            'badan' => 'Kemenkeu', 'kategori' => ['aset'], 'tag' => ['Barang Milik Negara'],
        ]),
        $entri([
            'kunci' => 'pp-28-2020', 'jenis' => 'PP', 'nomor' => '28', 'tahun' => 2020,
            'judul' => 'Perubahan atas Peraturan Pemerintah Nomor 27 Tahun 2014 tentang Pengelolaan Barang Milik Negara/Daerah',
            'badan' => 'Pemerintah Republik Indonesia', 'kategori' => ['aset'], 'tag' => ['Barang Milik Negara'],
        ]),
        $entri([
            'kunci' => 'permendikbud-3-2021', 'jenis' => 'PERMEN', 'nomor' => '3', 'tahun' => 2021,
            'judul' => 'Tata Naskah Dinas di Lingkungan Kementerian Pendidikan dan Kebudayaan',
            'badan' => 'Kemendikbud', 'kategori' => ['persuratan'], 'tag' => ['Tata Naskah Dinas'],
            'keterangan' => 'Judul persis: perlu verifikasi. '.$lengkapi,
        ]),
        $entri([
            'kunci' => 'permendiktisaintek-42-2025', 'jenis' => 'PERMEN', 'nomor' => '42', 'tahun' => 2025,
            'judul' => 'Tata Naskah Dinas',
            'badan' => 'Kemendiktisaintek', 'kategori' => ['persuratan'], 'tag' => ['Tata Naskah Dinas'],
            'tanggal_penetapan' => '2025-10-15',
            'keterangan' => 'Judul persis (di lingkungan Kemendiktisaintek): perlu verifikasi.',
            'terbit' => true,
        ]),
        $entri([
            'kunci' => 'permendikbud-14-2014', 'jenis' => 'PERMEN', 'nomor' => '14', 'tahun' => 2014,
            'judul' => 'Kerja Sama Perguruan Tinggi',
            'badan' => 'Kemendikbud', 'kategori' => ['kerjasama'], 'tag' => ['Kerja Sama'],
        ]),
        $entri([
            'kunci' => 'uu-17-2003', 'jenis' => 'UU', 'nomor' => '17', 'tahun' => 2003,
            'judul' => 'Keuangan Negara',
            'badan' => 'Pemerintah Republik Indonesia', 'kategori' => ['keuangan'], 'tag' => ['Keuangan Negara'],
        ]),
        $entri([
            'kunci' => 'pp-23-2005', 'jenis' => 'PP', 'nomor' => '23', 'tahun' => 2005,
            'judul' => 'Pengelolaan Keuangan Badan Layanan Umum',
            'badan' => 'Pemerintah Republik Indonesia', 'kategori' => ['keuangan'], 'tag' => ['Badan Layanan Umum'],
        ]),
        $entri([
            'kunci' => 'uu-14-2005', 'jenis' => 'UU', 'nomor' => '14', 'tahun' => 2005,
            'judul' => 'Guru dan Dosen',
            'badan' => 'Pemerintah Republik Indonesia', 'kategori' => ['sdm'], 'tag' => ['Guru', 'Dosen'],
        ]),
        $entri([
            'kunci' => 'pp-37-2009', 'jenis' => 'PP', 'nomor' => '37', 'tahun' => 2009,
            'judul' => 'Dosen',
            'badan' => 'Pemerintah Republik Indonesia', 'kategori' => ['sdm'], 'tag' => ['Dosen'],
        ]),
        $entri([
            'kunci' => 'permendikbud-109-2013', 'jenis' => 'PERMEN', 'nomor' => '109', 'tahun' => 2013,
            'judul' => 'Penyelenggaraan Pendidikan Jarak Jauh pada Pendidikan Tinggi',
            'badan' => 'Kemendikbud', 'kategori' => ['lms'], 'tag' => ['Pendidikan Jarak Jauh'],
            'keterangan' => 'Judul: perlu verifikasi. '.$lengkapi,
        ]),
        $entri([
            'kunci' => 'permendikbudristek-56-2022', 'jenis' => 'PERMEN', 'nomor' => '56', 'tahun' => 2022,
            'judul' => $judulKosong,
            'badan' => 'Kemendikbudristek', 'kategori' => ['microteaching'],
            'keterangan' => 'Judul: perlu verifikasi dari naskah. '.$lengkapi,
        ]),
        $entri([
            'kunci' => 'permendikbudristek-46-2023', 'jenis' => 'PERMEN', 'nomor' => '46', 'tahun' => 2023,
            'judul' => 'Pencegahan dan Penanganan Kekerasan di Lingkungan Satuan Pendidikan',
            'badan' => 'Kemendikbudristek', 'kategori' => ['microteaching', 'umum'], 'tag' => ['Kekerasan di Satuan Pendidikan'],
            'keterangan' => 'Judul: perlu verifikasi. '.$lengkapi,
        ]),
        $entri([
            'kunci' => 'perpres-33-2012', 'jenis' => 'PERPRES', 'nomor' => '33', 'tahun' => 2012,
            'judul' => 'Jaringan Dokumentasi dan Informasi Hukum Nasional',
            'badan' => 'Presiden Republik Indonesia', 'kategori' => ['umum'], 'tag' => ['Dokumentasi Hukum'],
        ]),
        $entri([
            'kunci' => 'permenkumham-8-2019', 'jenis' => 'PERMEN', 'nomor' => '8', 'tahun' => 2019,
            'judul' => 'Standar Pengelolaan Dokumen dan Informasi Hukum',
            'badan' => 'Kemenkumham', 'kategori' => ['umum'], 'tag' => ['Dokumentasi Hukum'],
        ]),
        $entri([
            'kunci' => 'lamdik-5-2025', 'jenis' => 'PERLEMBAGA', 'nomor' => '5', 'tahun' => 2025,
            'judul' => 'Instrumen Akreditasi Program Studi Kependidikan pada Program Studi yang Tercakup dalam Lembaga Akreditasi Mandiri Kependidikan (IAPSK 3.0)',
            'badan' => 'LAMDIK', 'kategori' => ['akreditasi'], 'tag' => ['Akreditasi Program Studi', 'LAMDIK', 'IAPSK 3.0'],
            'tanggal_penetapan' => '2025-12-02', 'tanggal_berlaku' => '2025-12-02',
            'keterangan' => 'Berlaku sejak ditetapkan. Abstrak: lengkapi dari naskah.',
            'terbit' => true,
        ]),
        $entri([
            'kunci' => 'permendiktisaintek-39-2025', 'jenis' => 'PERMEN', 'nomor' => '39', 'tahun' => 2025,
            'judul' => 'Penjaminan Mutu Pendidikan Tinggi',
            'badan' => 'Kemendiktisaintek', 'kategori' => ['akreditasi', 'lms', 'umum'], 'tag' => ['Penjaminan Mutu'],
            'sumber' => 'BN 2025 No. 661',
            'keterangan' => $lengkapi.' Usulan relasi "mencabut Permendikbudristek 53/2023" perlu verifikasi dari pasal penutup; tambahkan setelah membaca naskah.',
        ]),
        $iapsk('iapsk3-buku-1', 'PEDOMAN', 'IAPSK 3.0 Buku 1 — Naskah Akademik'),
        $iapsk('iapsk3-buku-2', 'PEDOMAN', 'IAPSK 3.0 Buku 2 — Pedoman Umum Akreditasi'),
        $iapsk('iapsk3-sarjana-buku-3-perolehan', 'PEDOMAN', 'IAPSK 3.0 Program Sarjana — Buku 3 Panduan Penyusunan LED dan Pengisian DKPS (Perolehan)', ['Laporan Evaluasi Diri', 'DKPS']),
        $iapsk('iapsk3-sarjana-buku-3-perpanjangan', 'PEDOMAN', 'IAPSK 3.0 Program Sarjana — Buku 3 Panduan Penyusunan LED dan Pengisian DKPS (Perpanjangan)', ['Laporan Evaluasi Diri', 'DKPS']),
        $iapsk('iapsk3-sarjana-buku-3-unggul', 'PEDOMAN', 'IAPSK 3.0 Program Sarjana — Buku 3 Panduan Penyusunan LED dan Pengisian DKPS (Unggul)', ['Laporan Evaluasi Diri', 'DKPS']),
        $iapsk('iapsk3-sarjana-buku-4-perolehan', 'INSTRUMEN', 'IAPSK 3.0 Program Sarjana — Buku 4 Panduan dan Matriks Penilaian (Perolehan)'),
        $iapsk('iapsk3-sarjana-buku-4-perpanjangan', 'INSTRUMEN', 'IAPSK 3.0 Program Sarjana — Buku 4 Panduan dan Matriks Penilaian (Perpanjangan)'),
        $iapsk('iapsk3-sarjana-buku-4-unggul', 'INSTRUMEN', 'IAPSK 3.0 Program Sarjana — Buku 4 Panduan dan Matriks Penilaian (Unggul)'),
    ],

    'rujukan' => [
        $rujukan([
            'kunci' => 'banpt-5-2019', 'jenis' => 'PERLEMBAGA', 'nomor' => '5', 'tahun' => 2019,
            'judul' => 'Instrumen Akreditasi Program Studi', 'badan' => 'BAN-PT', 'kategori' => ['akreditasi'],
            'keterangan' => 'Dokumen rujukan (metadata saja). Judul: perlu verifikasi.',
        ]),
        $rujukan([
            'kunci' => 'banpt-5-2023', 'jenis' => 'PERLEMBAGA', 'nomor' => '5', 'tahun' => 2023,
            'judul' => $judulKosong, 'badan' => 'BAN-PT', 'kategori' => ['akreditasi'],
            'keterangan' => 'Dokumen rujukan (metadata saja). Judul: perlu verifikasi.',
        ]),
        $rujukan([
            'kunci' => 'pp-27-2014', 'jenis' => 'PP', 'nomor' => '27', 'tahun' => 2014,
            'judul' => 'Pengelolaan Barang Milik Negara/Daerah', 'badan' => 'Pemerintah Republik Indonesia', 'kategori' => ['aset'],
            'tag' => ['Barang Milik Negara'],
        ]),
        $rujukan([
            'kunci' => 'lamdik-3-2025', 'jenis' => 'PERLEMBAGA', 'nomor' => '3', 'tahun' => 2025,
            'judul' => 'Instrumen Akreditasi Program Studi Kependidikan pada Program Studi yang Tercakup dalam LAMDIK (IAPSK 2.0)',
            'badan' => 'LAMDIK', 'kategori' => ['akreditasi'], 'tag' => ['LAMDIK'],
        ]),
        $rujukan([
            'kunci' => 'lamdik-4-2025', 'jenis' => 'PERLEMBAGA', 'nomor' => '4', 'tahun' => 2025,
            'judul' => 'Instrumen Akreditasi Program Studi Kependidikan pada Program Pendidikan Jarak Jauh yang Tercakup dalam LAMDIK (IAPSK PJJ 2.0)',
            'badan' => 'LAMDIK', 'kategori' => ['akreditasi'], 'tag' => ['LAMDIK', 'Pendidikan Jarak Jauh'],
        ]),
        $rujukan([
            'kunci' => 'banpt-20-2025', 'jenis' => 'PERLEMBAGA', 'nomor' => '20', 'tahun' => 2025,
            'judul' => 'Sistem Akreditasi Nasional Pendidikan Tinggi (SAN PT)', 'badan' => 'BAN-PT', 'kategori' => ['akreditasi'],
        ]),
        $rujukan([
            'kunci' => 'banpt-22-2025', 'jenis' => 'PERLEMBAGA', 'nomor' => '22', 'tahun' => 2025,
            'judul' => 'Mekanisme Perpanjangan Status Terakreditasi Perguruan Tinggi dan Program Studi', 'badan' => 'BAN-PT', 'kategori' => ['akreditasi'],
        ]),
        $rujukan([
            'kunci' => 'banpt-26-2025', 'jenis' => 'PERLEMBAGA', 'nomor' => '26', 'tahun' => 2025,
            'judul' => 'Perubahan atas Peraturan BAN-PT Nomor 22 Tahun 2025', 'badan' => 'BAN-PT', 'kategori' => ['akreditasi'],
        ]),
        $rujukan([
            'kunci' => 'banpt-27-2025', 'jenis' => 'PERLEMBAGA', 'nomor' => '27', 'tahun' => 2025,
            'judul' => 'Kriteria Melampaui Standar Nasional Pendidikan Tinggi bagi Program Studi yang Tercakup dalam LAMDIK', 'badan' => 'BAN-PT', 'kategori' => ['akreditasi'],
        ]),
    ],

    // [sumber, jenis relasi, target, keterangan]. Relasi 39/2025 → 53/2023 sengaja TIDAK dibuat (perlu verifikasi).
    'relasi' => [
        ['banpt-13-2025', 'mencabut', 'banpt-5-2019', null],
        ['banpt-13-2025', 'mencabut', 'banpt-5-2023', null],
        ['pp-28-2020', 'mengubah', 'pp-27-2014', null],
        ['permendiktisaintek-42-2025', 'mencabut', 'permendikbud-3-2021', 'Untuk lingkungan Kemendiktisaintek (pasal penutup perlu verifikasi)'],
        ['lamdik-5-2025', 'mencabut', 'lamdik-3-2025', 'Peralihan: pengajuan APSK s.d. 1 Maret 2026 masih memakai IAPSK 2.0'],
        ['lamdik-5-2025', 'mencabut', 'lamdik-4-2025', 'Peralihan: pengajuan APSK s.d. 1 Maret 2026 masih memakai IAPSK 2.0'],
        ['banpt-26-2025', 'mengubah', 'banpt-22-2025', null],
    ],
];
