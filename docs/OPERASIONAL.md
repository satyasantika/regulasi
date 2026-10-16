# Butir operasional yang memerlukan tindakan atau verifikasi manusia

Daftar ini menghimpun hal-hal yang **tidak dapat diselesaikan dari kode** (fakta regulasi, keputusan kebijakan,
infrastruktur kampus) yang tercatat sepanjang F0–F12. Centang setelah ditangani; cantumkan nama & tanggal.
> **Profil Ringan berlaku setelah Fase B; kondisi saat ini:** butir yang menyebut Horizon, Redis, atau Meilisearch
> (mis. G-10, pemindaian port, token FULLTEXT) mengacu pada kode berjalan dan akan disesuaikan setelah Fase B
> (STANDAR-TEKNIS §1b; pada target, FULLTEXT MariaDB menjadi mesin utama sehingga butir token FULLTEXT berlaku penuh).

Rujukan langkah teknis: `docs/DEPLOY.md`; skenario uji: `docs/uat/hasil-uat.md`; go-live: 05-UJI-PENERIMAAN §4.

## 1. Data seed & fakta regulasi

| ✓ | Butir | Tindakan | Penanggung jawab |
|---|---|---|---|
| ☐ | **URL naskah dokumen contoh kosong** | Unggah PDF ke Shared Drive "Regulasi FKIP" (bagikan *Siapa saja yang memiliki link*), lalu isi `database/seeders/data/tautan-contoh.local.php` (di-`.gitignore`) berbentuk `return ['<kunci entri>' => 'https://drive.google.com/file/d/…/view', …];` — kunci = `kunci` di `database/seeders/data/dokumen-contoh.php` (tercetak di peringatan seeder, mis. `iapsk3-sarjana-buku-3-unggul`). **Seeder melewati dokumen yang sudah ada**, jadi: lokal `php artisan migrate:fresh --seed`; produksi isi berkas ini **sebelum** `db:seed --class=DokumenContohSeeder` pertama (G-07) — bila sudah terlanjur, tempel tautan lewat panel (Tautan berkas → Tambah tautan) | Admin Regulasi |
| ☐ | **Judul/tanggal seed "perlu verifikasi"** | 13 entri di `dokumen-contoh.php` bertanda *perlu verifikasi* (judul persis, tanggal penetapan/pengundangan, pasal penutup); catatannya tersimpan di *Catatan internal* tiap dokumen. Cocokkan dengan naskah sebelum menerbitkan (G-08). Contoh: tanggal BAN-PT 13/2025 (16 Juni / 18 Agustus 2025), relasi Permendiktisaintek 42/2025 → Permendikbud 3/2021, usulan 39/2025 → 53/2023 (sengaja **tidak** dibuat) | Admin Regulasi |
| ☐ | **Konvensi T.E.U.** | Penulisan Tajuk Entri Utama di `BadanPenetapSeeder` (mis. `Indonesia. Kementerian Keuangan`, `Universitas Siliwangi. Fakultas Keguruan dan Ilmu Pendidikan`, `Lembaga Akreditasi Mandiri Kependidikan` tanpa awalan negara) perlu dicocokkan dengan Lampiran Permenkumham 8/2019; sunting di Master → Badan Penetap | Admin Regulasi |
| ☐ | **Sebutan jenis KEPPEJABAT / LAINNYA** | Jenis "Keputusan Dirjen/Kepala Lembaga" (`KEPPEJABAT`) dan "Dokumen Lainnya" (`LAINNYA`) belum punya singkatan resmi; sebutan otomatis dokumen tanpa nomor (mis. delapan dokumen IAPSK "LAMDIK 2025") tidak membedakan dokumen; jenis tanpa singkatan (PEDOMAN, MANUAL, INSTRUMEN, PERLEMBAGA) memakai singkatan badan, mis. pedoman FKIP tanpa nomor menjadi "FKIP Unsil 2026" — tetapkan konvensi singkatan/sebutan dan sunting bila perlu | Admin Regulasi |
| ☐ | **Domain banpt.or.id / lamdik.or.id** | Masih dikomentari di `config/berkas.php` (`domain_diizinkan`). Bila disetujui (G-11b), hapus komentar, rilis ulang, dan perbarui README daftar putih | Admin Regulasi & TI |
| ☐ | **Struktur laporan evaluasi** | Bab I–VI PDF laporan evaluasi (`docs/laporan-evaluasi.md`) mengikuti *semangat* Permenkumham 8/2019; cocokkan dengan Lampiran III (format laporan evaluasi) dan sesuaikan `resources/views/laporan/evaluasi.blade.php` bila berbeda | Admin Regulasi |
| ☐ | **Riwayat awal NULL → draf** | Saat ini pembuatan dokumen **tidak** mencatat baris riwayat awal (`dari` = NULL → `draf`); riwayat dimulai dari pengajuan/penerbitan. Putuskan apakah baris awal perlu dicatat (perubahan kode + migrasi data) | Admin Regulasi & pengembang |
| ☐ | **Alamat FKIP di kaki portal** | `REGULASI_ALAMAT` (bawaan "Jl. Siliwangi No. 24, Kota Tasikmalaya, Jawa Barat 46115") perlu dikonfirmasi FKIP; isi di `.env.production` | Humas/TU FKIP |

## 2. Infrastruktur & keamanan

| ✓ | Butir | Tindakan | Penanggung jawab |
|---|---|---|---|
| ☐ | **`innodb_ft_min_token_size`** | MariaDB lokal (host) memakai bawaan 3: "PP", "UU", "SE" tidak tercari di cadangan FULLTEXT (Meilisearch tidak terpengaruh). Produksi memakai `docker/mariadb/regulasi.cnf` (2, stopword mati) + `REGULASI_FT_MIN_TOKEN=2`. Pada target (keputusan 16 Oktober 2026) MariaDB produksi native di host, bukan container compose: pasang setelan yang sama di `my.cnf` host **sebelum** migrasi lalu bangun ulang indeks FULLTEXT (G-05). Perilaku FULLTEXT dibuktikan oleh gerbang uji `PencarianDatabaseTest` (Fase B), bukan diputuskan di dokumen ini | TI fakultas |
| ☐ | **robots.txt gateway** | Salin isi `public/robots.txt` (Disallow `/regulasi/admin`, `/regulasi/akun`, `/regulasi/d/`, `/regulasi/livewire`; `Sitemap: …/regulasi/sitemap.xml`) ke robots.txt akar `supportfkip.unsil.ac.id` | TI fakultas |
| ☐ | **Reverse proxy & HTTPS** | Blok `location /regulasi/` (docs/DEPLOY.md), `TRUSTED_PROXIES` = IP proxy, sertifikat valid | TI fakultas |
| ☐ | **Kartu aplikasi di landing supportfkip** | Tambah tautan `regulasi/` dan `regulasi/panduan/` di `index.html` akar supportfkip (PG-01) | TI fakultas |
| ☐ | **Cadangan di luar server** | Salin `./backup/mariadb` ke penyimpanan lain; uji pulih pertama dicatat (docs/DEPLOY.md §Uji pulih semester, G-11) | TI fakultas |
| ☐ | **Pemindaian port** | Pastikan hanya `WEB_PORT` terbuka untuk proxy; MariaDB/Redis/Meilisearch tidak terjangkau dari luar (KM-13) | TI fakultas |
| ☐ | **Super-admin awal `fkip@unsil.ac.id`** | Isi `REGULASI_SUPERADMIN_PASSWORD` di `.env.production` (jangan di repo), `db:seed --force`, masuk pertama: pasang MFA lalu ganti kata sandi awal (akun ditandai wajib ganti sandi). Setelah itu kosongkan kembali nilai sandi di `.env.production` bila diinginkan — seeder tidak menimpa akun yang sudah ada | Super Admin / TI fakultas |
| ☐ | **Flag `MFA_AKTIF`** | Bawaan `true` (wajib MFA bagi super-admin & admin-regulasi). Mematikan (`MFA_AKTIF=false`) adalah keputusan pemilik sistem; catat tanggal, alasan, dan tanggal dinyalakan kembali di tabel ini. Risiko & rekomendasi: `docs/keputusan/0002-flag-mfa-aktif.md` | Pemilik sistem |
| ☐ | **SMTP kampus** | Isi `MAIL_*`, uji surel atur kata sandi, notifikasi tolak/terbit, ringkasan mingguan | TI fakultas |
| ☐ | **Uptime monitor** | Pantau `https://supportfkip.unsil.ac.id/regulasi/api/health` (G-12) | TI fakultas |

## 3. Lingkungan pengembangan (mesin pengembang)

| ✓ | Butir | Tindakan |
|---|---|---|
| ☐ | **Klien API uji di DB dev** | `db_regulasi` memuat satu klien API uji lokal (dibuat saat F9 untuk mencoba API). Cabut tokennya / hapus klien bila tidak dipakai; jangan disalin ke produksi |
| ☐ | **Akun demo panduan** | `PanduanSeeder` (F12.6, hanya `APP_ENV=local`) membuat akun `panduan.<peran>@contoh.test` (tanpa MFA khusus akun demo lokal) dan data rekaan berlabel "(Contoh Panduan)". Dijalankan dengan `migrate:fresh` — pakai DB lokal/khusus. Tangkapan layar v1.0.0 dibuat dari DB terpisah `db_regulasi_panduan` (sudah dihapus); `db_regulasi` dev tidak diubah |
| ☐ | **Remote GitHub & auto-pull** | Remote `https://github.com/satyasantika/regulasi.git` (belum di-push dari mesin pengembang). Manusia: `git push -u origin main --tags`, proteksi branch `main` (wajib PR + CI hijau), isi GitHub Secrets `SERVER_HOST`, `SERVER_USER`, `SSH_PRIVATE_KEY` (opsional `SERVER_PORT`), siapkan clone + deploy key di server. Lihat docs/DEPLOY.md "Auto-pull dari GitHub" |
| ☐ | **Image uji lokal** | Uji build menyisakan image `regulasi-app:uji`, `regulasi-web:uji`, `regulasi-backup:uji` di Docker lokal; hapus bila tidak perlu (`docker image rm …`) |

## 4. Go-live (05-UJI §4) — status per 10 Oktober 2026

| No | Butir | Status |
|---|---|---|
| G-01 | Semua skenario §2 lulus & ditandatangani | **Belum** — pra-uji otomatis pengembang selesai (docs/uat/hasil-uat.md); UAT manusia & tanda tangan belum |
| G-02 | Checklist keamanan §3 | Sebagian — KM-08 (browser), KM-12, KM-13 perlu TI |
| G-03 | CI hijau pada `main`; tag `v1.0.0` | Uji lokal hijau & tag lokal dibuat; CI menunggu remote |
| G-04 | `.env` produksi | **Belum** (TI) — contoh: `.env.production.example`, `APP_VERSION=1.0.0` |
| G-05 | FULLTEXT token 2 & tanpa stopword | Disiapkan di compose (`docker/mariadb/regulasi.cnf`); terverifikasi di uji asap lokal |
| G-06 | migrate, seeder peran & master, super-admin dari env | Otomatis lewat entrypoint + `db:seed --force` (terverifikasi di uji asap lokal); ganti kata sandi awal: manusia |
| G-07 | PDF seed & `DokumenContohSeeder` | **Belum** — butir §1 baris pertama |
| G-08 | Lengkapi metadata seed & terbitkan; "perlu verifikasi" | **Belum** — §1 |
| G-09 | `scout:sync-index-settings` & `regulasi:reindeks`; salah ketik | Entrypoint menjalankan sync; reindeks manual setelah seed; salah ketik terverifikasi (P-02) |
| G-10 | Horizon & scheduler berjalan; `schedule:list` | Terverifikasi di uji asap lokal |
| G-11 | Backup harian & uji pulih pertama | Skrip & service siap, uji pulih lokal lulus; di server: **belum** |
| G-11a | Shared Drive "Regulasi FKIP" | **Belum** (Admin Regulasi) |
| G-11b | Daftar putih domain disetujui | **Belum** — §1 (banpt/lamdik) |
| G-12 | `/api/health` dipantau | **Belum** (TI) |
| G-13 | Token API untuk sistem FKIP | **Belum** (Super Admin), `docs/api.md` siap dibagikan |
| G-14 | Akun kontributor per sistem + panduan | **Belum**; panduan HTML siap di `/regulasi/panduan/` |
| G-15 | Pengumuman peluncuran | **Belum** (Pimpinan/Humas) |
