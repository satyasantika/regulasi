# 0003 — Database seragam `db_<app>`, UUID native MariaDB, produksi native, dan PHP 8.5

- Status: Diterima
- Tanggal: 2026-10-16

Keputusan pemilik 16 Oktober 2026 (lanjutan), mengikuti pembaruan STANDAR-TEKNIS §2.1, §2.2, §4a, dan §7.
Melengkapi ADR 0001 (tidak mencabutnya).

## Keputusan

1. **Nama database seragam** `db_<app>` dan `db_<app>_testing`. Untuk Regulasi: `db_regulasi` dan `db_regulasi_testing`
   (kode sudah memakainya). Nama pengguna DB tidak diubah. Database sementara memakai awalan yang sama
   (`db_regulasi_perf`, `db_regulasi_uat`, `db_regulasi_pulih`).
2. **UUID mengikuti Laravel 13.** `$table->uuid()`, `foreignUuid()`, dan `uuidMorphs()` menghasilkan tipe `UUID` native
   MariaDB; UUIDv7 dibuat aplikasi lewat `HasUuids`. Dokumen tidak lagi menyebut `CHAR(36)` sebagai tipe kolom.
   `char('id', 36)` dan `binary(16)` eksplisit dilarang (MariaDB menolak FK antar tipe berbeda). Gerbang uji:
   kolom `id` bertipe `uuid`/`char(36)`; 100 id UUIDv7 terurut sesuai `ORDER BY id`; FK dan morph berfungsi
   (`tests/Feature/UuidTest.php` memuat sebagian; sisanya ditambahkan).
3. **Produksi memakai MariaDB native di host** lewat `host.docker.internal` (`extra_hosts: host-gateway`), sama dengan
   aplikasi produksi lain. Compose produksi tidak boleh memuat container database; CI boleh memakai service container
   sementara. Alat dump: `mariadb-dump`. Compose Regulasi saat ini memuat service `mariadb`: **butir Fase B**.
4. **PHP 8.5.** Container `regulasi-php` yang berjalan sudah PHP 8.5.11. `composer.json` masih `"php": "^8.3"`:
   **butir Fase B** (`^8.5`, lalu perbarui `composer.lock`).

## Tidak diputuskan di sini

- Target kinerja pencarian < 500 ms tetap menunggu pemilik (lihat `docs/KINERJA.md`).
- Perilaku FULLTEXT MariaDB (token pendek, stopword, relevansi) tidak diputuskan di dokumen; dicatat sebagai gerbang uji
  `PencarianDatabaseTest` pada Fase B.

## Dampak

Dokumen diselaraskan (vibecoding, README, DEPLOY, OPERASIONAL, ADR 0001). Perubahan kode ada di Fase B.
