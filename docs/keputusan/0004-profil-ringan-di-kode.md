# 0004 — Profil Ringan di kode: antrean database, Scout `database` + kamus istilah, tanpa Horizon/Redis/Meilisearch

- Status: Diusulkan (menunggu persetujuan pemilik untuk butir bertanda (!))
- Tanggal: 2026-10-16

Pelaksanaan keputusan pemilik 15-16 Oktober 2026 (STANDAR-TEKNIS §1b, §4a, §7; ADR 0003) pada kode Regulasi, di cabang
`feat/profil-ringan`. Butir bertanda (!) berbeda dari teks standar/spesifikasi atau menyentuh kebijakan, dan belum diputuskan pemilik.

## Pilihan teknis

1. **Nama antrean.** `notifikasi`, `tautan`, `indeks` → `default`. `ekstraksi` dan `ekspor` (termasuk ekspor Filament
   `KelengkapanDokumenExporter::getJobConnection()/getJobQueue()`) → koneksi `database-berat`, antrean `berat`
   (`retry_after` 960 > `--timeout` 900). Job `EkstrakTeksTautan` (`timeout` 300) dan `BuatEksporLaporan` (`timeout` 600) tetap di bawah batas itu.
2. **Worker.** Dua `queue:work` di `routes/console.php` persis seperti STANDAR §1b (tanpa `--memory` pada worker `default`,
   berbeda dari Surat). Penjadwal menyalakan keduanya tiap menit; `horizon:snapshot` dihapus.
3. (!) **`Cache::lock` dipertahankan** pada `TransisiPublikasi`, `KunciStatusDokumen`, dan `TerapkanStatusTerjadwalCommand`,
   walau 02-ARSITEKTUR §3.3 menulis "tanpa `Cache::lock`". Dengan `CACHE_STORE=database` ia memakai tabel `cache_locks`
   (dibuktikan `tests/Feature/DriverDatabaseTest.php`); transaksi dan `lockForUpdate` yang sudah ada di dalam kunci tidak diubah.
   Penggantian ditunda atas instruksi pemilik; `AlurPublikasiTest`/`TambahRelasiTest` masih mengunci perilaku `Cache::lock`.
4. **`/api/health`** tetap berbentuk lama (`status`, `versi`, `waktu`, `layanan.*`); `layanan.redis` dan `layanan.pencarian`
   dihapus, `layanan.job_tertunda` (> 10 menit), `layanan.job_gagal_24j`, dan `layanan.detak_penjadwal`
   (`ok|terlambat|belum_ada`) ditambahkan. 503 hanya bila basis data gagal. Healthcheck `web` (`"status":"ok"`) tetap berlaku.
5. **Pencarian.** `Dokumen` memakai Scout driver `database` (`#[SearchUsingFullText]` pada `judul`, `sebutan_singkat`,
   `abstrak`; `#[SearchUsingPrefix]` pada `id`, `nomor`); `toSearchableArray()` kini hanya memuat kolom nyata tabel.
   `Dokumen::cariTerbit()` membatasi hasil Scout ke dokumen terbit (driver `database` tidak memanggil `shouldBeSearchable`).
   Layanan `PencarianDokumen` = `PencarianDatabase` (tanpa pemilih mesin): FULLTEXT `ft_dokumen`/`ft_ekstraksi_isi`, faset
   `GROUP BY`, sinonim dua arah dari tabel `sinonim_pencarian` (juga frasa, mis. "barang milik negara" ↔ "bmn").
   Koreksi salah ketik (`KoreksiIstilah`, levenshtein ≤ 2, ≤ 1 untuk token ≤ 5 huruf, tanpa koreksi token berangka) dijalankan
   **hanya bila hasil kosong** agar kata yang sah tetapi belum ada di kamus (mis. istilah di isi PDF) tidak diubah; pesan UI
   "Menampilkan hasil untuk …" dengan tautan "Cari persis" (parameter `persis`).
6. **Kamus.** Tabel `istilah_pencarian` dan `sinonim_pencarian` (UUID native, migrasi baru `2026_10_20_*`); `BangunKamus`
   (idempoten) dipanggil perintah `regulasi:bangun-kamus` (sinkron; `--antre` untuk antrean), job `BangunKamusIstilah`
   (`database-berat`, `ShouldBeUniqueUntilProcessing` agar perubahan yang datang saat job berjalan tidak hilang, bukan
   `ShouldBeUnique` seperti tertulis di prompt F7.2), jadwal Minggu 02:00, dan entrypoint setelah `migrate`. Pemicu:
   `Dokumen::sinkronkanIndeks()` (terbit/ubah/arsip/pulih, tautan, ekstraksi, status berlaku). `scout.queue` = `false`
   karena driver `database` tidak punya indeks yang disinkronkan.
7. **Horizon.** Provider, gate `viewHorizon`, config, paket, rute `/horizon`, dan izin `horizon.akses` dicabut (seeder
   21 permission; migrasi data `2026_10_20_100003_hapus_izin_horizon_akses` menghapus izin lama beserta pemberiannya).
   `config/queue.php`/`config/scout.php` dari kerangka kerja tetap digabung dengan bawaan (koneksi `redis` bawaan Laravel
   masih tampak di `config('queue.connections')`; tes arsitektur memeriksa berkas konfigurasi aplikasi, bukan hasil gabungan).
8. **Compose.** Layanan `app`, `web`, `scheduler`, `backup`; `backup` tetap berupa container berpenjadwal cron 02:00 (bukan
   profil host-cron seperti Surat) dan memakai pengguna aplikasi, bukan root. Nama layanan Nginx tetap `web` (instruksi
   menyebut `nginx`; mengganti nama menyentuh DEPLOY, skrip rilis, dan healthcheck). `docker/mariadb/regulasi.cnf` dihapus.
9. **CI.** `ci.yml` aktif (dipakai `deploy.yml`): hanya service Redis, `REDIS_HOST`, dan ekstensi `redis` yang dibuang.
10. **Panduan.** Baris "Horizon" dan tangkapan layar `05-horizon.png` dihapus dari panduan super-admin; tangkapan layar lain
    tidak diulang. Kalimat "toleran salah ketik" pada panduan tetap benar.

## Keputusan yang menunggu pemilik

- (!) Target kinerja pencarian < 500 ms (semula < 200 ms) belum diputuskan; tidak diubah. Kinerja pada kode baru belum diukur.
- (!) **Token minimum dan stopword FULLTEXT di MariaDB host** (`innodb_ft_min_token_size`, `innodb_ft_enable_stopword`): global
  per server, tidak diubah dari repo. Contoh env memakai `REGULASI_FT_MIN_TOKEN=3` (bawaan MariaDB); rilis lama memakai 2.
  Bila 2 dipilih: setel di host, bangun ulang indeks, dan ubah env. Dampak parity: "PP", "UU", "SE" (2 huruf) tidak
  tercari lewat FULLTEXT pada nilai 3; sinonim (`uu` ↔ `undang-undang`) hanya membantu bila kata kunci utuh ada di teks.
- (!) Nama pengguna DB (`app` pada contoh env; rilis lama `regulasi`) dan hak yang diperlukan `backup` (`SELECT, LOCK TABLES,
  SHOW VIEW, TRIGGER, EVENT`).
- (!) Port web: `WEB_PORT` (8034) dibuka di semua antarmuka (compose lama); Surat mengikatnya ke `127.0.0.1`. Tidak diubah.
- (!) `Cache::lock` tetap (butir 3) atau diganti transaksi + `lockForUpdate` saja.
- (!) Nama layanan `web` vs `nginx` (butir 8).
- Penghapusan `regulasi-horizon`, `regulasi-meilisearch`, dan volume `regulasi-meili-data` di
  `/home/satya/code/docker-compose.yml` (repo ini tidak menyentuh berkas itu).
- `regulasi.potong_isi_teks_kata` (60.000 kata) tidak lagi dipakai kode (hanya dibaca tes konfigurasi); pencarian DB memindai
  seluruh `isi_teks`. Hapus bersama tesnya atau pertahankan sebagai cadangan Meilisearch.

## Tidak dapat diverifikasi dari sini

Data produksi (jumlah baris, tipe kolom setelah restore ke MariaDB host, perilaku FULLTEXT pada naskah nyata), parity hasil
pencarian terhadap Meilisearch pada data nyata, kinerja, `docker stats`, uji asap compose terhadap MariaDB host, hak pengguna
cadangan, dan alur peralihan di server (`docs/DEPLOY.md`, "Peralihan dari rilis lama").
