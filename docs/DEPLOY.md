# Deploy produksi — Regulasi FKIP Unsil

Pola `docker-apps` Profil Ringan (STANDAR-TEKNIS §1b dan §7): container `app` (PHP-FPM 8.5, migrasi), `web` (Nginx),
`scheduler` (`schedule:work`, sekaligus menyalakan worker antrean `default` dan `berat` tiap menit dengan
`--stop-when-empty`), dan `backup` (klien `mariadb-dump`, cron 02:00 WIB). **MariaDB berjalan native di host**
(`host.docker.internal`, `extra_hosts: host.docker.internal:host-gateway`), sama dengan aplikasi produksi lain; compose
tidak memuat container database, Redis, Horizon, maupun Meilisearch. Cache, sesi, lock, dan antrean memakai tabel database;
pencarian memakai Scout driver `database` + FULLTEXT + kamus istilah. Aplikasi dipasang di subpath
`https://supportfkip.unsil.ac.id/regulasi`. Nama database `db_regulasi` (uji pulih: `db_regulasi_pulih`).

> **Server yang masih menjalankan rilis lama** (container `mariadb`, `redis`, `queue`/Horizon, `meilisearch`, database
> `regulasi`): ikuti bagian **"Peralihan dari rilis lama"** di bawah SEBELUM memberi tag rilis baru. Jangan men-deploy
> kode ini dengan `.env.production` lama.

## Prasyarat server

- MariaDB 10.11+ native di host (tipe `UUID` native butuh ≥ 10.7), `bind-address` yang mencakup gateway Docker, database
  `db_regulasi` (`utf8mb4` / `utf8mb4_unicode_ci`) dan pengguna khusus aplikasi dengan hak atas database itu. Pembuatan
  database dan pengguna dilakukan pemilik server (STANDAR-TEKNIS §2.2), bukan skrip repo.
- Docker Engine + Compose v2, akses `git` ke repositori `https://github.com/satyasantika/regulasi.git` (repo privat:
  *deploy key* baca-saja di server).
- HTTPS valid di server depan `supportfkip.unsil.ac.id` (sertifikat dikelola UPT TIK).
- Port host `WEB_PORT` (bawaan 8034) hanya dapat dijangkau reverse proxy depan. MariaDB host tidak boleh
  terjangkau dari luar server (KM-13).

## Reverse proxy depan (`supportfkip.unsil.ac.id`)

```nginx
location = /regulasi { return 301 /regulasi/; }
location /regulasi/ {
    proxy_pass http://127.0.0.1:8034/;          # garis miring akhir = awalan /regulasi dipotong
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Host $host;
    proxy_set_header X-Forwarded-Prefix /regulasi;
    client_max_body_size 30m;                    # tidak ada unggah dokumen; hanya impor Excel/CSV
}
```

- `TRUSTED_PROXIES` di `.env.production` diisi IP proxy ini (bukan `*`) agar `X-Forwarded-*` dipercaya.
- HTTPS diakhiri di proxy depan; aplikasi memaksa skema `https` dari `APP_URL` dan mengirim HSTS saat
  `APP_ENV=production`. Cookie `regulasi_session` ber-path `/regulasi`, `Secure`, `HttpOnly`, `SameSite=Lax`.
- **robots.txt gateway**: crawler hanya membaca `robots.txt` di akar domain. Salin baris dari
  `public/robots.txt` ke robots.txt gateway `supportfkip.unsil.ac.id` (Disallow `/regulasi/admin`, `/regulasi/akun`,
  `/regulasi/d/`, `/regulasi/livewire`; `Sitemap: https://supportfkip.unsil.ac.id/regulasi/sitemap.xml`).

## Deploy pertama

```bash
git clone https://github.com/satyasantika/regulasi.git ~/docker-apps/regulasi && cd ~/docker-apps/regulasi
git checkout v1.1.0
cp .env.production.example .env.production
# Isi: APP_KEY (php artisan key:generate --show), DB_USERNAME dan DB_PASSWORD (database host), MAIL_*,
# TRUSTED_PROXIES, REGULASI_FT_MIN_TOKEN (= innodb_ft_min_token_size server), REGULASI_SUPERADMIN_PASSWORD (super-admin fkip@unsil.ac.id), MFA_AKTIF=true, REGULASI_ALAMAT (perlu verifikasi FKIP).
export APP_VERSION=1.1.0
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
docker compose --env-file .env.production -f docker-compose.prod.yml exec app php artisan db:seed --force
curl -fsS http://127.0.0.1:8034/api/health        # {"status":"ok","versi":"1.0.0",...}
```

- `db:seed` di produksi menjalankan peran & izin, master (jenis dokumen, badan penetap, kategori sistem, tag), dan
  super-admin dari env. **Dokumen contoh tidak** ikut; bila diinginkan (G-07):
  `… exec app php artisan db:seed --class=DokumenContohSeeder --force` setelah URL naskah diisi.
- Migrasi berjalan otomatis di container `app` (`RUN_MIGRATIONS=true`) setelah basis data siap; `queue` dan
  `scheduler` tidak bermigrasi.
- Entrypoint menjalankan `optimize` (config/route/view/event), `filament:optimize`, `icons:cache`, dan (hanya `app`,
  setelah `migrate`) `regulasi:bangun-kamus` (tidak fatal bila gagal). `storage:link` tidak dipakai: tidak ada berkas publik/unggahan.
- Aset Vite dibangun di image dengan `ASSET_URL=https://supportfkip.unsil.ac.id/regulasi` (build arg).
- Volume `tmp` (`storage/app/tmp`) dipakai bersama `app` dan `scheduler` (tempat worker berjalan): unduhan sementara ekstraksi (dihapus
  di `finally`) dan ekspor Excel (dibersihkan ≤ 24 jam oleh `berkas:bersihkan-tmp`).
- Super-admin `fkip@unsil.ac.id` (dari `REGULASI_SUPERADMIN_PASSWORD`) wajib MFA selama `MFA_AKTIF=true` dan
  ditandai wajib ganti sandi: saat masuk pertama diminta memasang aplikasi autentikator lalu mengganti kata sandi awal
  (ADR 0002).

### Pencarian: kamus istilah dan FULLTEXT MariaDB

Tidak ada indeks pencarian terpisah: FULLTEXT ada di tabel `dokumen` dan `ekstraksi_teks`, sehingga ikut tercadangkan.
Yang dibangun ulang dari basis data adalah **kamus istilah** (koreksi salah ketik dan sinonim):

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml exec app php artisan regulasi:bangun-kamus          # sinkron
docker compose --env-file .env.production -f docker-compose.prod.yml exec app php artisan regulasi:bangun-kamus --antre  # lewat antrean berat
```

Kamus dibangun otomatis oleh entrypoint (setelah migrasi), mingguan (Minggu 02:00), dan lewat antrean `berat` saat dokumen
terbit/diubah/diarsipkan atau teks tautan diekstraksi. Bila kamus kosong, pencarian tetap berjalan tanpa koreksi.

**Token minimum dan stopword FULLTEXT (keputusan pemilik server).** Singkatan dua huruf (PP, UU, SE) hanya terindeks bila
server MariaDB disetel `innodb_ft_min_token_size=2` (bawaan 3) dan, bila diinginkan, `innodb_ft_enable_stopword=0`. Variabel
itu **global per server** (memengaruhi semua aplikasi di MariaDB yang sama) dan hanya berlaku untuk indeks yang dibangun
sesudahnya. Repo ini TIDAK mengubah `my.cnf` host. Aturan:

- `REGULASI_FT_MIN_TOKEN` di `.env.production` harus sama dengan `innodb_ft_min_token_size` server (contoh env memakai 3,
  bawaan MariaDB). Nilai yang lebih kecil dari setelan server membuat token pendek dicari tetapi tidak pernah cocok.
- Bila pemilik server memutuskan menyetel 2 (rilis lama memakainya lewat `docker/mariadb/regulasi.cnf`: `innodb_ft_min_token_size = 2`,
  `innodb_ft_enable_stopword = 0`), setel SEBELUM memulihkan dump ke `db_regulasi` atau bangun ulang indeks sesudahnya
  (`ALTER TABLE dokumen DROP INDEX ft_dokumen, ADD FULLTEXT ft_dokumen (judul, sebutan_singkat, abstrak)`; hal yang sama
  untuk `ft_ekstraksi_isi` pada `ekstraksi_teks`, atau `OPTIMIZE TABLE` dengan `innodb_optimize_fulltext_only=ON`).

## Pembaruan versi

```bash
cd regulasi
docker compose --env-file .env.production -f docker-compose.prod.yml exec backup /usr/local/bin/backup.sh   # cadangan pra-pembaruan
git fetch --tags && git checkout vX.Y.Z
export APP_VERSION=X.Y.Z
docker compose --env-file .env.production -f docker-compose.prod.yml build
docker compose --env-file .env.production -f docker-compose.prod.yml up -d          # app menjalankan migrate --force
docker compose --env-file .env.production -f docker-compose.prod.yml exec app php artisan queue:restart   # worker memuat kode baru
curl -fsS http://127.0.0.1:8034/api/health                                           # "versi":"X.Y.Z"
```

Bila rilis mengubah teks judul/singkatan secara massal, jalankan `regulasi:bangun-kamus` setelahnya.

## Auto-pull dari GitHub (CD)

Rilis produksi berjalan otomatis dari GitHub Actions: push tag `vX.Y.Z` → workflow `.github/workflows/deploy.yml`
menjalankan uji penuh (job `uji` = `ci.yml`: shellcheck, Pint, PHPStan, Pest, Pest ekstraksi) → job `deploy` masuk ke
server lewat SSH dan **menarik** tag itu:

```bash
cd ~/docker-apps/regulasi
git fetch --tags --prune --force origin && git fetch origin main
git checkout --detach "refs/tags/$TAG"
bash scripts/deploy.sh
```

`scripts/deploy.sh` (ikut di repo, dapat dijalankan manual di server):

1. menolak bila HEAD bukan tag `vX.Y.Z`, tag tidak berada di riwayat `origin/main`, atau working tree kotor;
2. cadangan MariaDB (di host) pra-migrasi lewat service `backup`, hasil di `./backup/mariadb` (dilewati pada deploy pertama);
3. `build` image `regulasi-app`, `regulasi-web`, `regulasi-backup` bertag versi, lalu `up -d` (container `app`
   menjalankan `migrate --force`, `optimize`, `regulasi:bangun-kamus`);
4. health check `http://127.0.0.1:${WEB_PORT:-8034}/api/health` maks. 2 menit (gagal = job merah + 50 baris log `app`);
5. `queue:restart` agar worker memuat kode baru; hapus image lama (3 versi terakhir disimpan).

Push biasa ke `main` **tidak** menggelar apa pun; hanya tag rilis.

| GitHub Secret | Isi |
|---|---|
| `SERVER_HOST` | Nama host/IP server Docker FKIP (perlu verifikasi dengan UPT TIK) |
| `SERVER_USER` | Akun deploy di server (anggota grup `docker`) |
| `SSH_PRIVATE_KEY` | Kunci privat khusus deploy (ed25519); kunci publiknya di `~/.ssh/authorized_keys` akun deploy |
| `SERVER_PORT` | Opsional, port SSH bila bukan 22 |

Persiapan server sekali:

- `git clone https://github.com/satyasantika/regulasi.git ~/docker-apps/regulasi`; repo privat → tambahkan
  *deploy key* baca-saja (Settings → Deploy keys) agar `git fetch` berjalan tanpa kata sandi.
- `.env.production` di `~/docker-apps/regulasi` (tidak di-commit), lalu deploy pertama (bagian di atas).
- Rahasia hanya di GitHub Secrets dan `.env.production` server; tidak ada host/kunci di repo.

Deploy ulang/rollback dari GitHub: *Actions → Deploy regulasi → Run workflow* dengan tag tujuan. Rollback cepat di
server tanpa build ulang: `bash scripts/deploy.sh --rollback X.Y.Z` (`--list` menampilkan versi yang image-nya masih
ada). Rollback hanya mengembalikan kode, bukan skema — lihat bagian berikut.

## Rollback ke tag sebelumnya

```bash
git checkout v<sebelumnya>
export APP_VERSION=<sebelumnya>
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build   # image lama dipakai ulang bila masih ada
docker compose --env-file .env.production -f docker-compose.prod.yml exec app php artisan queue:restart
```

Rollback hanya mengembalikan kode. Bila versi baru membawa migrasi yang tidak kompatibel ke belakang, pulihkan dump
cadangan pra-pembaruan (lihat "Cadangan dan pemulihan") — migrasi yang sudah dirilis tidak pernah diubah.

## Cadangan dan pemulihan

Yang dicadangkan **hanya basis data MariaDB** (`db_regulasi` di host; kamus pencarian ikut sebagai tabel). Berkas naskah
tidak ada di server (tautan ke Shared Drive "Regulasi FKIP" dan sumber resmi pemerintah); berkas sementara
`storage/app/tmp` tidak perlu dicadangkan.

- Service `backup` (`docker/backup/`, Alpine + `mariadb-client`; hanya klien, menghubungi MariaDB host dengan
  `DB_USERNAME`/`DB_PASSWORD` aplikasi, yang perlu hak `SELECT, LOCK TABLES, SHOW VIEW, TRIGGER, EVENT` dan membaca
  rutin pada `db_regulasi`) menjalankan `backup.sh` setiap hari **02:00 WIB**:
  `mariadb-dump --single-transaction --routines --triggers --events` → gzip →
  `./backup/mariadb/<YYYY-MM-DD>/regulasi-<YYYY-MM-DD-HHMMSS>.sql.gz` + `.sha256`, log `./backup/mariadb/cadangan.log`,
  hapus folder bertanggal lebih tua dari `RETENSI_HARI` (bawaan 30), keluar non-zero bila gagal.
- Cadangan manual: `docker compose --env-file .env.production -f docker-compose.prod.yml exec backup /usr/local/bin/backup.sh`.
- Salin `./backup/mariadb` ke luar server (mis. rsync harian ke penyimpanan TI fakultas); folder ini di-`.gitignore`.
- Alternatif tanpa service `backup` — cron host (`crontab -e` akun deploy):

  ```cron
  # Cadangan harian MariaDB Regulasi 02:00 WIB
  0 2 * * * cd /srv/docker-apps/regulasi && docker compose --env-file .env.production -f docker-compose.prod.yml run --rm -T backup /usr/local/bin/backup.sh >> /var/log/regulasi-cadangan.log 2>&1
  ```

- Pulihkan ke lingkungan **uji** (menolak `APP_ENV=production` tanpa `--paksa`):

  ```bash
  docker/backup/pulihkan-uji.sh 2026-10-10 db_regulasi_pulih   # restore.sh → DB uji, lalu regulasi:bangun-kamus & berkas:periksa-tautan
  ```

  Langsung di container: `… exec -e DB_DATABASE=db_regulasi_pulih backup /usr/local/bin/restore.sh <YYYY-MM-DD|berkas.sql.gz>`
  (memverifikasi `.sha256` dan `gzip -t`, membuat DB bila belum ada, mencetak jumlah dokumen).
- Pemulihan produksi (darurat): hentikan `app scheduler`, `restore.sh <tanggal> --paksa` dengan
  `DB_DATABASE` produksi, jalankan lagi layanan, lalu `regulasi:bangun-kamus`.

### Uji pulih semester

Dilakukan TI fakultas setiap awal semester (Februari & Agustus) dan setelah perubahan besar infrastruktur.

1. Pilih cadangan terbaru (`ls ./backup/mariadb`), catat nama berkas & ukurannya.
2. Catat angka pembanding di produksi: `… exec app php artisan tinker --execute='echo App\Models\Dokumen::withTrashed()->count();'`
   (juga jumlah terbit, tautan, relasi, pengguna) dan hash berkas: `sha256sum` dump = isi `.sha256`.
3. Jalankan `docker/backup/pulihkan-uji.sh <tanggal> db_regulasi_pulih`.
4. Kriteria lulus:
   - checksum `.sha256` cocok dan `gzip -t` lolos;
   - jumlah dokumen, dokumen terbit, tautan berkas, relasi, riwayat status, dan pengguna **sama** dengan angka
     produksi pada waktu cadangan (selisih hanya data yang dibuat setelah jam cadangan);
   - hash isi hasil ekstraksi (`ekstraksi_teks.hash_sha256`) identik;
   - `regulasi:bangun-kamus` selesai dan pencarian "permendikbudrstek 53" (salah ketik) menemukan dokumen (dengan
     pesan "Menampilkan hasil untuk …");
   - `berkas:periksa-tautan` berjalan normal (ringkasan status tampil, tanpa galat).
5. Bersihkan: hapus database uji `db_regulasi_pulih` (oleh pemilik server).

Formulir catatan hasil (salin ke arsip TI fakultas):

| Tanggal uji | Berkas cadangan | Ukuran | sha256 cocok | Dokumen (prod/pulih) | Terbit (prod/pulih) | Tautan (prod/pulih) | Pencarian | Periksa tautan | Lama pulih | Penguji | Paraf |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 10 Okt 2026 (uji lokal pengembang, DB dev) | regulasi-2026-10-10-233411.sql.gz | 28 KB | ✓ | 35/35 | 35/35 | 0/0 | ✓ (Meilisearch, teratas Permendikbudristek 53/2023) | ✓ | < 1 menit | agen pengembang | — |
|  |  |  |  |  |  |  |  |  |  |  |  |

## Antrean, penjadwal & log

- Tidak ada dasbor antrean. Pantau lewat `GET /api/health` (`job_tertunda` > 10 menit, `job_gagal_24j`, `detak_penjadwal`;
  503 hanya bila basis data gagal) dan perintah `… exec app php artisan queue:failed` / `queue:retry <id>`.
- Penjadwal: `… exec scheduler php artisan schedule:list` harus menampilkan seluruh jadwal (terapkan status 00:15,
  bersihkan tmp 00:45, ekstraksi ulang tiap jam, prune log buka 01:00, activitylog 01:30, periksa tautan Senin 06:30,
  ringkasan mingguan Senin 07:30, bangun kamus Minggu 02:00, dua `queue:work` tiap menit, prune job gagal/batch 03:00).
- Log aplikasi: volume `logs` (`storage/logs`, kanal `daily`, level `warning`); log Docker dirotasi (`json-file`, 10 MB × 3).
- Batas memori awal (PERKIRAAN, wajib dibuktikan dengan `docker stats`, STANDAR-TEKNIS §7.1): app 512m, web 64m,
  scheduler 768m (memuat worker), backup 128m.
- Status layanan: `… ps` — `web` sehat bila `/api/health` = `ok`; `app` sehat bila ping PHP-FPM membalas `pong`.
- Pantau `GET https://supportfkip.unsil.ac.id/regulasi/api/health` dari uptime monitor kampus (G-12).

## Uji image secara lokal

```bash
docker build -f docker/Dockerfile --target app -t regulasi-app:uji .
docker build -f docker/Dockerfile --target web -t regulasi-web:uji .
docker run --rm --entrypoint pdftotext regulasi-app:uji -v      # poppler tersedia
```

Workflow `.github/workflows/docker.yml` membangun kedua image (tanpa push) pada setiap tag `v*`; workflow
`deploy.yml` menggelarnya ke server (bagian "Auto-pull dari GitHub").

Uji asap lokal 10 Oktober 2026 (rilis 1.0.0/1.1.0, kondisi lama dengan Horizon, Redis, dan Meilisearch) tidak lagi
mewakili susunan ini. Susunan Profil Ringan diverifikasi 16 Oktober 2026 hanya sebatas: `docker compose config -q`
lolos, build target `base` menghasilkan PHP 8.5.11 tanpa ekstensi `redis` dengan `pdftotext`, dan seluruh uji Pest hijau.
**Belum diverifikasi:** uji asap compose penuh terhadap MariaDB host, `docker stats`, dan data produksi (lihat bagian di bawah).

## Peralihan dari rilis lama (container MariaDB + Redis + Horizon + Meilisearch)

Berlaku untuk server yang menjalankan v1.1.0 atau lebih lama: database `regulasi` di container `mariadb`, antrean di Redis
(Horizon), pencarian di Meilisearch. Perubahan yang terjadi: database pindah ke MariaDB host dan berganti nama
`regulasi` → `db_regulasi` (MariaDB tidak punya rename database: **dump → restore**), sesi/cache/antrean pindah ke tabel
database, Meilisearch diganti kamus istilah.

> **Urutan penting.** Deploy dipicu oleh **tag** `vX.Y.Z` (`deploy.yml`), bukan push ke `main`; merge ke `main` saja tidak men-deploy.
> Namun begitu tag baru didorong, server memakai `.env.production` yang ada. Karena itu `.env.production` harus sudah diubah
> (langkah 7) SEBELUM tag/merge-rilis dibuat. Job tertunda di Redis TIDAK berpindah; semua sesi pengguna berakhir.

| # | Langkah | Bukti |
|---|---|---|
| 0 | Pemilik server: siapkan `db_regulasi` + pengguna di MariaDB host (`bind-address` mencakup gateway Docker); putuskan token minimum FULLTEXT host (lihat "Pencarian: kamus istilah dan FULLTEXT"); tentukan nama pengguna DB | Keluaran `SHOW GRANTS` |
| 1 | Jadwalkan di luar jam kerja dan umumkan: sesi berakhir, pengguna masuk ulang; pencarian tanpa koreksi sampai kamus terbangun | Pengumuman |
| 2 | Cadangan penuh rilis lama: `… exec backup /usr/local/bin/backup.sh`; salin `./backup/mariadb` ke luar server; simpan `.env.production` sebagai `.env.production.v1.1.0` dan salinan `APP_KEY` di luar server | Berkas `.sql.gz` + `.sha256` |
| 3 | **Kuras antrean Redis**: di `/regulasi/horizon` tunggu job tertunda dan berjalan = 0, lalu `… exec queue php artisan horizon:terminate`. Job yang tersisa di Redis hilang | Tangkapan layar Horizon kosong |
| 4 | Hentikan penulisan: `… stop web scheduler queue` (biarkan `mariadb` dan `backup` menyala); ambil dump akhir `… exec backup /usr/local/bin/backup.sh` | Dump akhir + `.sha256` |
| 5 | Pulihkan ke MariaDB host: `sha256sum -c` lalu `gunzip -c regulasi-<stempel>.sql.gz \| mariadb -h 127.0.0.1 -u <pengguna> -p db_regulasi` (dump tidak memuat nama database, jadi aman dipulihkan ke `db_regulasi`; indeks FULLTEXT dibangun ulang dengan setelan token host) | Log restore |
| 6 | Cocokkan data lama vs baru: `SELECT COUNT(*)` pada `dokumen`, `tautan_berkas`, `ekstraksi_teks`, `dokumen_relasi`, `riwayat_status_dokumen`, `users`, `log_buka_tautan`, `activity_log`; tipe kolom `id` = `uuid`; `SHOW INDEX FROM dokumen` memuat `ft_dokumen` | Tabel perbandingan |
| 7 | Ubah `.env.production` di server: `DB_HOST=host.docker.internal`, `DB_DATABASE=db_regulasi`, `DB_USERNAME`/`DB_PASSWORD` pengguna host; `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SCOUT_DRIVER=database`, `APP_KODE=regulasi`, `REGULASI_FT_MIN_TOKEN` sesuai host; HAPUS `DB_ROOT_PASSWORD`, `REDIS_*`, `HORIZON_PREFIX`, `MEILISEARCH_*` (lihat `.env.production.example`) | `diff` terhadap `.env.production.example` |
| 8 | Hapus container cadangan lama agar skrip rilis tidak memanggilnya: `… rm -sf backup`. Lalu beri tag rilis baru (mis. `v1.2.0`); `deploy.sh` men-`up -d --remove-orphans` sehingga `queue`, `mariadb`, `redis`, `meilisearch` lama hilang (volume tetap ada); `app` menjalankan `migrate --force` (tabel kamus baru, penghapusan izin `horizon.akses`) dan `regulasi:bangun-kamus` | `docker compose ps`, log `app` |
| 9 | Verifikasi: `/api/health` → `layanan.database: ok`, `detak_penjadwal: ok` (≤ 2 menit); `schedule:list` memuat dua `queue:work`; cari "permendikbudrstek 53" (muncul "Menampilkan hasil untuk …") dan "181/PMK"; kirim notifikasi uji; ekstraksi satu tautan; `docker stats` selama jam sibuk | Keluaran perintah |
| 10 | Setelah ≥ 7 hari tanpa masalah: hapus volume lama (`docker volume ls \| grep regulasi`: `mariadb`, `redis`, `meilisearch`) dan database `regulasi` lama | — |

**Pembatalan (sebelum langkah 10):** hentikan rilis baru, `git checkout v1.1.0`, kembalikan `.env.production.v1.1.0` menjadi
`.env.production`, lalu `docker compose --env-file .env.production -f docker-compose.prod.yml up -d --remove-orphans`.
Volume `mariadb` lama masih memuat data per langkah 4. Data yang ditulis ke `db_regulasi` setelah peralihan TIDAK ikut
kembali kecuali di-dump dan diimpor manual ke database lama; job antrean yang sempat masuk ke tabel `jobs` hilang. Indeks
Meilisearch lama dibangun ulang oleh `regulasi:reindeks` pada v1.1.0 (perintah itu tidak ada lagi di rilis baru).

**Di luar repo ini:** `/home/satya/code/docker-compose.yml` (lingkungan pengembangan) masih memuat service
`regulasi-horizon`, `regulasi-meilisearch`, dan volume `regulasi-meili-data`; pemilik perlu menghapusnya (repo ini tidak
mengubah berkas itu). Redis bersama tetap dipakai aplikasi lain.
