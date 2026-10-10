# Catatan Perubahan

Semua perubahan penting pada proyek ini dicatat di berkas ini.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/) dan proyek memakai [Semantic Versioning](https://semver.org/lang/id/).

## [Belum dirilis]

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
- Inisialisasi repositori: `.gitignore`, hook git (`commit-msg`, `pre-commit`), `CLAUDE.md`, salinan paket dokumen di `docs/vibecoding/`, dan referensi dasar hukum di `docs/referensi/`.
