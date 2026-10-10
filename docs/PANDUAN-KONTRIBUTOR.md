# Panduan Kontributor — Regulasi FKIP Unsil

Untuk pengelola sistem/unit (mis. Aset/BMN, Persuratan, Akreditasi) berperan **kontributor**. Anda mengisi draf
dokumen untuk **kategori sistem yang ditugaskan** kepada Anda, lalu mengajukannya; admin regulasi memverifikasi dan
menerbitkan. Panel: `https://supportfkip.unsil.ac.id/regulasi/admin` (akun dibuat admin; tautan atur kata sandi
dikirim lewat surel).

## 1. Yang dapat dan tidak dapat Anda lakukan

| Dapat | Tidak dapat |
|---|---|
| Melihat dokumen di kategori Anda (semua status) | Melihat dokumen kategori lain di panel |
| Membuat draf, mengubah/menghapus draf **milik sendiri** berstatus Draf/Ditolak | Mengubah dokumen yang menunggu verifikasi, sudah terbit, atau milik kontributor lain |
| Menempel tautan naskah & lampiran pada draf milik sendiri | Mengunggah berkas (tidak ada fitur unggah) |
| Mengajukan verifikasi | Menerbitkan, menolak, mengarsipkan, mengubah status berlaku |
| Menambah tag baru | Mengubah master lain (jenis, badan penetap, kategori) |
| Melihat laporan untuk kategori Anda | Laporan evaluasi, penggunaan API, log aktivitas |

Usul relasi (mis. "dokumen ini mencabut SK lama") ditulis di **Catatan internal**; admin yang mencatat relasinya.

## 2. Mengisi metadata

**Dokumen → Buat**. Bagian formulir:

- **Identitas**
  - *Jenis dokumen* — mis. SOP, Pedoman/Panduan, Keputusan Dekan, Peraturan Menteri.
  - *Nomor* — tulis **persis seperti di naskah**, mis. `181/PMK.06/2016`, `0123/UN58.07/KP/2025`. Kosongkan untuk
    dokumen tanpa nomor (pedoman, SOP).
  - *Tahun* — tahun penetapan.
  - *Badan penetap (T.E.U.)* — Tajuk Entri Utama: badan yang menetapkan, ditulis dari induk ke unit, mis.
    `Indonesia. Kementerian Keuangan` (PMK), `Indonesia. Badan Akreditasi Nasional Perguruan Tinggi`,
    `Universitas Siliwangi. Fakultas Keguruan dan Ilmu Pendidikan` (Keputusan/Peraturan Dekan). Pilih dari daftar;
    bila tidak ada, minta admin menambahkannya.
  - *Judul (tentang)* — teks setelah kata "tentang", huruf seperti di naskah, tanpa nomor/jenis, mis.
    `Pengelolaan Barang Milik Negara pada Lingkungan FKIP`.
- **Penetapan & Keberlakuan** — tempat & tanggal penetapan (wajib untuk peraturan/keputusan), tanggal pengundangan
  (bila diundangkan), tanggal mulai berlaku (bila berbeda dari penetapan). *Sumber*: tempat terbit resmi, mis.
  `Berita Negara RI Tahun 2016 Nomor 1745` atau `Arsip FKIP Unsil`. *Tautan halaman sumber resmi* opsional, mis.
  halaman peraturan.go.id.
- **Klasifikasi** — *Kategori sistem* (hanya kategori Anda yang tersedia), *Subjek/tag*, *Visibilitas*: **Publik**
  (semua orang) atau **Internal** (hanya pengguna yang masuk; bagikan berkasnya terbatas ke unsil.ac.id).
- **Abstrak** — teks polos ringkas (2–5 kalimat): apa yang diatur, untuk siapa, pokok ketentuan. Contoh:
  "Mengatur tata cara peminjaman barang milik negara di lingkungan FKIP oleh dosen, tenaga kependidikan, dan
  organisasi mahasiswa, meliputi pengajuan, persetujuan, serah terima, dan pengembalian." Abstrak membantu pencarian,
  terutama untuk PDF hasil pindaian.
- **Sebutan** — terisi otomatis (mis. "Keputusan Dekan FKIP Universitas Siliwangi Nomor … Tahun …"); ubah hanya bila
  keliru.

Simpan → dokumen berstatus **Draf**; slug & sebutan dibuat otomatis.

## 3. Menempelkan tautan naskah & lampiran (bukan unggah)

1. Unggah PDF ke **Shared Drive "Regulasi FKIP"** (folder kategori Anda), bukan Drive pribadi.
2. Atur berbagi: dokumen publik → *Siapa saja yang memiliki link*; dokumen internal → *Universitas Siliwangi* atau
   *Terbatas*.
3. Klik kanan berkas → *Bagikan* → *Salin link* (bentuk `https://drive.google.com/file/d/…/view`).
4. Di halaman dokumen, bagian **Tautan berkas** → **Tambah tautan**: tempel URL, pilih *Jenis* (**Naskah Utama**
   untuk naskah; *Lampiran*, *Abstrak*, *Terjemahan*, *Halaman Sumber Resmi*), beri *Label* bila perlu
   (mis. "Lampiran I — Formulir").
5. Status **cek** dan **ekstraksi** terisi otomatis dalam ± 1 menit. *Tidak dapat diunduh* pada dokumen internal
   adalah wajar.

Tautan yang **ditolak**: tautan folder Drive, pemendek (bit.ly, s.id), `http://`, alamat IP, domain di luar daftar
(mis. dropbox.com). Pesan galat menjelaskan cara memperbaikinya. Satu dokumen hanya punya satu Naskah Utama.

## 4. Mengajukan verifikasi

Tombol **Ajukan verifikasi** di halaman dokumen. Bila belum lengkap, muncul daftar kekurangan (mis. "Tambahkan tautan
berkas berjenis Naskah Utama", "Tanggal penetapan wajib untuk peraturan/keputusan"). Setelah diajukan status menjadi
**Menunggu Verifikasi** dan formulir terkunci.

## 5. Menanggapi penolakan

Bila admin menolak, Anda menerima notifikasi (lonceng) dan surel berisi catatan. Buka dokumen: kotak merah
**"Dokumen ditolak verifikator"** menampilkan catatan penolakan. Perbaiki metadata/tautan → **Ajukan verifikasi** lagi. Setelah terbit Anda menerima
notifikasi berisi tautan halaman publik; perubahan status dokumen di kategori Anda (mis. dicabut) juga diberitahukan.

## 6. Tanya-jawab singkat

- *Dokumen terbit perlu dikoreksi?* Hubungi admin regulasi; kontributor tidak dapat mengubah dokumen terbit.
- *Tidak melihat kategori yang dibutuhkan?* Minta admin menambahkan penugasan kategori.
- *Lupa kata sandi?* Gunakan "Lupa kata sandi" di halaman masuk.
