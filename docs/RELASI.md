# Relasi dokumen dan status berlaku — panduan singkat admin

Relasi mencatat hubungan hukum antardokumen dan menentukan **status berlaku** secara otomatis
(PRD BR-07..BR-12). Kelola di halaman dokumen → tab **Relasi** (butuh izin `relasi.kelola`).

## Arti tiap relasi

| Dicatat dari sisi sumber | Sisi target otomatis | Dampak pada target |
|---|---|---|
| **Mencabut** | Dicabut oleh | Status target menjadi **Dicabut** |
| **Mengubah** | Diubah oleh | Status target menjadi **Berlaku dengan Perubahan** (bila tidak sedang dicabut) |
| **Melaksanakan** | Dilaksanakan oleh | Tidak mengubah status |

Relasi selalu **simetris**: cukup dicatat dari salah satu sisi ("Dokumen ini mencabut …" pada dokumen baru, atau
"Dokumen ini dicabut oleh …" pada dokumen lama); sistem membuat pasangannya sendiri. Relasi ke diri sendiri, relasi
ganda, saling mencabut, dan dokumen lebih lama yang mencabut/mengubah dokumen lebih baru ditolak.

## Kapan status berubah

- Relasi **berdampak (efektif)** bila dokumen sumber berstatus **Terbit** dan tanggal efektifnya
  (tanggal berlaku → pengundangan → penetapan; bila kosong semua: saat terbit) **sudah tiba**.
- Sumber masih draf/menunggu verifikasi: relasi tercatat tetapi belum berdampak; berdampak otomatis saat sumber diterbitkan.
- Tanggal efektif di masa depan: halaman target menampilkan peringatan "Relasi tertunda" dan status diterapkan oleh
  tugas harian `regulasi:terapkan-status` pada tanggalnya (cek manual: `php artisan regulasi:terapkan-status --dry-run`).
- Sumber diarsipkan: relasinya tidak lagi berdampak dan status target dihitung ulang; dipulihkan → berdampak lagi.
- Mengubah tanggal penetapan/pengundangan/berlaku dokumen terbit menerapkan ulang relasinya.
- Prioritas: **Dicabut > Berlaku dengan Perubahan > Berlaku**. Semua perubahan tercatat di tab **Riwayat status**.

## Status manual

Tombol **Ubah status berlaku** di halaman lihat dokumen (izin `status-berlaku.ubah`, catatan wajib):
- **Tidak berlaku (manual)** — mis. SK berjangka yang sudah berakhir; tidak ditimpa perhitungan relasi.
- **Kembalikan ke perhitungan otomatis** — status dihitung ulang dari relasi yang ada.

## Menambah dokumen rujukan

Bila peraturan yang dicabut/diubah belum ada di repositori, jangan menulis teks bebas. Di tab Relasi pilih
**Tambah dokumen rujukan**: isi jenis, nomor, tahun, judul, badan penetap, kategori (opsional langsung pilih jenis
relasinya). Dokumen rujukan berisi metadata saja (tanpa berkas), langsung terbit, dan tampil di portal sebagai
"Metadata saja — naskah belum tersedia". Naskahnya dapat ditautkan kemudian.

## Mengoreksi relasi yang keliru

Relasi tidak dapat diubah. **Hapus** baris relasi (alasan wajib; pasangannya ikut terhapus dan status dihitung
ulang), lalu **Tambah relasi** yang benar. Alasan penghapusan tercatat di Log Aktivitas.
