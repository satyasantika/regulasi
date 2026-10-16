# Keputusan Arsitektural (ADR)

| No | Judul | Status | Tanggal |
|---|---|---|---|
| [0001](0001-lingkungan-dan-penyimpangan-standar.md) | Lingkungan pengembangan dan penyimpangan dari standar paket | Diterima | 2026-10-10 |
| [0002](0002-flag-mfa-aktif.md) | Flag `MFA_AKTIF` dan super-admin awal `fkip@unsil.ac.id` | Diterima | 2026-10-12 |
| [0003](0003-database-native-uuid-dan-php-8-5.md) | Database seragam `db_<app>`, UUID native MariaDB, produksi native, PHP 8.5 | Diterima | 2026-10-16 |
| [0004](0004-profil-ringan-di-kode.md) | Profil Ringan di kode: antrean database, Scout `database` + kamus istilah, tanpa Horizon/Redis/Meilisearch | Diusulkan (menunggu pemilik) | 2026-10-16 |

> Catatan 16 Oktober 2026: butir Redis, Horizon, dan Meilisearch pada ADR 0001 menggambarkan rilis lama; kode kini memakai
> Profil Ringan (STANDAR-TEKNIS §1b, ADR 0004).
>
> Pembaruan 16 Oktober 2026 (keputusan pemilik): standar kini PHP 8.5 + MariaDB 10.11+; baris PHP dan basis data pada
> ADR 0001 tidak lagi menyimpang dari standar dan ADR 0001 tetap berlaku (tidak dicabut). Bagian lain ADR 0001 yang
> terkait Redis/Meilisearch tetap menggambarkan kondisi saat ini sampai Fase B.
