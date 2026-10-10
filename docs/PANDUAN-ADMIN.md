# Panduan Admin — Regulasi FKIP Unsil

Untuk peran **admin-regulasi** (Admin Regulasi Fakultas) dan **super-admin** (TI fakultas). Panel admin:
`https://supportfkip.unsil.ac.id/regulasi/admin`. Kedua peran **wajib MFA** (aplikasi autentikator) — saat masuk
pertama Anda diminta memindai kode QR dan menyimpan kode pemulihan.

## 1. Alur verifikasi dokumen

```
Draf ──(Ajukan verifikasi)──► Menunggu verifikasi ──(Terbitkan)──► Terbit ──(Arsipkan)──► Diarsipkan
  ▲                                   │                                                     │
  └───────(perbaiki)◄── Ditolak ◄──(Tolak + catatan wajib)            (Pulihkan) ◄──────────┘
```

1. Buka **Dokumen → Antrean Verifikasi** (badge menu = jumlah menunggu). Kolom *Kelengkapan x/y* menunjukkan syarat
   terbit BR-05: jenis, judul, tahun, badan penetap, ≥ 1 kategori, ≥ 1 tautan **Naskah Utama**; peraturan/keputusan
   juga wajib nomor & tanggal penetapan (dokumen rujukan dikecualikan dari naskah & tanggal).
2. Buka dokumen → periksa metadata terhadap naskah (tombol **Buka** pada tautan naskah) → **Terbitkan** (catatan
   opsional) atau **Tolak** (catatan wajib; kontributor menerima notifikasi & surel berisi catatan).
3. Dokumen terbit tampil di portal, pencarian, sitemap, dan API ± 1 menit (indeks & cache diperbarui otomatis).
4. Dokumen yang salah input **diarsipkan** (alasan wajib), tidak dihapus: hilang dari portal/API/sitemap, URL lama
   404, relasinya berhenti berdampak; **Pulihkan** mengembalikannya. Hapus lunak hanya untuk super-admin (BR-15).
5. Terbitkan massal: centang beberapa baris di Antrean Verifikasi → **Terbitkan**; dokumen yang belum lengkap dilewati
   dengan daftar alasannya.

## 2. Relasi status & dokumen rujukan

Lihat rinci di [`docs/RELASI.md`](RELASI.md). Ringkas:

- Tab **Relasi** di halaman dokumen: *mencabut / dicabut oleh*, *mengubah / diubah oleh*, *melaksanakan /
  dilaksanakan oleh*. Cukup dicatat dari satu sisi; pasangan dibuat otomatis (BR-07).
- Status target berubah (Dicabut / Berlaku dengan Perubahan) bila sumber **terbit** dan tanggal efektifnya
  (berlaku → pengundangan → penetapan) sudah tiba; bila belum, ditandai "relasi tertunda" dan diterapkan penjadwal
  harian 00:15.
- Peraturan yang dicabut/diubah tetapi belum ada di repositori → **Tambah dokumen rujukan** (metadata saja, langsung
  terbit, label "Metadata saja — naskah belum tersedia"). Jangan menulis teks bebas.
- Relasi keliru: **Hapus** (alasan wajib) lalu tambah yang benar; status dihitung ulang.
- **Ubah status berlaku** (manual): *Tidak berlaku* untuk SK berjangka yang berakhir (catatan wajib; tidak ditimpa
  perhitungan relasi) atau *Kembalikan ke perhitungan otomatis*. Semua perubahan masuk tab **Riwayat status**.

## 3. Tautan berkas (tanpa unggah)

Sistem **tidak menyimpan berkas**. Naskah, lampiran, abstrak, dan halaman sumber berupa **tautan https** ke domain
daftar putih (`config/berkas.php`): Google Drive/Docs, `*.unsil.ac.id`, peraturan.go.id, `jdih.*.go.id`,
peraturan.bpk.go.id.

- Simpan PDF di **Shared Drive "Regulasi FKIP"** (dikelola admin regulasi, bukan Drive pribadi) agar tautan tidak
  putus saat pegawai pindah.
- Dokumen **publik**: Bagikan → *Siapa saja yang memiliki link* → *Pengakses*. Dokumen **internal**: *Terbatas* atau
  *Universitas Siliwangi* (pratinjau tampil hanya bagi pengguna yang masuk akun Google kampus; ekstraksi teks akan
  berstatus *Tidak dapat diunduh* — wajar, pencarian memakai judul & abstrak).
- Tempel tautan **berkas**, bukan folder (`/drive/folders/…` ditolak), bukan pemendek (bit.ly, s.id), bukan `http://`.
  Hanya satu Naskah Utama per dokumen: menambah naskah utama baru menjadikan yang lama lampiran.
- Setiap tautan diperiksa keteraksesannya (antrean `tautan`) dan, untuk PDF, diekstraksi teksnya (antrean
  `ekstraksi`, unduh sementara ≤ 30 MB lalu dihapus). Status terlihat di kolom *Status cek* dan *Ekstraksi*.
- **Laporan → Tautan Bermasalah**: tautan *Tidak dapat diakses*, ekstraksi *Gagal/Tidak dapat diunduh/Tanpa teks*,
  dan *Isi berubah* (hash PDF berbeda dari sebelumnya). Aksi **Periksa ulang** / **Ekstrak ulang** per baris.
  Perbaiki berbagi di Drive atau ganti tautan, lalu periksa ulang. PDF pindaian (gambar) berstatus *Tanpa teks*:
  lengkapi abstrak agar tetap tercari.

## 4. Master data

Menu **Master**: Jenis Dokumen (urutan menentukan urutan filter publik), Badan Penetap (T.E.U.), Kategori Sistem,
Tag. **Pengguna → Atur penugasan** menugaskan kontributor ke kategori sistem (kontributor hanya melihat & mengisi
dokumen kategori itu, BR-03).

## 5. Laporan

| Laporan | Isi | Ekspor |
|---|---|---|
| Dasbor | Terbit, menunggu verifikasi, dicabut, tautan dibuka 30 hari, kelengkapan abstrak & teks, antrean tertua, terpopuler | — |
| Statistik Koleksi | Pivot jenis/tahun/status/kategori/badan × status berlaku | Excel (antrean) |
| Kelengkapan Metadata | ✓/✗ abstrak, naskah, teks, tanggal, tag, sumber + skor; filter "hanya yang belum lengkap" | Excel (antrean) |
| Pembukaan Tautan | Per dokumen web/API, grafik harian (tanpa IP/user agent) | Excel (antrean) |
| Relasi Status | Relasi mencabut/mengubah efektif/tertunda | Excel (antrean) |
| Penggunaan API | Per klien: pembukaan via API, terakhir dipakai, token aktif | — |
| Laporan Evaluasi | Laporan periode (semester): hitung ringkasan → finalkan (terkunci) → **Unduh PDF** (di-stream, tidak disimpan). Lihat `docs/laporan-evaluasi.md` | PDF |

Ekspor Excel diproses di latar belakang; tautan unduh (berlaku 24 jam, hanya untuk Anda) dikirim lewat lonceng
notifikasi.

## 6. Klien API & token

Menu **Sistem → Klien API** (kelola: super-admin; admin-regulasi lihat saja). Dokumentasi untuk pengembang sistem
lain: [`docs/api.md`](api.md).

1. **Buat** klien (nama sistem, kategori, kontak).
2. Tab **Token → Buat token**: nama, centang *Boleh membaca dokumen internal* hanya bila perlu (ability
   `dokumen:baca-internal`), masa berlaku 90/180/365 hari. **Token polos tampil sekali** — salin dan kirim lewat
   kanal aman.
3. Token bocor/tidak dipakai → **Cabut token**. Klien dinonaktifkan → semua tokennya ditolak (401).

## 7. Pengguna, log, Horizon (super-admin)

- **Sistem → Pengguna**: buat akun staf (surel atur kata sandi terkirim), ubah peran, nonaktifkan (tidak bisa masuk
  panel mana pun). Pengguna kampus mendaftar sendiri di `/akun/register` dengan surel `@unsil.ac.id`.
- **Sistem → Log Aktivitas**: siapa mengubah apa (nilai lama/baru), tanpa kata sandi/token.
- **Horizon** (`/regulasi/horizon`, hanya super-admin): antrean `ekstraksi`, `tautan`, `default`, `indeks`,
  `notifikasi`, `ekspor`; job gagal dapat diulang dari sini.

## 8. Perintah artisan

Jalankan di container `app` (produksi: `docker compose --env-file .env.production -f docker-compose.prod.yml exec app …`;
lokal: `docker exec regulasi-php …`).

| Perintah | Fungsi | Terjadwal |
|---|---|---|
| `php artisan regulasi:terapkan-status [--dry-run]` | Terapkan relasi yang tanggal efektifnya sudah tiba; hitung ulang status target | harian 00:15 |
| `php artisan regulasi:ekstraksi-ulang [--gagal] [--tanpa-teks] [--tautan=ID]` | Antrekan ulang ekstraksi teks PDF | tiap jam (`--gagal`) |
| `php artisan regulasi:reindeks [--hapus]` | Terapkan pengaturan indeks Meilisearch & impor ulang dokumen terbit | manual |
| `php artisan regulasi:ringkasan-mingguan` | Kirim ringkasan mingguan ke admin-regulasi | Senin 07:30 |
| `php artisan regulasi:siapkan-uat [--segar] [--sandi=]` | Data & akun UAT per peran (**tolak di produksi**) | — |
| `php artisan berkas:periksa-tautan [--dokumen=ID]` | Antrekan pemeriksaan keteraksesan semua tautan dokumen terbit | Senin 06:30 |
| `php artisan berkas:bersihkan-tmp [--jam=24]` | Hapus berkas sementara > 24 jam di `storage/app/tmp` | harian 00:45 |
| `php artisan scout:sync-index-settings` | Terapkan pengaturan indeks (filter, sort, sinonim) | saat deploy |
| `php artisan schedule:list` | Daftar jadwal | — |

## 9. Bila ada masalah

- Pencarian tidak toleran salah ketik / faset kosong → Meilisearch mati; portal otomatis memakai cadangan FULLTEXT.
  Cek `/api/health` (`layanan.pencarian`), lalu `regulasi:reindeks`.
- Status berlaku tidak berubah setelah relasi → periksa sumber sudah terbit & tanggal efektif; `regulasi:terapkan-status --dry-run`.
- Notifikasi/surel tidak terkirim → cek Horizon (antrean `notifikasi`) dan pengaturan SMTP.
