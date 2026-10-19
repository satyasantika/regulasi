# Kinerja — Regulasi FKIP Unsil

Catatan pengukuran F12.2 (04-PROMPT-BERTAHAP). Target: beranda (cache hangat) < 300 ms dan `/dokumen` berfilter
< 500 ms pada 5.000 dokumen di lingkungan lokal.

> **Catatan Profil Ringan (menunggu pemilik):** pengukuran di bawah dilakukan pada rilis lama (10 Oktober 2026, Meilisearch +
> Redis). Kode kini memakai pencarian Scout driver `database` + FULLTEXT + kamus istilah; angka
> "Cadangan FULLTEXT" di bawah adalah rujukan terdekat dan **belum diukur ulang** pada kode baru (kamus menambah satu
> kueri `istilah_pencarian` yang di-cache 60 menit, dan satu kueri ulang bila hasil kosong). Angka target tidak diubah di dokumen ini. Agen sebelumnya
> mengubah target kinerja pencarian dari < 200 ms menjadi < 500 ms (terlihat di PRD); perubahan itu **menunggu
> persetujuan pemilik** dan belum boleh dianggap final. Ukur ulang pada kode Profil Ringan (angka di atas dari rilis lama).

## Lingkungan pengukuran

- Tanggal: 10 Oktober 2026, mesin pengembang (WSL2), container `regulasi-php` (PHP 8.5-FPM, OPcache CLI mati,
  `APP_ENV=local`, `APP_DEBUG=true`, Xdebug tidak aktif), MariaDB 10.11 di host, Redis & Meilisearch v1 bersama.
- Data: `PerfSeeder` (hanya `APP_ENV=local`) di basis data **terpisah** `db_regulasi_perf` agar data pengembangan
  tidak tercampur: 5.000 dokumen (±90% terbit, ±85% publik, 1–2 kategori, tahun 1990–2026) + 13.000 tautan
  (5.000 naskah utama + 8.000 lampiran). Meilisearch memakai indeks berawalan `perf_regulasi_` (4.486 dokumen terbit).
- Waktu = waktu kernel HTTP Laravel (tanpa Nginx/jaringan), median 7 kali (5 kali untuk cache dingin & admin),
  diukur dengan skrip PHP yang memanggil `Kernel::handle()`; kolom *Query* = jumlah query SQL pada permintaan terakhir.

## Hasil (sesudah optimasi F12.2)

### Mesin Meilisearch (konfigurasi normal)

| Skenario | Median (ms) | Maks (ms) | Query |
|---|---|---|---|
| Beranda, cache dingin | 78 | 124 | 8 |
| Beranda, cache hangat | 15 | 19 | 0 |
| `/dokumen` tanpa filter | 44 | 104 | 4 |
| `/dokumen` 3 filter (kategori + status + jenis) | 52 | 57 | 4 |
| `/dokumen` 3 filter + kata kunci | 33 | 36 | 1 |
| Detail dokumen | 20 | 27 | 8 |
| `GET /api/v1/dokumen` | 28 | 52 | 6 |
| `GET /api/v1/dokumen` 2 filter | 39 | 43 | 6 |
| Tabel admin `/admin/dokumen` (super-admin) | 167 | 269 | 9 |

### Cadangan FULLTEXT MariaDB (`SCOUT_DRIVER=null` / Meilisearch mati)

| Skenario | Median (ms) | Maks (ms) | Query |
|---|---|---|---|
| Beranda, cache dingin | 95 | 126 | 8 |
| Beranda, cache hangat | 15 | 17 | 0 |
| `/dokumen` tanpa filter | 106 | 117 | 10 |
| `/dokumen` 3 filter | 81 | 86 | 10 |
| `/dokumen` 3 filter + kata kunci | 52 | 55 | 6 |
| Detail dokumen | 18 | 34 | 8 |
| `GET /api/v1/dokumen` | 92 | 117 | 12 |
| `GET /api/v1/dokumen` 2 filter | 67 | 70 | 12 |
| Tabel admin `/admin/dokumen` | 166 | 419 | 9 |

Kedua target terpenuhi dengan selisih besar. Sebelum optimasi angka-angkanya setara (selisih di bawah derau
pengukuran); perubahan F12.2 terutama menurunkan kerja query daftar (lihat EXPLAIN) yang baru terasa ketika koleksi
tumbuh.

## Perubahan F12.2

1. **Indeks `idx_dokumen_publik_terbaru (status_publikasi, visibilitas, dipublikasikan_pada)`** (migrasi
   `2026_10_18_100001`). Urutan bawaan cadangan pencarian kini `dipublikasikan_pada DESC, id DESC` (sebelumnya
   `id ASC` sebagai pemutus seri) sehingga `LIMIT 20` dibaca langsung dari indeks:

   | Query daftar publik tanpa filter | Indeks | Extra | Waktu |
   |---|---|---|---|
   | sebelum (`…, id ASC`) | `idx_dokumen_publik` | Using index condition; Using where; **Using filesort** | 11,9 ms |
   | sesudah (`…, id DESC`) | `idx_dokumen_publik_terbaru` | Using where | 1,3 ms |

2. **Tabel admin dokumen** memuat kolom seperlunya (`DokumenResource::KOLOM_TABEL`, tanpa abstrak/catatan), relasi
   `jenisDokumen:id,nama` & `kategoriSistem:id,nama`, dan `withCount` jumlah lampiran (kolom "Lampiran"). Aksi
   hapus/pulihkan pada baris tabel memuat ulang model lengkap agar log aktivitas & indeks pencarian tidak menerima
   atribut kosong.
3. `Model::preventLazyLoading()` aktif di luar produksi tanpa pengecualian; tidak ada pelanggaran lazy loading pada
   rangkaian uji maupun pengukuran.
4. Larastan level 6 bersih **tanpa baseline** (`phpstan.neon` tidak memuat `ignoreErrors`/baseline).
5. `php artisan optimize`, `filament:optimize`, dan `icons:cache` berjalan tanpa galat (tidak ada closure di
   config/route); dijalankan otomatis oleh `docker/entrypoint.sh` di produksi.

## EXPLAIN yang diperiksa

- Daftar & faset dengan filter kategori + status + jenis: `kategori_sistem` (const, `kategori_sistem_kode_unique`),
  `jenis_dokumen` (const), `dokumen` (ref `idx_dokumen_jenis_tahun`), `dokumen_kategori_sistem` (eq_ref PRIMARY).
  Faset per dimensi 2–12 ms.
- Pivot `dokumen_kategori_sistem`: PRIMARY `(dokumen_id, kategori_sistem_id)` + indeks `kategori_sistem_id`
  (InnoDB menambahkan kolom PK ke indeks sekunder, sehingga indeks ini efektif `(kategori_sistem_id, dokumen_id)`);
  indeks komposit tambahan tidak diperlukan.
- Kata kunci: `ft_dokumen` dan `ft_ekstraksi_isi` (fulltext) dipakai; tanpa pemindaian penuh pada tabel teks.
- `COUNT(*)` daftar tanpa filter memindai tabel (±90% baris terbit) — wajar dan murah (±15 ms pada 5.000 baris).

## Cara mengulang

```bash
# 1. Basis data terpisah (sekali)
docker exec -e HOME=/tmp regulasi-php php artisan tinker --execute='DB::statement("CREATE DATABASE IF NOT EXISTS db_regulasi_perf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");'

# 2. Variabel lingkungan perf: DB terpisah, prefiks cache, antrean sinkron
PE="-e HOME=/tmp -e DB_DATABASE=db_regulasi_perf -e CACHE_PREFIX=regulasi_perf: -e QUEUE_CONNECTION=sync -e SESSION_DRIVER=array"
docker exec $PE regulasi-php php artisan migrate:fresh --force --seeder=PerfSeeder
docker exec $PE regulasi-php php artisan regulasi:bangun-kamus

# 3. Ukur (skrip memanggil Kernel::handle() untuk tiap URI)
docker exec -i $PE -w /var/www/html regulasi-php php < ukur-kinerja.php

# 4. Bersihkan
docker exec -e HOME=/tmp regulasi-php php artisan tinker --execute='DB::statement("DROP DATABASE db_regulasi_perf");'
```

Kerangka skrip `ukur-kinerja.php` (tidak di-commit; jalankan dari stdin):

```php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
// untuk tiap URI: $req = Illuminate\Http\Request::create('http://localhost/regulasi'.$uri, 'GET',
//   server: ['SCRIPT_NAME' => '/regulasi/index.php', 'SCRIPT_FILENAME' => '/var/www/html/public/index.php']);
// $t = hrtime(true); $res = $kernel->handle($req); $ms = (hrtime(true) - $t) / 1e6; $kernel->terminate($req, $res);
// cache dingin: App\Support\CacheRegulasi::lupakanPublik() sebelum permintaan; admin: $app['auth']->guard('web')->setUser($admin)
// (super-admin dengan rahasia MFA tersimpan); API: header Authorization: Bearer <token dokumen:baca>.
```

## Catatan produksi

Angka produksi diharapkan lebih baik (OPcache aktif dengan `validate_timestamps=0`, `APP_DEBUG=false`, config/route/
view/event ter-cache). Ukur ulang setelah go-live dengan data nyata; pantau `/api/health` dan log lambat MariaDB.
