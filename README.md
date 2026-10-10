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

Aplikasi: http://localhost:8034 · Mailpit: http://localhost:18018 · Meilisearch hanya dari jaringan internal
(`rgp curl -s http://regulasi-meilisearch:7700/health`).
