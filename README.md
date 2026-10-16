# Regulasi FKIP Unsil

[![CI](https://github.com/satyasantika/regulasi/actions/workflows/ci.yml/badge.svg)](https://github.com/satyasantika/regulasi/actions/workflows/ci.yml)

Repositori terpusat **dokumen hukum dan dokumen pendukung** Fakultas Keguruan dan Ilmu Pendidikan (FKIP)
Universitas Siliwangi: undang-undang, peraturan pemerintah, peraturan menteri, peraturan BAN-PT/LAM, peraturan dan
keputusan rektor/dekan, surat edaran, SOP, pedoman, dan manual aplikasi. Satu rujukan regulasi bagi sistem-sistem
FKIP — Akreditasi, SDM, Persuratan, Aset/BMN, Microteaching, Keuangan, Puspresma, Kerja Sama, dan FKIP Edu (LMS ditunda).

Produksi: `https://supportfkip.unsil.ac.id/regulasi`. Sistem ini bukan portal JDIH resmi (tidak terdaftar di JDIHN);
naskah resmi tetap mengacu pada sumber penerbitnya. Skema metadata mengikuti semangat Permenkumham 8/2019 tentang
Standar Pengelolaan Dokumen dan Informasi Hukum.

## Fitur

- **Metadata lengkap** per dokumen: jenis, nomor, tahun, tentang, badan penetap (T.E.U.), tempat & tanggal
  penetapan, pengundangan, berlaku, sumber, subjek, kategori sistem, dan abstrak.
- **Berkas berupa tautan**, tanpa unggah ke server: naskah utama, lampiran, abstrak, dan halaman sumber resmi
  (Shared Drive fakultas atau situs pemerintah berdaftar putih), dibuka lewat rute bercatat.
- **Alur verifikasi**: draf → menunggu verifikasi → terbit/ditolak → diarsipkan, dengan riwayat status.
- **Relasi status simetris otomatis**: mencabut/dicabut oleh, mengubah/diubah oleh, melaksanakan/dilaksanakan oleh;
  status berlaku diperbarui sendiri (termasuk penerapan terjadwal).
- **Pencarian teks lengkap** berfaset dan toleran salah ketik. Target (Profil Ringan): Scout driver `database`
  (FULLTEXT) + kamus istilah, Meilisearch hanya cadangan. Kondisi saat ini: Meilisearch dengan cadangan FULLTEXT
  MariaDB; teks PDF diekstraksi dari tautan secara aman (unduh sementara anti-SSRF, langsung dihapus).
- **Portal publik** dengan landing page, daftar berfilter, detail dokumen dan pratinjau Google Drive, sitemap & SEO.
- **API baca JSON:API v1** bertoken untuk sistem FKIP lain.
- **Akun**: halaman masuk kiri-kanan, profil & ganti kata sandi (mengeluarkan sesi di perangkat lain), wajib ganti
  sandi per akun, MFA aplikasi autentikator (flag `MFA_AKTIF`), impor massal pengguna lewat salin-tempel Excel, dan
  impersonasi oleh super-admin yang tercatat.
- **Laporan**: dasbor, statistik, kelengkapan metadata, pemanfaatan, relasi status, penggunaan API, ekspor Excel, dan
  laporan evaluasi PDF; jejak audit seluruh perubahan; halaman eror bertema.

## Peran

| Peran | Akses |
|---|---|
| Tamu | Membaca dokumen publik yang terbit |
| `pengguna` | Masuk dengan surel `@unsil.ac.id`; membaca dokumen internal |
| `kontributor` | Mengisi draf dokumen dan tautan berkas pada kategori sistem yang ditugaskan |
| `admin-regulasi` | Memverifikasi, menerbitkan, mengelola relasi & status, master data, dan laporan |
| `super-admin` | Mengelola pengguna & peran, klien API, log aktivitas, impersonasi (dasbor Horizon hanya ada pada kondisi saat ini; hilang di Profil Ringan) |
| Klien API | Sistem FKIP lain dengan token Sanctum (baca saja) |

Panduan bergambar per peran tersedia di `public/panduan/` (tersaji di `/regulasi/panduan/`).

## Tumpukan teknologi

**Kondisi saat ini (kode berjalan):** Laravel 13 · PHP 8.5 · MariaDB 10.11 · Redis 7 · Filament 5 · Livewire 4 ·
Tailwind CSS 4 · Vite · Meilisearch + Laravel Scout · Horizon · Sanctum · spatie/laravel-permission ·
spatie/laravel-activitylog · poppler-utils · Pest 4 · Larastan · Pint.

**Target Profil Ringan (berlaku setelah Fase B):** tanpa Redis, Horizon, Octane, dan Meilisearch bawaan; antrean,
cache, sesi, dan lock di MariaDB (driver `database`); pencarian Scout driver `database` + kamus istilah; kesehatan lewat
`/api/health`; dua antrean (`default`, `berat`); tidak ada dasbor antrean. Standar (diperbarui keputusan 16 Oktober 2026) menetapkan PHP 8.5, MariaDB 10.11+ native di host (dev dan produksi,
nama database `db_<app>`), dan kolom UUID tipe native MariaDB lewat Blueprint Laravel. Image yang berjalan sudah PHP 8.5.11,
tetapi `composer.json` masih `"php": "^8.3"` (butir Fase B: `^8.5`).

Penyesuaian terhadap standar teknis (PHP, basis data, Docker, MFA) dicatat di [`docs/keputusan/`](docs/keputusan/README.md).

> **Profil Ringan (keputusan 15 Oktober 2026).** Dokumen perancangan sudah beralih ke Profil Ringan
> (STANDAR-TEKNIS §1b): tanpa Redis, Horizon, dan Octane; antrean/cache/sesi/lock di MariaDB; pencarian Scout driver
> database + kamus istilah (Meilisearch hanya cadangan). Profil Ringan berlaku setelah Fase B; kondisi saat ini
> adalah Redis + Horizon + Meilisearch. Bagian tumpukan, deploy, dan perintah di bawah masih
> menggambarkan kode yang saat ini berjalan (Redis, Horizon, Meilisearch) dan akan disesuaikan saat penyesuaian kode
> (Fase B) dikerjakan. LMS ditunda.

## Deploy

> Profil Ringan berlaku setelah Fase B; kondisi saat ini: compose produksi masih berisi queue/Horizon, Redis, dan
> Meilisearch. Target: satu container penjadwal yang juga menjalankan worker antrean, tanpa Redis/Horizon/Meilisearch.

Produksi memakai Docker Compose (`docker-compose.prod.yml`: app, web, queue/Horizon, scheduler, MariaDB, Redis,
Meilisearch, backup; **kondisi saat ini**. Target (keputusan 16 Oktober 2026): MariaDB native di host lewat
`host.docker.internal` (`extra_hosts: host-gateway`), compose produksi tanpa container database. Penghapusan service `mariadb`
dari compose adalah butir Fase B) di belakang reverse proxy subpath `/regulasi`. Rilis berjalan otomatis: push tag `vX.Y.Z` ke
GitHub → uji penuh → server menarik tag dan menjalankan `scripts/deploy.sh` (cadangan pra-migrasi, build, health check,
rollback cepat). Rincian, GitHub Secrets yang dibutuhkan, cadangan & pemulihan: [`docs/DEPLOY.md`](docs/DEPLOY.md).

## Keputusan yang menunggu pemilik

- Angka target kinerja pencarian: target pencarian pernah diubah dari < 200 ms menjadi < 500 ms tanpa persetujuan
  (PRD §kinerja). Perubahan itu **menunggu persetujuan pemilik**; angka tidak diubah lagi di dokumen ini.
- Diselesaikan keputusan pemilik 16 Oktober 2026: standar kini PHP 8.5 + MariaDB 10.11+ (kolasi `utf8mb4_unicode_ci`,
  tanpa SQLite); ADR 0001 sejalan dengan standar, tidak perlu dicabut.

## Dokumentasi

- [`docs/DEPLOY.md`](docs/DEPLOY.md) — Docker produksi, auto-pull, pembaruan, rollback, cadangan, reindeks.
- [`docs/OPERASIONAL.md`](docs/OPERASIONAL.md) — butir yang memerlukan tindakan/verifikasi manusia, status go-live.
- [`docs/api.md`](docs/api.md) — API baca v1.
- [`docs/KINERJA.md`](docs/KINERJA.md) — pengukuran kinerja.
- [`docs/RELASI.md`](docs/RELASI.md) — aturan relasi status antardokumen.
- [`docs/uat/hasil-uat.md`](docs/uat/hasil-uat.md) — lembar UAT (data uji: `php artisan regulasi:siapkan-uat`).
- [`docs/PANDUAN-ADMIN.md`](docs/PANDUAN-ADMIN.md), [`docs/PANDUAN-KONTRIBUTOR.md`](docs/PANDUAN-KONTRIBUTOR.md),
  dan panduan HTML di `public/panduan/`.
- Dasar hukum rujukan (Perpres 33/2012, Permenkumham 8/2019): `docs/referensi/README.md`.
- Riwayat rilis: [`CHANGELOG.md`](CHANGELOG.md).

## Menjalankan lokal

### Persiapan

> Profil Ringan berlaku setelah Fase B; kondisi saat ini: persiapan di bawah masih memasang Redis bersama dan
> `regulasi-meilisearch`.

Infrastruktur berada di luar repo ini, di `/home/satya/code` (lihat `docs/keputusan/0001-lingkungan-dan-penyimpangan-standar.md`):
container `regulasi-php`, `regulasi-nginx`, `regulasi-scheduler`, `regulasi-meilisearch` (berkas `docker-compose.yml`,
image `php/regulasi.Dockerfile` dengan `poppler-utils`), MariaDB native di host (`db_regulasi`, `db_regulasi_testing`),
Redis bersama (container `redis`, DB 44/45/46), dan Mailpit bersama (`alias-mailpit`). Tanpa Sail, tanpa PHP di host.

```bash
alias rgp='docker exec -it regulasi-php'
cd /home/satya/code && docker compose up -d --build regulasi-php regulasi-nginx regulasi-scheduler regulasi-meilisearch
cd regulasi
cp .env.example .env          # isi DB_PASSWORD dan MEILISEARCH_KEY (= REGULASI_MEILISEARCH_KEY compose)
rgp composer install
rgp php artisan key:generate
rgp php artisan migrate --seed  # super-admin fkip@unsil.ac.id bila REGULASI_SUPERADMIN_PASSWORD diisi
npm install && npm run build  # Node di host (container tidak memiliki Node); npm run dev untuk pengembangan
```

### Antrean dan penjadwal

> Profil Ringan berlaku setelah Fase B; kondisi saat ini: Horizon di service `regulasi-horizon` (uraian di bawah).
> Target: penjadwal menyalakan worker `database` tiap menit (`--stop-when-empty`), dua antrean (`default`, `berat`),
> tanpa Horizon dan tanpa `horizon:snapshot`; pantau lewat `/api/health`, `queue:failed`, `queue:retry`.

Antrean (`default`, `tautan`, `ekstraksi`, `indeks`, `notifikasi`, `ekspor`) dijalankan Horizon di service
`regulasi-horizon`; penjadwal di service `regulasi-scheduler`. **Restart Horizon setelah mengubah kode job:**

```bash
cd /home/satya/code && docker compose restart regulasi-horizon
```

Dasbor Horizon: http://localhost:8034/horizon (environment `local`). Kesehatan: `GET /api/health`.

Penjadwal (`routes/console.php`, zona Asia/Jakarta, semua `withoutOverlapping()->onOneServer()`) berjalan di service
`regulasi-scheduler`; restart service itu setelah mengubah jadwal. Cek daftar tugas:

```bash
docker exec regulasi-php php artisan schedule:list
cd /home/satya/code && docker compose restart regulasi-scheduler
```

| Jadwal | Perintah |
|---|---|
| Harian 00:15 | `regulasi:terapkan-status` (relasi yang tanggal efektifnya tiba) |
| Harian 00:45 | `berkas:bersihkan-tmp` (berkas sementara > 24 jam) |
| Tiap jam | `regulasi:ekstraksi-ulang --gagal` |
| Harian 01:00 | `model:prune --model=App\Models\LogBukaTautan` (retensi 24 bulan) |
| Harian 01:30 | `activitylog:clean` (retensi 365 hari) |
| Senin 06:30 | `berkas:periksa-tautan` (semua tautan dokumen terbit, dijeda per host) |
| Senin 07:30 | `regulasi:ringkasan-mingguan` (surel ke admin-regulasi) |
| Tiap 5 menit | `horizon:snapshot` |

Notifikasi (lonceng panel admin + surel, antrean `notifikasi`; kondisi saat ini, menjadi `default` pada Profil Ringan) terkirim lewat Mailpit bersama saat pengembangan:
http://localhost:18018.

### Pencarian (Scout + Meilisearch)

> Profil Ringan berlaku setelah Fase B; kondisi saat ini: Meilisearch (uraian di bawah). Target: Scout driver
> `database` + kamus istilah; Meilisearch hanya cadangan bila kriteria uji tidak tercapai.

Indeks `regulasi_dokumen` hanya berisi dokumen terbit (`SCOUT_PREFIX=regulasi_`, job indeks di antrean `indeks`; kondisi saat ini, Fase B menggantinya dengan Scout driver `database`).
Setelah memasang atau mengubah `config/scout.php`/sinonim (`config/regulasi.php` → `sinonim`), terapkan pengaturan
indeks lalu impor ulang:

```bash
rgp php artisan scout:sync-index-settings
rgp php artisan scout:import "App\Models\Dokumen"
```

Ekstraksi teks PDF dari tautan berjalan di antrean `ekstraksi` (kondisi saat ini; pada Profil Ringan menjadi antrean `berat`, pemetaan STANDAR-TEKNIS §1b) (`regulasi:ekstraksi-ulang --gagal|--tanpa-teks|--tautan=ID`);
sisa berkas sementara disapu `berkas:bersihkan-tmp`.

### Basis data uji

> Catatan: uraian uji di bawah menggambarkan kondisi saat ini (Redis bersama, grup `meilisearch`); pada Profil Ringan
> (setelah Fase B) uji memakai driver `database`/`array` tanpa Redis dan tanpa suite Meilisearch.

Uji (Pest) berjalan di MariaDB host (SQLite tidak dipakai) karena memakai indeks FULLTEXT dan CHECK. Buat sekali:

```sql
CREATE DATABASE db_regulasi_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- hook pre-commit memakai `php artisan test --parallel`: Laravel membuat db_regulasi_testing_test_N,
-- sehingga pengguna `app` wajib berhak CREATE DATABASE (GRANT pada `db\_regulasi\_testing%` atau global).
```

`phpunit.xml`: `DB_CONNECTION=mariadb`, `DB_DATABASE=db_regulasi_testing`, `CACHE_STORE=array`,
`QUEUE_CONNECTION=sync`, `SCOUT_DRIVER=collection`, `SCOUT_QUEUE_CONNECTION=sync`, `MAIL_MAILER=array` — uji tidak
menyentuh Redis bersama maupun jaringan (`Http::preventStrayRequests()` + DNS tiruan untuk `PengunduhAman`).
`tests/Integrasi` (grup `meilisearch`) memakai Meilisearch nyata dengan indeks sementara dan di-skip bila tidak terjangkau.

Lokal & waktu: `APP_LOCALE=id`, `APP_TIMEZONE=Asia/Jakarta`. Redis: DB 44 (default/lock), 45 (cache, kunci
berawalan `regulasi:`), 46 (antrean).

Aktifkan hook git (sekali per klon) — `commit-msg` menolak pesan di luar Conventional Commits, `pre-commit`
menjalankan Pint dan Pest di container `regulasi-php`:

```bash
chmod +x .githooks/*
git config core.hooksPath .githooks
```

Gerbang kualitas: `rgp composer cek` (Pint `--test`, Larastan level 6, Pest).

Aplikasi: http://localhost:8034/regulasi/ (juga http://localhost:8034 untuk pemeriksaan langsung) ·
Mailpit: http://localhost:18018 · Meilisearch hanya dari jaringan internal
(`rgp curl -s http://regulasi-meilisearch:7700/health`).

### Subpath `/regulasi`

Produksi: `https://supportfkip.unsil.ac.id/regulasi`. Reverse proxy memotong awalan sebelum meneruskan ke Nginx aplikasi,
sedangkan aplikasi membangkitkan semua URL dengan awalan itu (`URL::forceRootUrl`, `ASSET_URL`, `SESSION_PATH=/regulasi`,
`SESSION_COOKIE=regulasi_session`, `trustProxies`). Nginx lokal (`/home/satya/code/docker/nginx/regulasi.conf`) meniru
pemotongan awalan itu, sehingga lokal dan produksi berperilaku sama. Aset dibangun dengan `ASSET_URL` terisi
(`npm run build` membaca `.env`, base `/regulasi/build/`). Dilarang `href="/..."`, `src="/..."`, `action="/..."`,
`fetch('/...')` di Blade/JS (diuji `tests/Arch/SubpathTest.php`). Livewire, Filament, dan Horizon yang dipasang
kemudian wajib mengikuti awalan yang sama.

Blok reverse proxy produksi (di server depan):

```nginx
location = /regulasi { return 301 /regulasi/; }
location /regulasi/ {
    proxy_pass http://127.0.0.1:<port>/;          # garis miring akhir = awalan dipotong
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Prefix /regulasi;
    client_max_body_size 20m;
}
```

### Memperbarui panduan

Panduan pengguna HTML per peran ada di `public/panduan/` (tersaji di `/regulasi/panduan/`, ditautkan dari navigasi &
kaki portal, halaman masuk panel, dan menu pengguna panel). Tangkapan layar dibuat Playwright dari data rekaan
`PanduanSeeder` (hanya `APP_ENV=local`; akun `panduan.<peran>@contoh.test`, kata sandi `PANDUAN_PASSWORD`, tanpa MFA
khusus akun demo lokal; tidak memuat data pribadi asli):

```bash
docker exec regulasi-php php artisan migrate:fresh --seeder=PanduanSeeder   # menghapus data lokal! pakai DB lokal/khusus
docker exec regulasi-php php artisan regulasi:reindeks
# di host (composer di container tidak memiliki docker):
PANDUAN_PASSWORD=... docker compose -f docker-compose.panduan.yml --profile panduan run --rm regulasi-panduan [umum pengguna kontributor admin-regulasi super-admin]
```

Skrip `tests/panduan/tangkap.mjs` menyimpan `public/panduan/img/<peran>/NN-<langkah>.png` (1366×768, ponsel 390×844,
PNG palet terkompresi; kata sandi/kode MFA/token disamarkan). Teks halaman disunting langsung di `public/panduan/*.html`
(HTML mandiri, CSS di berkas, tanpa CDN/JS, semua tautan relatif). `PanduanTest` memeriksa kelengkapan halaman,
gambar, tautan relatif, dan ukuran total ≤ 15 MB.

## Lisensi & kontak

Kode aplikasi berlisensi MIT (lihat `composer.json`); naskah dokumen hukum tetap milik penerbitnya masing-masing.
Dikembangkan untuk Fakultas Keguruan dan Ilmu Pendidikan, Universitas Siliwangi, Tasikmalaya.

- Konten & verifikasi dokumen: Admin Regulasi FKIP — regulasi-fkip@unsil.ac.id
- Akun, MFA, dan server: Admin TI Fakultas
- Pengelola repositori: Satya Santika
