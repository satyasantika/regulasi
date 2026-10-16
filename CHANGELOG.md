# Catatan Perubahan

Semua perubahan penting pada proyek ini dicatat di berkas ini.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/) dan proyek memakai [Semantic Versioning](https://semver.org/lang/id/).

## [Belum dirilis]

### Diubah (Profil Ringan di kode, cabang `feat/profil-ringan`)
- **PHP `^8.5`** di `composer.json`; **UUID native MariaDB** dibuktikan gerbang uji (`tests/Feature/UuidTest.php`: tipe `uuid`
  semua kolom id, tipe FK = tipe kolom yang diacu, 100 id UUIDv7 terurut pada `ORDER BY id`, morph; `tests/Arch/UuidTest.php`
  melarang `char('..._id', 36)`/`binary('..._id', 16)`). Hanya dua query `information_schema` untuk seluruh skema.
- **Antrean di database**: koneksi `database` (`after_commit`) dan `database-berat` (antrean `berat`, `retry_after` 960);
  hanya antrean `default` dan `berat` (nama lama `notifikasi`, `tautan`, `indeks` → `default`; `ekstraksi`, `ekspor` →
  `berat`, termasuk ekspor Filament). Worker dinyalakan penjadwal tiap menit (`--stop-when-empty`), dengan detak penjadwal,
  `queue:prune-failed` dan `queue:prune-batches`; `horizon:snapshot` dihapus. Sesi, cache, dan `Cache::lock` di database.
- **`GET /api/health`** tanpa Redis: `database`, `job_tertunda` (> 10 menit), `job_gagal_24j`, `detak_penjadwal`; 503 hanya
  bila basis data gagal. Kunci `layanan.redis` dan `layanan.pencarian` dihapus.
- **Pencarian**: Meilisearch diganti Laravel Scout driver `database` + FULLTEXT MariaDB + kamus istilah. Tabel baru
  `istilah_pencarian` dan `sinonim_pencarian`, perintah `regulasi:bangun-kamus` (mingguan, pascadeploy, dan lewat job
  `BangunKamusIstilah` di antrean `berat`), koreksi salah ketik `KoreksiIstilah` (levenshtein ≤ 2, ≤ 1 untuk token ≤ 5
  huruf; hanya bila hasil kosong; pesan "Menampilkan hasil untuk …" dengan tautan "Cari persis"), sinonim dua arah di
  kueri FULLTEXT. Dicabut: `meilisearch/meilisearch-php`, `PemilihMesinPencarian`, `PencarianMeilisearch`, `regulasi:reindeks`,
  suite `tests/Integrasi`.
- **Horizon dicabut**: paket, provider, gate, config, rute `/horizon`, izin `horizon.akses` (seeder dan migrasi data
  `hapus_izin_horizon_akses`), baris panduan super-admin dan tangkapan layar Horizon. Tes arsitektur anti-Horizon/Redis/Meilisearch.
- **Compose produksi** hanya `app`, `web`, `scheduler`, `backup` (klien `mariadb-dump` ke MariaDB host lewat
  `host.docker.internal`); tanpa `mariadb`, `redis`, `meilisearch`, `queue`; `mem_limit` dan rotasi log; Dockerfile tanpa
  `phpredis`; `docker/mariadb/regulasi.cnf` dihapus; `.env.production.example` memakai `DB_HOST=host.docker.internal` dan `db_regulasi`.
- CI (`ci.yml`): service Redis dan ekstensi `redis` dihapus (bagian lain tidak diubah).
- Peralihan dari rilis lama: `docs/DEPLOY.md` bagian "Peralihan dari rilis lama". Keputusan terbuka: ADR 0004.

### Diubah
- Dokumentasi diselaraskan dengan Profil Ringan (STANDAR-TEKNIS §1b, keputusan 15 Oktober 2026): README, DEPLOY,
  OPERASIONAL, KINERJA, dan docs/keputusan diberi penanda "Profil Ringan berlaku setelah Fase B; kondisi saat ini: ...".
  Tanpa perubahan kode. Target kinerja pencarian < 200 ms menjadi < 500 ms (oleh agen sebelumnya) menunggu persetujuan pemilik.

### Diubah (16 Oktober 2026)
- Dokumentasi diselaraskan dengan keputusan pemilik 16 Oktober 2026: PHP 8.5; basis data MariaDB 10.11+ native di host
  (bukan MySQL 8.4, bukan container); SQLite tidak dipakai; kolasi bawaan `utf8mb4_unicode_ci`. Paket vibecoding
  (PRD, arsitektur, skema, prompt bertahap, uji penerimaan, CLAUDE.md), README, DEPLOY, dan ADR 0001/indeks ADR
  disesuaikan; ADR 0001 kini sejalan dengan standar. Tanpa perubahan kode.

### Diubah (16 Oktober 2026, lanjutan)
- Dokumentasi diselaraskan dengan keputusan pemilik lanjutan (ADR 0003): nama database seragam `db_regulasi`/`db_regulasi_testing`
  (paket vibecoding, CLAUDE.md); tipe kolom UUID mengikuti Blueprint Laravel 13 (tipe `UUID` native MariaDB, bukan `CHAR(36)`;
  skema 03 diperbarui; gerbang uji UUID ditambahkan); produksi memakai MariaDB native di host lewat `host.docker.internal`
  (service `mariadb` di compose produksi ditandai Fase B); `composer.json` `^8.5` ditandai Fase B; nama antrean lama
  `tautan`/`ekstraksi`/`ekspor` dipetakan ke `default`/`berat`. Tanpa perubahan kode.

## [1.1.0] - 2026-10-12

### Ditambahkan
- Deploy otomatis dari GitHub (auto-pull): push tag `vX.Y.Z` → uji penuh (`ci.yml` dipakai ulang, kini juga
  shellcheck) → server menarik tag lewat SSH dan menjalankan `scripts/deploy.sh` (cadangan pra-migrasi, build,
  health check, `horizon:terminate` (kini `queue:restart`), rollback cepat `--rollback X.Y.Z`). GitHub Secrets: `SERVER_HOST`,
  `SERVER_USER`, `SSH_PRIVATE_KEY`, opsional `SERVER_PORT` (docs/DEPLOY.md).
- Landing page diperkaya: ajakan telusuri/masuk, bagian keunggulan repositori, dan kartu panduan per peran
  (panduan super-admin tidak ditampilkan di landing; berkasnya tetap di `public/panduan/`).
- Halaman masuk kiri-kanan bertema landing untuk panel admin dan akun (juga halaman daftar), tema bersama
  `public/css/regulasi.css`; warna utama panel menjadi indigo.
- Halaman eror bertema untuk 401, 403, 404, 419, 429, 500, 503 serta cadangan 4xx/5xx: tombol kembali & beranda,
  tombol masuk (401/419) dan coba lagi (429/500/503), aman subpath, tanpa detail teknis.
- Profil untuk semua peran (nama, NIP, ganti kata sandi). Ganti sandi mengeluarkan sesi di perangkat lain
  (AuthenticateSession kini juga di portal), mengganti token ingat-saya, dan tercatat di log aktivitas.
- Wajib ganti kata sandi per akun (kolom `wajib_ganti_sandi`, `sandi_diubah_pada`): diatur super-admin lewat form atau
  aksi massal; middleware `WajibGantiSandi` memaksa ke halaman profil.
- Super-admin awal `fkip@unsil.ac.id` dari seeder idempoten; kata sandi hanya dari `REGULASI_SUPERADMIN_PASSWORD`
  di `.env`; ditandai wajib ganti sandi saat masuk pertama.
- Flag `MFA_AKTIF` (bawaan `true`): `false` mematikan MFA seluruhnya. Keputusan & risiko di
  `docs/keputusan/0002-flag-mfa-aktif.md`.
- Impor massal pengguna lewat salin-tempel Excel (nama, surel, peran, kategori kontributor) dengan pratinjau,
  validasi domain `@unsil.ac.id`, idempoten (surel terdaftar dilewati), hanya super-admin.
- Impersonasi oleh super-admin (bukan diri sendiri/super-admin lain/akun nonaktif) dengan spanduk "Kembali ke akun
  saya", tercatat di log aktivitas (setiap entri memuat `impersonasi_oleh`), melewati MFA & wajib ganti sandi sasaran.

### Diubah
- Variabel super-admin awal `SEED_SUPERADMIN_EMAIL`/`SEED_SUPERADMIN_PASSWORD` diganti `REGULASI_SUPERADMIN_EMAIL`
  (bawaan `fkip@unsil.ac.id`) dan `REGULASI_SUPERADMIN_PASSWORD`.
- Panduan HTML 1.1.0: profil & kata sandi untuk semua peran, impor tempel, wajib ganti sandi, impersonasi, dan MFA
  untuk super-admin, halaman masuk dan eror di indeks (tangkapan layar halaman baru menyusul).
- README ditulis ulang; `APP_VERSION=1.1.0`.

### Keamanan
- Uji regresi CSRF rute keluar panel (KM-06): POST tanpa token ditolak 419 untuk pengguna masuk; 302 untuk tamu
  terbukti bukan celah (autentikasi berjalan sebelum pemeriksaan CSRF, tanpa sesi yang diakhiri).

## [1.0.0] - 2026-10-11

Rilis pertama Regulasi FKIP Unsil: repositori terpusat dokumen hukum & pendukung FKIP Universitas Siliwangi.

### Ringkasan fitur (F0–F11)
- Fondasi Laravel 13 / PHP 8.5 di Docker FKIP, MariaDB 10.11, Redis, Horizon, penjadwal, subpath `/regulasi`, UUIDv7.
- Autentikasi & peran (super-admin, admin-regulasi, kontributor per kategori, pengguna `@unsil.ac.id`), MFA wajib
  penerbit, jejak audit spatie/activitylog.
- Master jenis dokumen, badan penetap (T.E.U.), kategori sistem, tag, penugasan kontributor.
- Metadata dokumen lengkap (BR-06 unik), alur verifikasi draf → menunggu → terbit/ditolak → arsip dengan riwayat
  status append-only, aksi massal.
- Tautan berkas tanpa unggah (daftar putih domain, tolak pemendek/folder/http/IP), buka `/d/{id}/buka` tercatat dengan
  `ip_hash`, pemeriksaan keteraksesan, ekstraksi teks PDF anti-SSRF lewat `PengunduhAman`.
- Relasi status simetris (mencabut/mengubah/melaksanakan), dokumen rujukan, status manual, penerapan terjadwal.
- Pencarian Meilisearch berfaset toleran salah ketik dengan cadangan FULLTEXT MariaDB.
- Portal publik (beranda, daftar berfaset, detail + pratinjau Drive, SEO, sitemap), API baca JSON:API v1 bertoken
  Sanctum untuk sistem FKIP lain, notifikasi database/surel & ringkasan mingguan, dasbor, laporan & ekspor Excel,
  laporan evaluasi PDF.

### Ditambahkan (F12 pengerasan & rilis)
- Matriks akses PRD §3.2 teruji otomatis (`tests/Feature/Keamanan/MatriksAksesTest.php`: 60 rute × 5 peran + API &
  aksi) serta uji IDOR, tautan berbahaya/SSRF, open redirect, dan XSS (`KeamananTest`). Tidak ada policy yang perlu
  diperbaiki.
- Indeks `idx_dokumen_publik_terbaru (status_publikasi, visibilitas, dipublikasikan_pada)`; tabel admin dokumen
  memuat kolom terbatas + `withCount` lampiran; `PerfSeeder` (hanya lokal) dan `docs/KINERJA.md` (beranda hangat
  15 ms, `/dokumen` berfilter 52–81 ms pada 5.000 dokumen).
- Docker produksi: `docker/Dockerfile` (target `app` & `web`, PHP 8.5-FPM Alpine + `poppler-utils`, OPcache
  produksi, user non-root), `docker/nginx/default.conf`, `docker/entrypoint.sh` (tunggu DB, migrasi & sinkron indeks
  hanya di `app`, optimize/filament:optimize/icons:cache), `docker-compose.prod.yml` (app, web, queue/Horizon,
  scheduler, mariadb 10.11 dengan token FULLTEXT 2, redis AOF, meilisearch v1.54, backup), `.env.production.example`,
  `.dockerignore`, workflow `docker.yml`, `docs/DEPLOY.md`. Uji asap lokal: `/api/health` ok dan ekstraksi PDF nyata
  oleh container `queue`.
- Cadangan harian 02:00 WIB (`docker/backup/backup.sh`, `mariadb-dump` gzip + sha256, retensi 30 hari, log, keluar
  non-zero bila gagal), `restore.sh` (menolak produksi tanpa `--paksa`), `pulihkan-uji.sh`, dan prosedur "Uji pulih
  semester" (uji pulih lokal lulus).
- `php artisan regulasi:siapkan-uat` (akun uji per peran & klien API uji, hanya non-produksi), lembar
  `docs/uat/hasil-uat.md` dengan 30 pra-uji otomatis lulus, `docs/OPERASIONAL.md` (butir tindakan manusia & status
  go-live).
- Panduan pengguna HTML per peran dengan tangkapan layar Playwright (`public/panduan/`, 1,3 MB) dari data rekaan
  `PanduanSeeder` (hanya lokal, akun demo `@contoh.test` tanpa MFA), tautan di navigasi & kaki portal, halaman masuk,
  dan menu pengguna panel; `docker-compose.panduan.yml`, skrip `composer panduan`.
- Konfigurasi `REGULASI_FT_MIN_TOKEN` (panjang token cadangan FULLTEXT; produksi 2).

### Diubah
- `HeaderKeamanan`: tambah Permissions-Policy, Cross-Origin-Opener-Policy, hapus X-Powered-By, HSTS di produksi.
- Cookie sesi `Secure` secara bawaan saat `APP_ENV=production`.
- Urutan bawaan cadangan pencarian memakai pemutus seri `id DESC`.
- `docs/PANDUAN-ADMIN.md` & `docs/PANDUAN-KONTRIBUTOR.md` kini menunjuk panduan HTML.
- `APP_VERSION=1.0.0`.

## [0.11.0] - 2026-10-10

### Ditambahkan
- Dasbor admin (US-LAP-01, F11.1): halaman `Dasbor` menggantikan dasbor bawaan Filament dengan widget
  `StatistikDokumenOverview` (terbit, menunggu verifikasi, dicabut, tautan dibuka 30 hari, kelengkapan abstrak &
  teks), `DokumenPerJenisChart` (bar), `DokumenPerTahunChart` (line, 10 tahun), `AntreanVerifikasiWidget`
  (5 tertua, hanya `dokumen.verifikasi`), `DibukaTerpopulerWidget` (10 dokumen terbanyak dibuka 30 hari dari
  `log_buka_tautan`; pratinjau sematan Drive tidak dihitung, BR-17). Kontributor: semua angka dibatasi kategori
  yang ditugaskan (`LingkupLaporan`) dan antrean diganti `DrafSayaWidget` ("Draf & dokumen ditolak milik saya").
  Agregat di-cache 10 menit per kunci `dasbor:{peran}:{hash kategori}` dan dibersihkan oleh
  `CacheRegulasi::lupakanPublik()`.
- Laporan statistik koleksi (PRD §8, F11.2): halaman `Laporan → Statistik Koleksi`, pivot dimensi (jenis, tahun,
  status berlaku, kategori, badan penetap) × status berlaku + total, filter status publikasi (bawaan terbit) dan
  rentang tahun.
- Laporan kelengkapan metadata (US-LAP-02): tabel dokumen terbit dengan ✓/✗ abstrak, naskah utama, teks
  terekstraksi, tanggal penetapan, tanggal berlaku, tag, sumber, skor kelengkapan (%); filter "hanya yang belum
  lengkap", kategori, jenis; ringkasan rata-rata skor per kategori; aksi "Buka" ke halaman dokumen. Kriteria
  dihitung di SQL (`KriteriaKelengkapan`, tanpa N+1).
- Laporan pembukaan tautan (F11.3): rentang tanggal (bawaan 30 hari), saluran web/API, kategori; per dokumen
  (web, API, total) terurut total, halaman pertama = 20 terpopuler, grafik harian `PembukaanHarianChart`.
  `ip_hash`/`user_agent` tidak pernah ditampilkan.
- Laporan relasi status: relasi mencabut/mengubah (dokumen, pencabut/pengubah, tanggal efektif, keadaan) dengan
  filter efektif/tertunda.
- Laporan penggunaan API (super-admin & admin-regulasi): per klien jumlah tautan dibuka via API pada periode,
  terakhir dipakai, token aktif, kedaluwarsa token terdekat.
- Laporan evaluasi periode PDF (US-LAP-03, F11.4): tabel `laporan_evaluasi` (03-SKEMA §3.15), enum
  `StatusLaporan`, model `LaporanEvaluasi` (log aktivitas `laporan`, alias morph `laporan_evaluasi`),
  `LaporanEvaluasiPolicy`, `LaporanEvaluasiResource` (buat/ubah draf, "Hitung ringkasan", "Finalkan", "Unduh PDF").
  Snapshot `ringkasan` (`RingkasanEvaluasi`): koleksi, penambahan periode, perubahan status, kelengkapan, kesehatan
  tautan, pembukaan per saluran + 10 terpopuler, penggunaan API. PDF dompdf A4 portrait (kop FKIP Unsil, Bab I–VI +
  lampiran) dirender ulang dari snapshot dan di-stream lewat `GET /laporan-evaluasi/{id}/pdf`, tidak disimpan.
  Laporan final dikunci di model (`LaporanFinalTidakDapatDiubah`). Dokumentasi `docs/laporan-evaluasi.md`.
- Ekspor Excel berbasis antrean `ekspor`: job `BuatEksporLaporan` (statistik koleksi, pembukaan tautan, relasi
  status; `spatie/simple-excel`) menulis `storage/app/tmp/ekspor/{uuid}.xlsx` lalu mengirim notifikasi database
  `EksporLaporanSiap` berisi tautan unduh bertanda tangan 24 jam (`GET /ekspor/{berkas}`) yang hanya berlaku bagi
  peminta yang memegang `laporan.ekspor`. Kelengkapan metadata memakai Filament `ExportAction`
  (`KelengkapanDokumenExporter`, antrean `ekspor`, disk `tmp`) dengan tabel `exports` UUID (model `Ekspor`,
  `EksporPolicy`). Header kolom berbahasa Indonesia; berkas dihapus ≤ 24 jam oleh `berkas:bersihkan-tmp`.
- Disk `tmp` (`storage/app/tmp`) dan `storage/app/tmp/.gitignore`.
- Uji `tests/Feature/Laporan/` (DasborTest, LaporanKoleksiTest, LaporanPemanfaatanTest, LaporanEvaluasiTest).

### Diubah
- `barryvdh/laravel-dompdf` dipindah dari `require-dev` ke `require`; `spatie/simple-excel` ditambahkan.
- Tautan "Buka dasbor" pada surel ringkasan mingguan memakai rute `filament.admin.pages.dasbor`.
- `APP_VERSION=0.11.0`.

## [0.10.0] - 2026-10-10

### Ditambahkan
- Notifikasi database & surel (US-NOT-01/02, 02-ARSITEKTUR §10): tabel `notifications` (UUID, `uuidMorphs`), lonceng
  notifikasi database di panel admin (`databaseNotifications()`, polling 30 dtk). Kelas dasar `NotifikasiRegulasi`
  (`ShouldQueue`, antrean `notifikasi`, `afterCommit`, format database `Filament\Notifications\Notification`
  dengan tombol ke halaman admin dokumen, surel berbahasa Indonesia); isi dihitung saat dibuat sehingga tetap terkirim
  walau model pemicu sudah hilang. Penerima nonaktif (`users.aktif = false`) tidak menerima apa pun (`via()` kosong).
- `DokumenDiajukan` → pengguna ber-permission `dokumen.verifikasi` (super-admin hanya cadangan bila tidak ada admin
  aktif); `DokumenDiterbitkan` → pembuat draf dengan tautan halaman publik; `DokumenDitolak` → pembuat draf memuat
  catatan. Dikirim dari `setelahCommit` Action `AjukanVerifikasi`/`TerbitkanDokumen`/`TolakDokumen` (transaksi gagal →
  tidak ada notifikasi); pelaku aksi tidak memberi tahu dirinya sendiri.
- Notifikasi perubahan status per kategori (US-REL-04): pendengar `KirimNotifikasiStatusBerlaku` atas
  `StatusBerlakuBerubah` → `StatusDokumenBerubah` ke kontributor yang ditugaskan pada kategori dokumen + admin-regulasi,
  mis. "Permendikbud 3/2021 kini Dicabut oleh Permendiktisaintek 42/2025. Status: Berlaku → Dicabut."
- `TautanTidakDapatDiakses` (database + surel) dari `PeriksaTautanBerkas` → penambah tautan, dan admin-regulasi bila
  dokumen terbit (URL mentah tidak dicantumkan); `IsiBerkasBerubah` (database) lewat pendengar
  `KirimNotifikasiIsiBerkasBerubah`; `EkstraksiGagal` (database) dari `EkstrakTeksTautan::failed()`.
- Penjadwal `routes/console.php` (02-ARSITEKTUR §9, Asia/Jakarta, semua `withoutOverlapping()->onOneServer()`):
  `regulasi:terapkan-status` 00:15, `berkas:bersihkan-tmp` 00:45, `regulasi:ekstraksi-ulang --gagal` tiap jam,
  `model:prune --model=LogBukaTautan` 01:00 (retensi 24 bulan), `activitylog:clean --force` 01:30 (365 hari),
  `berkas:periksa-tautan` Senin 06:30, `regulasi:ringkasan-mingguan` Senin 07:30, `horizon:snapshot` tiap 5 menit.
- Perintah `berkas:periksa-tautan {--dokumen=*}` (`PeriksaTautanCommand`): antrekan `PeriksaTautanBerkas` untuk semua
  tautan dokumen terbit, dijeda per host (job ke-n ditunda ⌊n / batas `unduh-host` per menit⌋ menit), ringkasan jumlah
  per status cek.
- Ringkasan mingguan (US-NOT-03): `App\Support\RingkasanMingguan` (dokumen baru terbit 7 hari, antrean verifikasi
  jumlah & tertua, dokumen terbit tanpa abstrak/naskah utama/teks, tautan tidak dapat diakses, ekstraksi gagal/tidak
  dapat diunduh, isi berkas berubah, relasi tertunda yang efektif 30 hari ke depan), notifikasi surel
  `RingkasanMingguan`, dan perintah `regulasi:ringkasan-mingguan`.
- README: tabel jadwal, `schedule:list`, restart `regulasi-scheduler`, Mailpit.
- Uji `tests/Feature/Notifikasi/NotifikasiDokumenTest.php` dan `tests/Feature/Penjadwal/JadwalTest.php`.

### Diubah
- `PeriksaTautanBerkas` dan `EkstrakTeksTautan` tidak lagi hanya mencatat log/event: kini mengirim notifikasi nyata.
- `APP_VERSION=0.10.0`.

## [0.9.0] - 2026-10-10

### Ditambahkan
- Klien API (US-API-02, BR-18): tabel `klien_api` (03-SKEMA §3.14), model `KlienApi` (Authenticatable + `HasApiTokens`,
  UUIDv7, soft delete, log aktivitas `klien-api`, alias morph `klien_api`), factory, dan `KlienApiPolicy` (kelola →
  `klien-api.kelola`/super-admin; admin-regulasi lihat saja). FK `log_buka_tautan.klien_api_id` → `klien_api` (SET NULL)
  lewat migrasi baru.
- Filament `KlienApiResource` (grup Sistem): CRUD nama, kategori, kontak, aktif, catatan; kolom terakhir dipakai dan
  jumlah token aktif; relation manager Token dengan aksi **Buat token** (nama, "Boleh membaca dokumen internal",
  masa berlaku 90/180/365 hari; token polos ditampilkan sekali di modal bertombol salin dan peringatan) dan **Cabut
  token**. Action `BuatTokenKlienApi` dan `CabutTokenKlienApi` (tercatat di log aktivitas tanpa token polos).
- Token Sanctum read-only: ability `dokumen:baca` (wajib) dan `dokumen:baca-internal` (opsional); alias middleware
  `abilities`/`ability`. Middleware `PastikanKlienApiAktif`: pemilik token harus klien API aktif (selain itu 401
  JSON:API "Klien API tidak aktif"); `terakhir_dipakai_pada` diperbarui maksimal sekali per menit.
- API baca JSON:API v1 (US-API-01, BR-01, BR-02) memakai `JsonApiResource` bawaan Laravel 13 (`included` dibuat unik
  per type+id): `GET /api/v1/dokumen` (filter `filter[kategori|status|jenis|tahun|badan]`, koma = ATAU, alias
  `?kategori=…&status=…`, `q` lewat layanan `PencarianDokumen`, `sort` tahun/-tahun/tanggal_penetapan/-dipublikasikan_pada,
  `page[number]`, `page[size]` maks. 100, `include=kategori,relasi,tautan`, `meta.faset`, tautan paginasi),
  `GET /api/v1/dokumen/{id}`, `GET /api/v1/jenis-dokumen`, `GET /api/v1/kategori-sistem` (aktif, terurut,
  `meta.jumlah_dokumen`). Hanya dokumen terbit; internal hanya untuk token `dokumen:baca-internal`. Respons
  `application/vnd.api+json`; galat 401/403/404/405/422/429 berformat JSON:API (`GalatJsonApi`).
- Buka tautan dari API: dokumen publik → `/d/{id}/buka`; dokumen internal → URL bertanda tangan 10 menit
  `GET /d/{id}/buka-api` (`tautan.buka-api`, `signed`, `throttle:buka`) yang mencatat `log_buka_tautan` saluran `api`
  dengan `klien_api_id`. URL mentah tautan tidak pernah ada di JSON. Kontrak `PenyimpananBerkas::urlBukaApi()`.
- Rate limit `api` 60/menit per token (per IP bila tanpa token) dengan 429 JSON:API, `Retry-After`,
  `X-RateLimit-Limit/Remaining`. ETag lemah + `Last-Modified` pada detail dokumen; `If-None-Match` → 304.
- `docs/api.md`: autentikasi, cara meminta token, abilities, endpoint + contoh curl/respons, kode galat, rate limit,
  kebijakan versi, contoh "Dasar Hukum" sistem Aset dengan cache 1 jam.
- Uji `tests/Feature/Api/` (KlienApiTest, DokumenApiTest, RateLimitApiTest).

### Diubah
- `KriteriaPencarian`: urutan `penetapan_asc` dan batas per halaman dapat diatur (API 100, portal tetap 50).
- `Dokumen`: relasi alias `kategori()` dan `tautan()` (chaperone pemilik) untuk `include` JSON:API.
- Tamu pada rute `api/*` tidak lagi dialihkan ke halaman masuk (selalu 401 JSON); rute web tanpa `login` dialihkan ke
  halaman masuk panel akun.
- `APP_VERSION=0.9.0`.

## [0.8.0] - 2026-10-10

### Ditambahkan
- Tata letak portal publik `x-layouts.publik` (Blade + Livewire 4 + Tailwind CSS 4 via `@tailwindcss/vite`, US-PUB-03):
  `<html lang="id">`, tautan lewati-ke-konten, header dengan navigasi Beranda/Dokumen/Kategori (dropdown dari master
  ter-cache) dan tombol Masuk/nama pengguna → `/akun`, menu hamburger Alpine di < 768 px, slot breadcrumb, kaki berisi
  alamat FKIP (`config('regulasi.alamat')`, `REGULASI_ALAMAT`) dan versi. Komponen `x-publik.breadcrumb`,
  `x-publik.badge-status`, `x-publik.ikon` (Heroicon kategori), `x-publik.kartu-dokumen`, `x-publik.paginasi`.
- Beranda Livewire `Publik\Beranda` (`/`, `beranda`, US-PUB-01): hero dengan kotak pencarian (GET ke daftar dokumen),
  ringkasan angka, kartu per jenis (→ `/jenis/{slug}`), kartu kategori sistem berikon (→ `/kategori/{slug}`), 10 dokumen
  terbaru, tautan per status berlaku dan per tahun. `CacheRegulasi::statistikBeranda()`, `terbaru()`, `tahunTersedia()`
  (kunci `beranda:statistik`, `beranda:terbaru`, `faset:tahun`, 60 menit; hanya terbit & publik); pengguna login melihat
  dokumen internal pada daftar terbaru lewat query terpisah yang tidak di-cache (BR-02, BR-20).
- Daftar dokumen berfaset Livewire `Publik\DaftarDokumen` untuk `/dokumen`, `/jenis/{jenisDokumen:slug}` (jenis
  terkunci), dan `/kategori/{kategoriSistem:slug}` (kategori terkunci) (US-CARI-01..03): `q` (debounce 400 ms), `jenis[]`,
  `tahun[]`, `status[]`, `kategori[]`, `badan[]`, `urut` tersinkron ke URL; memakai `PencarianDokumen` F7.3 dengan
  `sertakanInternal` = login; faset berjumlah per dimensi dalam `fieldset/legend`, panel "Filter (n)" lipat di ponsel,
  chip filter aktif, "Hapus semua filter", jumlah hasil `aria-live`, catatan "Pencarian sederhana" saat mesin database,
  breadcrumb dan `<title>` per rute. Rate limit `pencarian` 60/menit per IP (mesin tidak dipanggil bila terlampaui).
- Halaman detail `/dokumen/{dokumen}/{slug?}` (`dokumen.show`, `Publik\DetailDokumenController`, US-PUB-02, BR-19): 404
  bila tidak terbit/terhapus, dokumen internal untuk tamu → masuk akun, slug kosong/lama → 301 (BR-16); breadcrumb
  Beranda › Jenis › sebutan; badge status + kalimat `RingkasanStatus` dan relasi tertunda; tabel metadata (T.E.U., tanggal
  `j F Y`, sumber + halaman sumber resmi, kategori, subjek → pencarian, bahasa); abstrak ter-escape per paragraf; relasi
  dikelompokkan (Mencabut … Dilaksanakan oleh) dengan badge dan tanda "Tertunda" (dokumen terkait yang belum terbit
  tidak ditampilkan, internal tanpa tautan bagi tamu); daftar tautan `x-tautan-berkas` (penyedia, jumlah halaman
  terekstraksi); kotak "Metadata saja" untuk `tanpa_berkas`; jumlah dibuka. Policy `DokumenPolicy::lihatDiPortal`.
- Pratinjau sematan `x-pratinjau-tautan` tanpa PDF.js/berkas lokal: Google Drive/Docs → iframe `/preview`
  (`loading="lazy"`, `referrerpolicy="no-referrer"`) yang baru dimuat setelah "Tampilkan pratinjau", dengan tombol
  "Buka berkas" sebagai cadangan; sumber resmi pemerintah → "Buka di situs sumber". URL pratinjau hanya bagi yang berhak.
- Middleware `HeaderKeamanan` (dasar F12.1): CSP dengan `frame-src https://drive.google.com https://docs.google.com`,
  `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`.
- SEO (F8.4): komponen `x-meta` (title, description, canonical slug terkini, Open Graph dasar); `noindex` untuk dokumen
  internal serta panel `/admin` dan `/akun` (render hook); JSON-LD schema.org `Legislation` (peraturan/keputusan:
  `legislationIdentifier`, `legislationDate`, `legislationPassedBy`, `legislationType`, `legislationLegalForce`) atau
  `CreativeWork` (`App\Support\DataTerstruktur`); `/sitemap.xml` (`Publik\SitemapController`) berisi beranda, daftar,
  jenis & kategori yang berdokumen, dan dokumen terbit & publik (`lastmod`), cache `sitemap` 1 jam yang ikut dibersihkan
  `lupakanPublik()`; `public/robots.txt` dengan baris untuk robots.txt gateway.
- Aksesibilitas: label untuk semua input, ikon `aria-hidden`, urutan heading h1→h2→h3, `focus-visible`, target sentuh
  ≥ 44 px di ponsel, tombol buka menyebut penyedia dan membuka tab baru `rel="noopener noreferrer"`; audit axe-core
  (WCAG 2.2 AA + best practice) tanpa pelanggaran untuk beranda, daftar, jenis, dan detail di 360 px dan 1280 px.
- Uji `tests/Feature/Publik/` (BerandaTest, DaftarDokumenTest, DetailDokumenTest, SitemapSeoTest), termasuk uji bahwa
  halaman detail dokumen internal tidak memuat URL Drive/URL mentah bagi tamu (tindak lanjut F4).

### Diubah
- `CacheRegulasi::master()` menyimpan larik atribut lalu meng-hydrate model: store Redis menolak unserialize objek
  (`cache.serializable_classes = false`), sehingga cache master yang hangat sebelumnya menimbulkan galat.
- Komponen `x-tautan-berkas` ditata ulang dengan Tailwind, menampilkan penyedia & jumlah halaman, dan menerima
  `:pratinjau="false"`. Rute `/` kini beranda portal (`welcome.blade.php` dihapus). Font Instrument Sans menambah bobot 700.
- `APP_VERSION=0.8.0`.

## [0.7.0] - 2026-10-10

### Ditambahkan
- Ekstraksi teks PDF dari tautan tanpa menyimpan berkas (BR-14, US-EKS-01/02): tabel `ekstraksi_teks` (03-SKEMA §3.10a,
  FULLTEXT `ft_ekstraksi_isi`), enum `StatusEkstraksi`, model `EkstraksiTeks` (alias morph `ekstraksi_teks`),
  `TautanBerkas::ekstraksi()`. `spatie/pdf-to-text` (poppler-utils) lewat `PengekstrakTeksPdf` (pdfinfo + pdftotext UTF-8,
  normalisasi spasi & karakter kontrol); teks minim → `tanpa_teks`.
- `PengunduhAman` anti-SSRF (02-ARSITEKTUR §4.3): daftar putih host (+ `domain_unduhan_tambahan`), resolusi DNS sendiri
  (`PemecahDns`) yang menolak IP privat/loopback/link-local/CGNAT/IPv4-mapped, sambungan dipaku `CURLOPT_RESOLVE`, redirect
  manual ≤ 3 dengan validasi ulang, batas 30 MB (Content-Length + penghitung progress saat streaming ke `sink`), timeout
  5/30 dtk, tanpa cookie, User-Agent `RegulasiFKIPUnsil/1.0`, tolak respons HTML Drive dan isi tanpa magic bytes `%PDF-`;
  berkas sementara `storage/app/tmp/ekstraksi/{uuid}.pdf` 0600. Kontrak `PenyimpananBerkas::ambilSementara()` →
  `BerkasSementara`.
- Job `PeriksaTautanBerkas` (antrean `tautan`, unik per tautan; HEAD lalu GET Range 0-1023 tanpa menyimpan isi; login
  Google/401/403 = dapat diakses; berubah menjadi tidak dapat diakses → peringatan log sampai notifikasi F10) dan
  `EkstrakTeksTautan` (antrean `ekstraksi`, unik, timeout 300, 3 percobaan backoff 60/300/900; penolakan terkendali tanpa
  retry; `failed()` → `gagal` + percobaan; hash berubah → `isi_berubah_pada` + event `IsiBerkasBerubah`; selesai → event
  `TeksTautanDiekstraksi`; berkas sementara selalu dihapus di `finally`). Pembatas `unduh-host:{host}` 30/menit (job
  dilepas ulang 60 dtk). `SimpanTautanBerkas` mengantrekan keduanya setelah commit; URL berubah mereset status cek &
  ekstraksi; jenis `halaman_sumber`/`lainnya` → `tidak_berlaku` tanpa job.
- Perintah `regulasi:ekstraksi-ulang {--gagal} {--tanpa-teks} {--tautan=*}` dan `berkas:bersihkan-tmp` (> 24 jam).
  Supervisor Horizon `supervisor-tautan` (1 proses) dan `supervisor-ekstraksi` (1 proses, timeout 330).
- Halaman admin "Tautan Bermasalah" (`/admin/tautan-bermasalah`, `dokumen.verifikasi`): tautan mati, ekstraksi bermasalah,
  dan isi berubah; aksi periksa ulang, ekstrak ulang (per baris & massal), tandai ditinjau. Kolom "Ekstraksi" di relation
  manager tautan berkas dan butir "Teks terekstraksi" di panel Kelengkapan membaca status naskah utama.
- Pencarian Laravel Scout + Meilisearch (02-ARSITEKTUR §5.1): indeks `regulasi_dokumen` hanya dokumen terbit & tidak
  dihapus, `toSearchableArray` (kategori, tag, isi teks ekstraksi berhasil naskah utama lebih dulu dipotong
  `potong_isi_teks_kata`), pengaturan filter/sort/typo dan sinonim dua arah dari `config('regulasi.sinonim')`, job indeks di
  antrean `indeks`. Indeks diperbarui eksplisit setelah commit: terbit/pulih/arsip, simpan dokumen terbit, tautan dihapus
  atau URL-nya berubah, pendengar `PerbaruiIndeksDokumen` (teks diekstraksi, status berlaku berubah).
- Layanan pencarian berfaset `PencarianDokumen` (`KriteriaPencarian` → `HasilPencarian`: paginator, faset jenis/tahun/
  status/kategori/badan, mesin): `PencarianMeilisearch` (filter IN, visibilitas, sort, facetDistribution, faset
  disjungtif) dan cadangan `PencarianDatabase` (MariaDB FULLTEXT BOOLEAN MODE + nomor LIKE + teks ekstraksi, faset
  GROUP BY). `PemilihMesinPencarian` turun ke database bila Meilisearch tidak sehat (`pencarian:sehat`, 60 dtk) dengan
  peringatan log. Perintah `regulasi:reindeks {--hapus}`.
- Uji: `Http::preventStrayRequests()` + DNS tiruan di semua uji; suite `tests/Integrasi` (grup `meilisearch`, di-skip bila
  Meilisearch tak terjangkau, mis. CI).

### Diubah
- Batas memori PHPUnit 512 MB (uji arsitektur mem-parse dependensi Scout).
- CI menjalankan uji ekstraksi dengan `--fail-on-skipped` (poppler-utils terpasang); `APP_VERSION=0.7.0`.

## [0.6.0] - 2026-10-10

### Ditambahkan
- Tabel `dokumen_relasi` (03-SKEMA §3.11: `uk_relasi`, `pasangan_id` UNIQUE FK diri SET NULL, CHECK
  `dokumen_id <> dokumen_terkait_id`, indeks `efektif`+`efektif_pada`) dan migrasi baru FK
  `riwayat_status_dokumen.dokumen_relasi_id` → `dokumen_relasi` SET NULL. Enum `JenisRelasi` (`kebalikan()`,
  `adalahArahAktif()`, `dampakStatus()`), model `DokumenRelasi` (alias morph `dokumen_relasi`, jejak audit `relasi`),
  `Dokumen::relasi()`, event `StatusBerlakuBerubah` (setelah commit; pendengar di F7/F10).
- Relasi simetris (BR-07..BR-10) lewat `TambahRelasiDokumen`: mencabut/mengubah/melaksanakan beserta kebalikannya
  dalam satu transaksi, jenis pasif dari sisi target dinormalkan, tolak relasi ke diri sendiri/ganda/siklik/sumber
  lebih lama; kunci `lock:status-dokumen:{id}` berurutan id menaik (`KunciStatusDokumen`) + `lockForUpdate`.
- Hitung ulang status berlaku (`HitungUlangStatusBerlaku`, BR-09: dicabut > diubah > berlaku; status manual tidak
  ditimpa) dengan riwayat berjenis `berlaku` yang menunjuk relasi pemicu.
- `HapusRelasiDokumen` (berpasangan, alasan wajib di log aktivitas), status manual `UbahStatusBerlaku`
  (tidak berlaku manual / kembalikan otomatis, catatan wajib, `status-berlaku.ubah`), `TerapkanRelasiDokumen`
  yang dipanggil setelah terbit, arsip, pulih, dan perubahan tanggal dokumen terbit; perintah
  `regulasi:terapkan-status` (`--dry-run`) untuk relasi yang tanggal efektifnya tiba (penjadwalan di F10.2).
- Antarmuka relasi: relation manager "Relasi" (tambah relasi dengan label "Dokumen ini mencabut …", pencarian
  dokumen di sisi server tanpa dokumen itu sendiri, pintas "Tambah dokumen rujukan" yang langsung terbit (BR-11),
  hapus dengan alasan, tanpa ubah), tombol "Ubah status berlaku", bagian "Status" berkalimat ringkas
  (`RingkasanStatus`, mis. "Dicabut oleh BAN-PT 13/2025 sejak 18 Agustus 2025") dan peringatan relasi tertunda.
  `DokumenRelasiPolicy`, `DokumenPolicy::ubahStatusBerlaku`.
- Seeder `DokumenContohSeeder` (03-SKEMA §4.5, lewat Action, idempoten): 26 dokumen contoh (buku 3 & 4 IAPSK 3.0
  masing-masing tiga entri), 9 dokumen rujukan, 7 relasi; fakta tak pasti dikosongkan dan ditandai "perlu verifikasi"
  di catatan internal; URL naskah dari data atau `database/seeders/data/tautan-contoh.local.php` (di-.gitignore);
  `REGULASI_SEED_TERBITKAN_SEMUA` untuk demo lokal. Dipanggil `DatabaseSeeder` di luar produksi.
- `docs/RELASI.md`: panduan singkat relasi untuk admin.

### Diubah
- Kait `setelahCommit`/`setelahTerbit`/`setelahArsip` alur publikasi menerima pengguna pelaku.

## [0.5.0] - 2026-10-10

### Ditambahkan
- Tabel `riwayat_status_dokumen` (03-SKEMA §3.12, append-only tanpa `updated_at`; `dokumen_relasi_id` tanpa FK
  sampai F6.1), enum `JenisRiwayatStatus` (publikasi, berlaku), model `RiwayatStatusDokumen` (ubah/hapus ditolak),
  `Dokumen::riwayatStatus()` terbaru lebih dulu.
- Alur publikasi lewat Action bertransaksi (`Cache::lock('lock:publikasi:{id}')` + `DB::transaction` +
  `lockForUpdate()`, satu baris riwayat per transisi, efek samping di `afterCommit`): `AjukanVerifikasi`
  (draf/ditolak → menunggu verifikasi), `TerbitkanDokumen` (menunggu verifikasi/draf → terbit; mengisi
  `diverifikasi_oleh`/`diverifikasi_pada`, `dipublikasikan_pada` hanya pertama kali; titik kait `setelahTerbit`),
  `TolakDokumen` (catatan wajib ≥ 10 karakter ke `catatan_verifikasi`), `ArsipkanDokumen` (catatan wajib; titik kait
  `setelahArsip`), `PulihkanDokumen` (diarsipkan → terbit). `PeriksaKelengkapan` memeriksa syarat terbit BR-05.
  `DokumenPolicy::ajukan`, `verifikasi`, `arsipkan`; `CacheRegulasi::lupakanPublik()` dipanggil saat terbit,
  arsip, dan pulih (BR-20).
- Tombol alur publikasi di halaman lihat & ubah dokumen (Ajukan verifikasi, Terbitkan, Tolak, Arsipkan, Pulihkan)
  menurut Policy + status; kekurangan kelengkapan tampil sebagai notifikasi. Panel "Kelengkapan" (checklist BR-05 +
  abstrak, tanggal berlaku, tag, teks terekstraksi), relation manager riwayat status (baca saja), dan banner catatan
  penolakan di halaman ubah.
- Halaman "Antrean Verifikasi" (`/admin/antrean-verifikasi`, `dokumen.verifikasi`, badge jumlah menunggu): urut paling
  lama menunggu, kolom pembuat, kategori, lama menunggu, kelengkapan x/y; aksi baris Terbitkan & Tolak dan Terbitkan
  massal yang melewati dokumen tidak lengkap dan melaporkannya.

### Diubah
- `StatusPublikasi::bolehTransisiKe()`: dokumen ditolak boleh langsung diajukan ulang ke menunggu verifikasi.

## [0.4.0] - 2026-10-10

### Ditambahkan
- Tabel `dokumen` (03-SKEMA §3.7, UUIDv7, soft delete) dengan `uk_dokumen_identitas` (BR-06), `idx_dokumen_publik`,
  `idx_dokumen_jenis_tahun`, dan FULLTEXT `ft_dokumen`; pivot `dokumen_kategori_sistem` dan `dokumen_tag`.
  Enum `StatusPublikasi` (`bolehTransisiKe()` sesuai PRD §6.1), `StatusBerlaku`, `Visibilitas`.
- Model `Dokumen`: scope `terbit()`, `publik()`, `terlihatOleh()` (tamu: terbit & publik; login: terbit),
  `dalamKategoriPengguna()`; `tanggal_efektif` (berlaku ?? pengundangan ?? penetapan); jejak audit tanpa
  `jumlah_dibuka`. `SebutanDokumen` (mis. "PMK 181/PMK.06/2016", "BAN-PT 13/2025") dan `BuatSlugDokumen` (unik, ≤ 200).
- Resource Filament "Dokumen" (`/admin/dokumen`): form bersection lewat aksi `SimpanDokumen` (nomor wajib untuk
  jenis peraturan/keputusan, tanggal pengundangan ≥ penetapan, duplikat BR-06 ditolak, slug dibentuk ulang saat
  nomor/judul berubah), tab status publikasi, filter jenis/tahun/status/kategori/badan/visibilitas. Status publikasi &
  berlaku tidak dapat diubah lewat form. `DokumenPolicy`: kontributor hanya kategori yang ditugaskan dan hanya
  mengubah/menghapus draf/ditolak miliknya (BR-03, BR-16); admin tidak mengubah dokumen diarsipkan.
- Tautan berkas tanpa unggahan (BR-13): tabel `tautan_berkas` polimorfik (alias morph `dokumen`, morph map wajib),
  `config/berkas.php`, rule `TautanBerkasValid` (https, tanpa userinfo/port/IP, daftar putih per label termasuk
  sumber resmi pemerintah, tolak pemendek & folder Drive, pesan perbaikan berbahasa Indonesia), `PenguraiTautan`
  (penyedia, `drive_file_id`, URL pratinjau/unduh), kontrak `PenyimpananBerkas` + `TautanEksternal`, aksi
  `SimpanTautanBerkas` (tolak URL/berkas ganda dalam dokumen, peringatan bila dipakai dokumen lain, naskah utama lama
  menjadi lampiran, `PeriksaTautanBerkas` diantrekan) dan `HapusTautanBerkas`; relation manager tautan berkas dengan
  petunjuk berbagi publik/internal. Uji arsitektur: tidak ada `FileUpload`/penulisan disk berkas.
- Buka tautan berotorisasi `GET /d/{id}/buka` (`tautan.buka`): `TautanBerkasPolicy::buka` (tamu: terbit & publik;
  login: terbit; staf: dokumen yang boleh dilihat), tamu ke dokumen internal diarahkan masuk, selain itu 404; redirect
  302 hanya ke URL tersimpan dengan `Referrer-Policy: no-referrer` dan `Cache-Control: no-store`; rate limit `buka`
  30/menit per pengguna/IP. Log `log_buka_tautan` (`ip_hash` SHA-256(IP + APP_KEY), tanpa IP mentah, prunable
  24 bulan) dan penghitung `jumlah_dibuka` lewat job `CatatBukaTautan` setelah respons. Komponen Blade
  `x-tautan-berkas` (Buka berkas + pratinjau sematan Drive bagi yang berhak, URL mentah tidak dicetak).

## [0.3.0] - 2026-10-10

### Ditambahkan
- Tabel master (03-SKEMA §3.2–§3.6, UUIDv7): `jenis_dokumen`, `badan_penetap` (T.E.U., induk SET NULL),
  `kategori_sistem` (ketiganya soft delete), `kategori_sistem_user` (PK komposit), `tag`; enum
  `KelompokJenisDokumen` (`wajibNomor()`) dan `TingkatBadanPenetap`; model dengan slug otomatis unik,
  scope `aktif()`/`terurut()`, jejak audit `master`.
- Seeder master idempoten: 18 jenis dokumen, 11 badan penetap (FKIP berinduk Universitas Siliwangi),
  11 kategori sistem berikon Heroicon, 16 tag subjek; konvensi T.E.U. ditandai perlu verifikasi.
- Cache master Redis `App\Support\CacheRegulasi::master()` (`master:{nama}`, 24 jam, aktif & terurut)
  yang dibersihkan otomatis oleh `MasterCacheObserver` dan setelah pengurutan ulang.
- Resource Filament grup "Master": Jenis Dokumen (urut seret-lepas), Badan Penetap, Kategori Sistem, Tag;
  filter aktif/terhapus, pulihkan; kode jenis/kategori terkunci setelah dibuat. Policy: lihat `master.lihat`,
  kelola `master.kelola`, tag baru `tag.buat`, tanpa hapus permanen.
- Penugasan kontributor ke kategori sistem: aksi "Atur penugasan" (`penugasan.kelola`, admin-regulasi tanpa
  hak ubah data pengguna lain) dan isian di form pengguna; perubahan tercatat di log aktivitas.
  Akun lokal `kontributor.aset@unsil.test` ditugaskan ke kategori Aset/BMN.

## [0.2.0] - 2026-10-10

### Ditambahkan
- Peran & permission (spatie/laravel-permission, UUID): `super-admin`, `admin-regulasi`, `kontributor`, `pengguna`
  dengan 22 permission (PRD §3.3, 03-SKEMA §4.1); `Gate::before` super-admin; Horizon hanya untuk `horizon.akses`.
- Seeder `PeranDanIzinSeeder` (idempoten) dan `PenggunaAwalSeeder` (super-admin dari `SEED_SUPERADMIN_*`,
  akun contoh `*@unsil.test` hanya di lokal).
- Kolom `users`: `nip`, `prodi_id`, `kode_eksternal`, `aktif`, `terakhir_masuk_pada` (dicatat saat masuk).
- Filament 5: panel `admin` (`/admin`, staf) dan panel `akun` (`/akun`, masuk/daftar/verifikasi surel/profil),
  berjalan di bawah subpath `/regulasi` (middleware `AwalanSubpath` untuk URL Livewire).
- Registrasi mandiri hanya surel `@unsil.ac.id`, otomatis berperan `pengguna`; masuk dibatasi 5 percobaan/menit per surel + IP.
- Jejak audit (spatie/laravel-activitylog, UUID, retensi 365 hari): perubahan nama/surel/NIP/status aktif dan
  pemberian/pencabutan peran pengguna; halaman baca-saja "Log Aktivitas" (`audit.lihat`).
- Manajemen pengguna oleh super-admin (tautan atur kata sandi, tanpa hapus permanen, tidak dapat menonaktifkan diri sendiri);
  admin-regulasi hanya melihat.
- MFA aplikasi autentikator (kode pemulihan) di kedua panel; wajib bagi super-admin dan admin-regulasi.

## [0.1.0] - 2026-10-10

### Ditambahkan
- Laravel 13 di Docker FKIP: container `regulasi-php` (PHP 8.5 + `poppler-utils`), `regulasi-nginx` (port 8034),
  `regulasi-scheduler`, `regulasi-horizon`, `regulasi-meilisearch`; MariaDB native di host; Redis bersama DB 44/45/46;
  Mailpit bersama (penyesuaian standar dicatat di `docs/keputusan/0001`).
- Gerbang kualitas: Pest 4, Larastan level 6, Pint, Laravel Boost; hook `commit-msg` dan `pre-commit` aktif.
- Lokal Indonesia (`id`, `Asia/Jakarta`, terjemahan laravel-lang), `CarbonImmutable`, pencegahan lazy loading,
  `config/regulasi.php`.
- Horizon (supervisor `ekstraksi` dan `umum`), Sanctum (`routes/api.php`), endpoint `GET /api/health`.
- Primary key UUIDv7 untuk semua tabel aplikasi (`users`, `personal_access_tokens` via model `TokenAkses`) dan uji arsitektur.
- Siap subpath `https://supportfkip.unsil.ac.id/regulasi` (root URL, aset, cookie `regulasi_session` ber-path `/regulasi`, proxy tepercaya).
- CI GitHub Actions: Pint, Larastan, Pest (MariaDB 10.11, Redis 7, poppler-utils) dan build aset.
- Inisialisasi repositori: `.gitignore`, hook git (`commit-msg`, `pre-commit`), dan referensi dasar hukum di `docs/referensi/`.
