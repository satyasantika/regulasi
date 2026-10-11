<?php

/**
 * Teks halaman eror HTTP (resources/views/errors). Kunci = kode status; 'ulang' menampilkan tombol "Coba lagi"
 * (hanya untuk permintaan GET). Pesan sengaja umum: tidak ada detail teknis yang dibocorkan.
 */
return [
    '401' => ['judul' => 'Anda belum masuk', 'pesan' => 'Halaman ini hanya dapat dibuka setelah Anda masuk.', 'saran' => ['Masuk dengan akun Anda, lalu buka kembali halaman ini.']],
    '403' => ['judul' => 'Akses tidak diizinkan', 'pesan' => 'Akun Anda tidak memiliki izin untuk membuka halaman atau melakukan tindakan ini.', 'saran' => ['Pastikan Anda masuk dengan akun yang benar.', 'Dokumen internal hanya untuk pengguna yang sudah masuk; kontributor hanya untuk kategori yang ditugaskan.', 'Hubungi Admin Regulasi bila Anda merasa seharusnya punya akses.']],
    '404' => ['judul' => 'Halaman tidak ditemukan', 'pesan' => 'Alamat yang Anda buka tidak ada, atau dokumennya belum diterbitkan atau sudah tidak tersedia.', 'saran' => ['Periksa kembali penulisan alamat.', 'Cari dokumen lewat kotak pencarian di beranda.']],
    '419' => ['judul' => 'Sesi telah berakhir', 'pesan' => 'Halaman kedaluwarsa karena terlalu lama dibiarkan atau Anda keluar dari perangkat lain.', 'saran' => ['Muat ulang halaman, masuk kembali bila diminta, lalu ulangi langkah Anda.', 'Isian yang belum disimpan perlu dimasukkan ulang.']],
    '429' => ['judul' => 'Terlalu banyak permintaan', 'pesan' => 'Anda mengirim terlalu banyak permintaan dalam waktu singkat.', 'saran' => ['Tunggu sekitar satu menit, lalu coba lagi.'], 'ulang' => true],
    '500' => ['judul' => 'Terjadi kesalahan pada server', 'pesan' => 'Maaf, ada masalah di sisi kami. Pengelola dapat menelusurinya dari catatan sistem.', 'saran' => ['Coba muat ulang beberapa saat lagi.', 'Bila berulang, sampaikan waktu kejadian di bawah ini kepada Admin TI Fakultas.'], 'ulang' => true],
    '503' => ['judul' => 'Layanan sedang dalam pemeliharaan', 'pesan' => 'Aplikasi sedang dalam pemeliharaan atau pembaruan. Data Anda aman.', 'saran' => ['Coba lagi beberapa saat lagi.'], 'ulang' => true],
    '4xx' => ['judul' => 'Permintaan tidak dapat diproses', 'pesan' => 'Permintaan tidak dapat diselesaikan.', 'saran' => ['Muat ulang halaman atau kembali ke beranda.']],
    '5xx' => ['judul' => 'Layanan sedang bermasalah', 'pesan' => 'Server tidak dapat menyelesaikan permintaan saat ini.', 'saran' => ['Coba lagi beberapa saat lagi.'], 'ulang' => true],
];
