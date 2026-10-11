# Deploy produksi — Regulasi FKIP Unsil

Pola `docker-apps` (STANDAR-TEKNIS §7, sama dengan sistem keuangan): container `app` (PHP-FPM, migrasi),
`web` (Nginx), `queue` (Horizon), `scheduler` (`schedule:work`), `mariadb` (10.11), `redis` (7, AOF), dan
`meilisearch` (v1.54, dipin). Aplikasi dipasang di subpath `https://supportfkip.unsil.ac.id/regulasi`.

> Basis data produksi memakai **MariaDB 10.11** (bukan `mysql:8.4` seperti paket): sama dengan lingkungan
> pengembangan dan CI tempat seluruh uji dijalankan (ADR 0001).

## Prasyarat server

- Docker Engine + Compose v2, akses `git` ke repositori `https://github.com/satyasantika/regulasi.git` (repo privat:
  *deploy key* baca-saja di server).
- HTTPS valid di server depan `supportfkip.unsil.ac.id` (sertifikat dikelola UPT TIK).
- Port host `WEB_PORT` (bawaan 8034) hanya dapat dijangkau reverse proxy depan. Meilisearch, MariaDB, dan Redis
  **tidak** membuka port host (KM-13).

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
# Isi: APP_KEY (php artisan key:generate --show), DB_PASSWORD, DB_ROOT_PASSWORD, REDIS_PASSWORD,
# MEILISEARCH_KEY (acak ≥ 16 byte, mis. openssl rand -hex 24), MAIL_*, TRUSTED_PROXIES,
# SEED_SUPERADMIN_EMAIL/SEED_SUPERADMIN_PASSWORD, REGULASI_ALAMAT (perlu verifikasi FKIP).
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
- Entrypoint menjalankan `optimize` (config/route/view/event), `filament:optimize`, `icons:cache`, dan (hanya `app`)
  `scout:sync-index-settings`. `storage:link` tidak dipakai: tidak ada berkas publik/unggahan.
- Aset Vite dibangun di image dengan `ASSET_URL=https://supportfkip.unsil.ac.id/regulasi` (build arg).
- Volume `tmp` (`storage/app/tmp`) dipakai bersama `app`, `queue`, `scheduler`: unduhan sementara ekstraksi (dihapus
  di `finally`) dan ekspor Excel (dibersihkan ≤ 24 jam oleh `berkas:bersihkan-tmp`).
- Super-admin wajib MFA: saat masuk pertama diminta memasang aplikasi autentikator; ganti kata sandi awal segera.

### Indeks pencarian (Meilisearch)

Indeks **dibangun ulang dari basis data**, tidak dicadangkan:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml exec app php artisan regulasi:reindeks          # pengaturan + impor ulang
docker compose --env-file .env.production -f docker-compose.prod.yml exec app php artisan regulasi:reindeks --hapus  # kosongkan dulu lalu impor
```

Bila Meilisearch mati, portal & API otomatis memakai cadangan FULLTEXT MariaDB (tanpa toleransi salah ketik);
`/api/health` tetap `ok` dengan `layanan.pencarian = gagal`.

### FULLTEXT MariaDB

`docker/mariadb/regulasi.cnf` memasang `innodb_ft_min_token_size=2` dan `innodb_ft_enable_stopword=0` sebelum
migrasi pertama sehingga singkatan dua huruf (PP, UU, SE) terindeks; `.env.production` memasang
`REGULASI_FT_MIN_TOKEN=2` agar query cadangan tidak membuang token itu. Bila basis data dibuat sebelum setelan ini,
bangun ulang indeks: `OPTIMIZE TABLE dokumen, ekstraksi_teks;` (dengan `innodb_optimize_fulltext_only=ON`) atau
drop/create indeks `ft_dokumen` & `ft_ekstraksi_isi` lewat migrasi baru.

## Pembaruan versi

```bash
cd regulasi
docker compose --env-file .env.production -f docker-compose.prod.yml exec backup /usr/local/bin/backup.sh   # cadangan pra-pembaruan
git fetch --tags && git checkout vX.Y.Z
export APP_VERSION=X.Y.Z
docker compose --env-file .env.production -f docker-compose.prod.yml build
docker compose --env-file .env.production -f docker-compose.prod.yml up -d          # app menjalankan migrate --force
docker compose --env-file .env.production -f docker-compose.prod.yml exec queue php artisan horizon:terminate   # pekerja memuat kode baru
curl -fsS http://127.0.0.1:8034/api/health                                           # "versi":"X.Y.Z"
```

Bila rilis mengubah pengaturan indeks atau `toSearchableArray`, jalankan `regulasi:reindeks` setelahnya.

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
2. cadangan MariaDB pra-migrasi lewat service `backup` (dilewati pada deploy pertama);
3. `build` image `regulasi-app`, `regulasi-web`, `regulasi-backup` bertag versi, lalu `up -d` (container `app`
   menjalankan `migrate --force`, `optimize`, `scout:sync-index-settings`);
4. health check `http://127.0.0.1:${WEB_PORT:-8034}/api/health` maks. 2 menit (gagal = job merah + 50 baris log `app`);
5. `horizon:terminate` agar pekerja memuat kode baru; hapus image lama (3 versi terakhir disimpan).

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
docker compose --env-file .env.production -f docker-compose.prod.yml exec queue php artisan horizon:terminate
```

Rollback hanya mengembalikan kode. Bila versi baru membawa migrasi yang tidak kompatibel ke belakang, pulihkan dump
cadangan pra-pembaruan (lihat "Cadangan dan pemulihan") — migrasi yang sudah dirilis tidak pernah diubah.

## Cadangan dan pemulihan

Yang dicadangkan **hanya basis data MariaDB**. Berkas naskah tidak ada di server (tautan ke Shared Drive
"Regulasi FKIP" dan sumber resmi pemerintah); berkas sementara `storage/app/tmp` tidak perlu dicadangkan; indeks
Meilisearch **tidak dicadangkan** karena dibangun ulang dari basis data (`regulasi:reindeks`).

- Service `backup` (`docker/backup/`, Alpine + `mariadb-client`) menjalankan `backup.sh` setiap hari **02:00 WIB**:
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
  docker/backup/pulihkan-uji.sh 2026-10-10 regulasi_pulih   # restore.sh → DB uji, lalu regulasi:reindeks --hapus & berkas:periksa-tautan
  ```

  Langsung di container: `… exec -e DB_DATABASE=regulasi_pulih backup /usr/local/bin/restore.sh <YYYY-MM-DD|berkas.sql.gz>`
  (memverifikasi `.sha256` dan `gzip -t`, membuat DB bila belum ada, mencetak jumlah dokumen).
- Pemulihan produksi (darurat): hentikan `app queue scheduler`, `restore.sh <tanggal> --paksa` dengan
  `DB_DATABASE` produksi, jalankan lagi layanan, lalu `regulasi:reindeks --hapus`.

### Uji pulih semester

Dilakukan TI fakultas setiap awal semester (Februari & Agustus) dan setelah perubahan besar infrastruktur.

1. Pilih cadangan terbaru (`ls ./backup/mariadb`), catat nama berkas & ukurannya.
2. Catat angka pembanding di produksi: `… exec app php artisan tinker --execute='echo App\Models\Dokumen::withTrashed()->count();'`
   (juga jumlah terbit, tautan, relasi, pengguna) dan hash berkas: `sha256sum` dump = isi `.sha256`.
3. Jalankan `docker/backup/pulihkan-uji.sh <tanggal> regulasi_pulih`.
4. Kriteria lulus:
   - checksum `.sha256` cocok dan `gzip -t` lolos;
   - jumlah dokumen, dokumen terbit, tautan berkas, relasi, riwayat status, dan pengguna **sama** dengan angka
     produksi pada waktu cadangan (selisih hanya data yang dibuat setelah jam cadangan);
   - hash isi hasil ekstraksi (`ekstraksi_teks.hash_sha256`) identik;
   - `regulasi:reindeks --hapus` selesai dan pencarian "permendikbudrstek 53" (salah ketik) menemukan dokumen di indeks
     `pulih_dokumen`;
   - `berkas:periksa-tautan` berjalan normal (ringkasan status tampil, tanpa galat).
5. Bersihkan: `DROP DATABASE regulasi_pulih;` dan hapus indeks Meilisearch `pulih_dokumen`.

Formulir catatan hasil (salin ke arsip TI fakultas):

| Tanggal uji | Berkas cadangan | Ukuran | sha256 cocok | Dokumen (prod/pulih) | Terbit (prod/pulih) | Tautan (prod/pulih) | Pencarian | Periksa tautan | Lama pulih | Penguji | Paraf |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 10 Okt 2026 (uji lokal pengembang, DB dev) | regulasi-2026-10-10-233411.sql.gz | 28 KB | ✓ | 35/35 | 35/35 | 0/0 | ✓ (Meilisearch, teratas Permendikbudristek 53/2023) | ✓ | < 1 menit | agen pengembang | — |
|  |  |  |  |  |  |  |  |  |  |  |  |

## Horizon, penjadwal & log

- Dasbor antrean: `https://supportfkip.unsil.ac.id/regulasi/horizon` (hanya **super-admin**, masuk panel dulu).
- Penjadwal: `… exec scheduler php artisan schedule:list` harus menampilkan seluruh jadwal (terapkan status 00:15,
  bersihkan tmp 00:45, ekstraksi ulang tiap jam, prune log buka 01:00, activitylog 01:30, periksa tautan Senin 06:30,
  ringkasan mingguan Senin 07:30, horizon:snapshot 5 menit).
- Log aplikasi: volume `logs` (`storage/logs`, kanal `daily`, level `warning`).
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

Uji asap lokal 10 Oktober 2026 (compose produksi lengkap, proyek `regulasi-ujiprod`, port 18934, lalu `down -v`):
seluruh layanan berjalan; `/api/health` → `{"status":"ok","versi":"1.0.0",…,"pencarian":"ok"}`; migrasi &
`scout:sync-index-settings` sukses di `app`; `db:seed --force` sukses; `innodb_ft_min_token_size=2`, stopword mati;
`horizon:status` = running; `schedule:list` lengkap; aset Filament/Vite berawalan
`https://supportfkip.unsil.ac.id/regulasi/`; `/.env` dan `/storage/*` → 404; tautan naskah
`https://peraturan.go.id/files/pp-no-44-tahun-2025.pdf` dicek (`dapat_diakses`) dan diekstraksi container `queue`
(`berhasil`, 92 halaman) dengan folder `storage/app/tmp/ekstraksi` kosong sesudahnya.
