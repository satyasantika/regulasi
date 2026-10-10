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

Akan dilengkapi di F1.
