# Regulasi FKIP Unsil

Repositori terpusat dokumen hukum dan dokumen pendukung bagi seluruh sistem Fakultas Keguruan dan Ilmu
Pendidikan (FKIP) Universitas Siliwangi. Sistem ini menyimpan metadata lengkap (jenis, nomor, tahun, tentang,
badan penetap, tanggal penetapan/pengundangan/berlaku, status berlaku, subjek, kategori sistem, abstrak),
tautan berkas naskah (tanpa unggah ke server), relasi status antardokumen (mencabut, mengubah, melaksanakan),
alur verifikasi sebelum terbit, pencarian teks lengkap, portal publik, dan API baca untuk sistem FKIP lain
(Akreditasi, SDM, Persuratan, Aset/BMN, Microteaching, LMS, Keuangan, Puspresma, Kerja Sama, FKIP Edu).
Nama "JDIH" sengaja tidak dipakai karena cakupannya lebih luas dari dokumen hukum dan sistem ini bukan portal
JDIH resmi yang terdaftar di JDIHN.

## Tumpukan teknologi

Laravel 13 · PHP 8.5 (di container) · MariaDB 10.11 (host) · Redis 7 · Filament 5 · Livewire 4 ·
Tailwind CSS 4 · Meilisearch + Laravel Scout · Horizon · Sanctum · Pest 4 · Larastan · Pint.

Penyesuaian terhadap standar paket (PHP, basis data, Docker) dicatat di `docs/keputusan/`.

## Dokumen

Semua dokumen perancangan ada di [`docs/vibecoding/`](docs/vibecoding/):

- `01-PRD.md` — kebutuhan produk dan aturan bisnis
- `02-ARSITEKTUR.md` — arsitektur
- `03-SKEMA-DATABASE.md` — skema basis data
- `04-PROMPT-BERTAHAP.md` — rencana kerja bertahap per fase
- `05-UJI-PENERIMAAN.md` — uji penerimaan
- `STANDAR-TEKNIS.md`, `STANDAR-GIT.md` — standar bersama
- `CLAUDE.md` — konteks untuk agen AI (juga di root repo)

Referensi dasar hukum (Perpres 33/2012, Permenkumham 8/2019) dijelaskan di `docs/referensi/README.md`.

Operasional: [`docs/DEPLOY.md`](docs/DEPLOY.md) (Docker produksi, pembaruan, rollback, reindeks),
[`docs/KINERJA.md`](docs/KINERJA.md) (pengukuran kinerja), [`docs/api.md`](docs/api.md) (API baca v1),
[`docs/OPERASIONAL.md`](docs/OPERASIONAL.md) (butir yang perlu tindakan/verifikasi manusia),
[`docs/uat/hasil-uat.md`](docs/uat/hasil-uat.md) (lembar UAT; data uji: `php artisan regulasi:siapkan-uat`),
panduan [`docs/PANDUAN-ADMIN.md`](docs/PANDUAN-ADMIN.md) dan [`docs/PANDUAN-KONTRIBUTOR.md`](docs/PANDUAN-KONTRIBUTOR.md).

## Memulai

### Menjalankan lokal

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
rgp php artisan migrate
npm install && npm run build  # Node di host (container tidak memiliki Node); npm run dev untuk pengembangan
```

### Antrean dan penjadwal

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

Notifikasi (lonceng panel admin + surel, antrean `notifikasi`) terkirim lewat Mailpit bersama saat pengembangan:
http://localhost:18018.

### Pencarian (Scout + Meilisearch)

Indeks `regulasi_dokumen` hanya berisi dokumen terbit (`SCOUT_PREFIX=regulasi_`, job indeks di antrean `indeks`).
Setelah memasang atau mengubah `config/scout.php`/sinonim (`config/regulasi.php` → `sinonim`), terapkan pengaturan
indeks lalu impor ulang:

```bash
rgp php artisan scout:sync-index-settings
rgp php artisan scout:import "App\Models\Dokumen"
```

Ekstraksi teks PDF dari tautan berjalan di antrean `ekstraksi` (`regulasi:ekstraksi-ulang --gagal|--tanpa-teks|--tautan=ID`);
sisa berkas sementara disapu `berkas:bersihkan-tmp`.

### Basis data uji

Uji (Pest) berjalan di MariaDB host, bukan SQLite, karena memakai indeks FULLTEXT dan CHECK. Buat sekali:

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

Gerbang kualitas: `rgp composer cek` (Pint `--test`, Larastan level 6, Pest). Laravel Boost (MCP `laravel-boost`)
dijalankan agen AI lewat `docker exec -i regulasi-php php artisan boost:mcp` (`.mcp.json` lokal).

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
