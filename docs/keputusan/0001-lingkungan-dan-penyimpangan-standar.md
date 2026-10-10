# 0001 — Lingkungan pengembangan dan penyimpangan dari standar paket

- Status: Diterima
- Tanggal: 2026-10-10

Mesin pengembang sudah memiliki infrastruktur Docker bersama untuk seluruh sistem FKIP (pola yang sama dengan
sistem `keuangan`, ADR 0003 di repo itu). Keputusan ini menggantikan bagian standar teknis bila bertentangan:

| Topik | Paket | Keputusan |
|---|---|---|
| PHP | 8.4 | **8.5** (image `~/code/php/regulasi.Dockerfile` = `laravel8.5.Dockerfile` + `poppler-utils`, container `regulasi-php`) |
| Basis data | MySQL 8.4, `utf8mb4_0900_ai_ci`, DB `regulasi`/`regulasi_testing` | **MariaDB 10.11 native** di host; `db_regulasi`, `db_regulasi_testing`; koneksi `mariadb`, collation `utf8mb4_unicode_ci`; uji berjalan di MariaDB (FULLTEXT InnoDB didukung) |
| Redis | DB 44/45/46 | Tetap **44/45/46** (container `redis` bersama sudah 64 DB), prefiks `regulasi_` |
| Compose/Nginx | `docker-compose.yml` di repo, jaringan `fkip-net` | Berada di `/home/satya/code` (`docker-compose.yml`, `docker/nginx/regulasi.conf`, `php/regulasi.Dockerfile`); port Nginx **8034**; jaringan `code_laranet` |
| Meilisearch | Service di compose repo | Service `regulasi-meilisearch` di compose bersama `/home/satya/code`, tanpa port host |
| Node/Vite | Node di image PHP | Node dijalankan di **host** (`npm install && npm run build`) |
| URL lokal | `http://localhost:8080/regulasi` (gateway) | `APP_URL=http://localhost:8034/regulasi`; Nginx aplikasi menerima awalan `/regulasi` langsung (lihat F1.6) |
| Domain produksi | `supportfkip.unsil.ac.id/regulasi` | Tetap |
| Hook `pre-commit` | `docker compose exec` dari repo | `docker exec regulasi-php` (compose tidak di repo) |
| Git | Remote + PR | Belum ada remote: branch per fase, `merge --no-ff` ke `main`, tag lokal. Remote ditambahkan manusia kelak |
| Tipe kolom UUID | `CHAR(36)` | MariaDB 10.11 memetakan `uuid()`/`foreignUuid()`/`uuidMorphs()` ke tipe bawaan `UUID`; antarmuka PHP identik. Uji `UuidTest` menerima `uuid` atau `char(36)` |
| Berkas PDF referensi | — | PDF di `docs/referensi/` tidak di-commit |
| Klien Meilisearch (F7.2) | `meilisearch/meilisearch-php` + `http-interop/http-factory-guzzle` | `http-interop/http-factory-guzzle` **tidak dipasang**: bentrok dengan `guzzlehttp/psr7` 3.x; PSR-17 sudah disediakan `guzzlehttp/psr7` (ditemukan `php-http/discovery`) |
| Uji pencarian FULLTEXT (F7.3) | `RefreshDatabase` | InnoDB FULLTEXT hanya melihat baris ter-commit: `PencarianDatabaseTest` memakai `DatabaseTruncation` (+ truncate sesudah uji) |
| ID job ekstraksi (F7.1) | `EkstrakTeksTautan(int $tautanId)` | `string $tautanId` karena PK UUIDv7 |
| PDF uji ekstraksi (F7.1) | dompdf | `barryvdh/laravel-dompdf` dipasang sebagai dependensi **dev** dulu (laporan PDF F11 memindahkannya ke `require`) |
| Docker produksi (F12.3) | `docker/produksi/{Dockerfile,nginx.conf,entrypoint.sh}`, `compose.produksi.yaml`, `mysql:8.4` (`utf8mb4_0900_ai_ci`) | Pola sistem keuangan: `docker/Dockerfile` (target `app` & `web`, PHP **8.5**-FPM Alpine + `poppler-utils`), `docker/nginx/default.conf`, `docker/entrypoint.sh`, `docker/php/*.ini`, `docker-compose.prod.yml`, `docs/DEPLOY.md`. Basis data **`mariadb:10.11`** (`utf8mb4_unicode_ci`, `docker/mariadb/regulasi.cnf`: `innodb_ft_min_token_size=2`, `innodb_ft_enable_stopword=0`) karena seluruh uji & CI berjalan di MariaDB; Meilisearch dipin `v1.54` (sama dengan lokal) |
| Panjang token FULLTEXT cadangan | tetap ≥ 3 | `REGULASI_FT_MIN_TOKEN` (bawaan 3 = default MariaDB lokal; produksi 2 sesuai `innodb_ft_min_token_size`) |
