# Regulasi FKIP — Referensi Pembuatan Sistem

Folder ini berisi dasar hukum dan referensi arsitektur untuk membangun sistem
"**Regulasi**" — repositori terpusat dokumen hukum dan dokumen pendukung
(pedoman/panduan teknis non-hukum) untuk seluruh sistem di bawah FKIP Unsil.

Cakupan sistem (disepakati 2026-10-06): tidak terbatas pada produk hukum resmi
(UU, PP, Permendikbud, Peraturan Rektor, SK) — juga mencakup dokumen pendukung
non-hukum seperti panduan BAN-PT, pedoman teknis Kemendikti, manual aplikasi,
dsb. Karena cakupannya lebih luas dari JDIH klasik, nama "JDIH" dihindari agar
tidak terikat standar resmi JDIHN (yang mewajibkan proses akreditasi BPHN dan
scope ketat pada produk hukum saja).

## Dasar hukum JDIH (referensi struktur, bukan kewajiban akreditasi)

| File | Isi | Kenapa relevan |
|---|---|---|
| `Perpres-No-33-Tahun-2012-JDIHN.pdf` | Perpres No. 33/2012 tentang Jaringan Dokumentasi dan Informasi Hukum Nasional | Dasar hukum pembentukan JDIHN — definisi anggota jaringan, kewajiban partisipasi, struktur pembinaan (BPHN sebagai pusat). Referensi ini dipakai BUKAN untuk mengikat sistem Regulasi secara hukum (karena sistem kita bukan portal JDIH resmi terdaftar), tapi sebagai acuan konsep "jaringan dokumentasi hukum" yang sudah terbukti dipakai ratusan instansi (`jdih.unsil.ac.id` sendiri mengikuti pola ini). |
| `Permenkumham-No-8-Tahun-2019-Standar-Pengelolaan-Dokumen-Informasi-Hukum.pdf` | Permenkumham No. 8/2019 tentang Standar Pengelolaan Dokumen dan Informasi Hukum | **Paling penting untuk desain data model.** Berisi 3 lampiran standar: (1) Standar Pembuatan Abstrak Peraturan, (2) Standar Pengolahan Dokumen dan Informasi Hukum (metadata wajib: jenis, nomor, tahun, tentang, status berlaku/dicabut/diubah, tanggal penetapan/pengundangan, subjek/tag, lampiran), (3) Standar Laporan Evaluasi. Jadikan acuan skema tabel `dokumen_hukum` / metadata minimal. |

## Taksonomi metadata standar (diringkas dari Permenkumham 8/2019 & contoh JDIH aktif)

Field metadata yang konsisten dipakai semua portal JDIH yang dicek (JDIH DPR,
Komdigi, PU, UNNES, ESDM):

- **Jenis/Bentuk Dokumen** — UU, PP, Perpres, Permen, Peraturan Rektor, SK, Keputusan,
  Surat Edaran, SOP, Panduan/Pedoman (kategori tambahan untuk dokumen non-hukum)
- **Nomor** dan **Tahun**
- **Tentang** (judul/perihal)
- **T.E.U.** (Tentang/Entitas/Unit — badan yang menetapkan)
- **Tempat & Tanggal Penetapan**, **Tanggal Pengundangan**, **Tanggal Berlaku**
- **Status**: Berlaku / Diubah / Dicabut / Tidak Berlaku — dengan relasi ke
  dokumen yang mengubah/mencabut (atau yang diubah/dicabut olehnya)
- **Subjek/Tag** (untuk pencarian — bisa multi-tag)
- **Sumber** (nomor Berita Negara/Lembaran Negara, atau asal unggahan)
- **Bidang Hukum / Kategori Sistem** (pemetaan ke salah satu dari 10 sistem FKIP:
  Akreditasi, SDM, Persuratan, Aset/BMN, Microteaching, LMS, Keuangan,
  Puspresma, Kerja Sama, FKIP Edu)
- **Lampiran** (file PDF utama + lampiran tambahan, bisa multi-file per dokumen)
- **Abstrak** (ringkasan isi — sesuai Standar Pembuatan Abstrak di Permenkumham 8/2019)

## Fitur umum yang ditemukan di implementasi JDIH yang baik

Dari pengecekan `jdih.dpr.go.id`, `jdih.komdigi.go.id`, `jdih.pu.go.id`,
`jdih.unnes.ac.id` (paling relevan — sesama perguruan tinggi):

1. **Filter multi-dimensi**: tipe dokumen, bentuk dokumen, tahun, status
   (berlaku/dicabut), kategori/bidang — biasanya sidebar kanan atau kiri
2. **Pencarian full-text** pada judul + abstrak, bukan cuma judul
3. **Halaman detail per dokumen**: metadata lengkap, status relasi
   (mencabut/dicabut-oleh, mengubah/diubah-oleh), tombol unduh, kadang
   pratinjau PDF inline (viewer, bukan harus download dulu)
4. **Statistik ringkas** di beranda: jumlah dokumen per jenis
5. **Struktur breadcrumb jenis dokumen** sebagai navigasi utama (mis. UNNES:
   Peraturan Perundang-undangan, Peraturan Rektor, Keputusan, SE, SOP, Dokumen
   Hukum Unit Kerja, Monografi, Artikel Hukum, Putusan Pengadilan, Dokumen Langka)
   — pola ini bisa diadaptasi: kategori jenis dokumen UNNES + kategori sistem FKIP
   sebagai dua dimensi filter independen

## Contoh referensi implementasi (riset, bukan untuk clone langsung)

- `jdih.unnes.ac.id` — PTN sejenis, strukturnya paling relevan untuk ditiru
  (Laravel-based, filter tipe dokumen lengkap, halaman per dokumen rapi)
- `jdihn.go.id` — portal agregator nasional, referensi UX pencarian lintas instansi
- Studi kasus akademik: "Development of JDIH Information System" (Diskominfo
  Kota Batu, metodologi XP, Laravel+MySQL) dan studi serupa di FMIPA Universitas
  Halu Oleo — pola arsitektur: akun admin + sub-admin dengan hak terbatas,
  CRUD dokumen, kategori, tag, tahun publikasi, pencarian & unduh publik

## Rekomendasi skema tabel awal (draf, didiskusikan sebelum eksekusi)

```
dokumen (
  id, judul, jenis_dokumen_id FK, nomor, tahun,
  teu (badan penetap), tempat_penetapan, tanggal_penetapan,
  tanggal_pengundangan, tanggal_berlaku,
  status ENUM('berlaku','diubah','dicabut','tidak_berlaku'),
  abstrak TEXT, sumber, kategori_sistem_id FK (nullable, multi via pivot?),
  file_utama_path, created_at, updated_at
)
jenis_dokumen (id, nama)           -- UU, PP, Perpres, Peraturan Rektor, SK, Panduan, dst
kategori_sistem (id, nama)         -- Akreditasi, SDM, Persuratan, Aset, Microteaching,
                                    -- LMS, Keuangan, Puspresma, Kerja Sama, FKIP Edu
dokumen_kategori_sistem (dokumen_id, kategori_sistem_id)  -- pivot, many-to-many
dokumen_relasi (dokumen_id, dokumen_terkait_id, tipe ENUM('mencabut','dicabut_oleh','mengubah','diubah_oleh'))
dokumen_lampiran (id, dokumen_id, nama_file, path)
tag (id, nama)
dokumen_tag (dokumen_id, tag_id)
```

Catatan: skema ini draf awal untuk didiskusikan — belum final, belum migrasi.
