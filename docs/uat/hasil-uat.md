# Lembar hasil UAT — Regulasi FKIP Unsil

Skenario: dokumen uji penerimaan §2. Jalankan di staging (compose produksi, `APP_ENV=staging`)
dengan data `php artisan regulasi:siapkan-uat --segar` (akun uji per peran §1.2 + klien API "UAT Sistem Aset" dan
"UAT Internal"; kata sandi dari `--sandi`/`UAT_PASSWORD`, token API tampil sekali). Penguji mengisi kolom
**Penguji/Tanggal/Hasil manusia**; temuan dicatat di "Daftar temuan".

Versi diuji: `1.0.0` · Server uji: …… · Periode UAT: …… s.d. ……

## Pra-uji otomatis pengembang (10 Oktober 2026)

Sebelum UAT manusia, pengembang menjalankan pemeriksaan dari CLI di basis data terpisah `db_regulasi_uat`
(`regulasi:siapkan-uat --segar`, `REGULASI_SEED_TERBITKAN_SEMUA=true` agar dokumen contoh terbit seperti setelah G-08,
Meilisearch berawalan `uat_regulasi_`, antrean sinkron). Permintaan dikirim ke kernel HTTP Laravel (tanpa browser).

- **Otomatis-CLI ✓**: 30 pemeriksaan skenario lulus (kolom "Pra-uji" di bawah memuat buktinya).
- **Pest ✓**: perilaku diuji oleh rangkaian uji otomatis (`php artisan test`, 559+ uji hijau) — berkas uji disebut.
- **Belum diuji manusia**: perlu browser/ponsel, surel nyata, Google Drive nyata, atau penilaian manusia.

Semua skenario tetap **wajib diuji dan ditandatangani perwakilan peran** (G-01); pra-uji bukan pengganti UAT.

### 2.1 Tamu (publik)

| No | Skenario | Pra-uji pengembang | Penguji | Tanggal | Hasil manusia | Catatan |
|---|---|---|---|---|---|---|
| P-01 | Beranda | Otomatis-CLI ✓ — 200; statistik per jenis & kartu kategori tampil; draf/internal tidak tampil | | | | |
| P-02 | Cari salah ketik "permendikbudrstek 53" | Otomatis-CLI ✓ — mesin Meilisearch, teratas Permendikbudristek 53/2023 | | | | |
| P-03 | Cari isi PDF Permendiktisaintek 42/2025 | **Belum diuji** — tautan naskah seed belum diisi (lihat OPERASIONAL §1). Ekstraksi PDF nyata terbukti di uji asap Docker (PP 44/2025, 92 hlm) | | | | |
| P-04 | Filter Aset + Berlaku + 2016 | Otomatis-CLI ✓ — 1 hasil: PMK 181/PMK.06/2016; faset tahun 2016 = 1 | | | | |
| P-05 | URL filter dapat dibagikan | Otomatis-CLI ✓ — URL berfilter dibuka ulang → hasil sama | | | | |
| P-06 | Breadcrumb jenis | Otomatis-CLI ✓ — `/jenis/peraturan-menteri` 200, breadcrumb Beranda › Peraturan Menteri | | | | |
| P-07 | Detail BAN-PT 13/2025 | Otomatis-CLI ✓ — ditetapkan 16 Juni 2025, berlaku 18 Agustus 2025, mencabut BAN-PT 5/2019 & 5/2023 (tanggal seed: perlu verifikasi terhadap naskah) | | | | |
| P-08 | Status dicabut Permendikbud 3/2021 | Otomatis-CLI ✓ — badge Dicabut + "Dicabut oleh Permendiktisaintek 42/2025" | | | | |
| P-09 | Pratinjau Drive | Sebagian — tombol "Tampilkan pratinjau" ada; tampilan sematan & cadangan ponsel **belum diuji manusia** | | | | |
| P-10 | Buka berkas | Otomatis-CLI ✓ — 302 ke URL tersimpan; Pest `BukaTautanTest` (log & penghitung) | | | | |
| P-10a | URL internal tersembunyi | Otomatis-CLI ✓ — URL Drive internal tidak ada di HTML tamu | | | | |
| P-11 | Dokumen internal | Otomatis-CLI ✓ — tamu dialihkan ke `/akun/login` | | | | |
| P-12 | Draf tidak bocor | Otomatis-CLI ✓ — UUID draf → 404 | | | | |
| P-13 | Dokumen rujukan BAN-PT 5/2019 | Otomatis-CLI ✓ — "Metadata saja — naskah belum tersedia" + Dicabut | | | | |
| P-14 | Responsif 360 px | **Belum diuji manusia** (tangkapan layar ponsel panduan 390 px tersedia) | | | | |
| P-15 | Rate limit buka tautan | Otomatis-CLI ✓ — permintaan ke-30 = 302, ke-31 = 429 | | | | |

### 2.2 Pengguna terdaftar

| No | Skenario | Pra-uji pengembang | Penguji | Tanggal | Hasil manusia | Catatan |
|---|---|---|---|---|---|---|
| U-01 | Registrasi gmail ditolak | Pest ✓ `PanelAksesTest` ("Gunakan surel @unsil.ac.id") | | | | |
| U-02 | Registrasi unsil.ac.id | Pest ✓ `PanelAksesTest` (peran pengguna, verifikasi surel); surel nyata **belum diuji** | | | | |
| U-03 | Akses internal | Otomatis-CLI ✓ — detail internal 200 + tombol Buka berkas; pratinjau sematan **belum diuji manusia** | | | | |
| U-04 | Tidak bisa ke admin | Otomatis-CLI ✓ — `/admin` 403 | | | | |
| U-05 | Pencarian internal | Otomatis-CLI ✓ — masuk: 1 hasil; tamu: 0 (badge "Internal" belum diperiksa visual) | | | | |

### 2.3 Kontributor (Aset/BMN)

| No | Skenario | Pra-uji pengembang | Penguji | Tanggal | Hasil manusia | Catatan |
|---|---|---|---|---|---|---|
| K-01 | Lingkup kategori | Otomatis-CLI ✓ — daftar `uat.aset`: PMK 181 ada, BAN-PT tidak; Pest `DokumenResourceTest`, `MatriksAksesTest` | | | | |
| K-02 | Buat draf SOP | Pest ✓ `DokumenResourceTest` (draf, sebutan & slug otomatis) | | | | |
| K-03 | Kategori di luar tugas | Pest ✓ `DokumenResourceTest` | | | | |
| K-04 | Tautan naskah & lampiran | Pest ✓ `TautanBerkasTest`, `EkstrakTeksTautanTest`; dengan Drive nyata **belum diuji** | | | | |
| K-04a | Tautan terbatas | Pest ✓ (status *Tidak dapat diunduh*, tetap dapat diajukan); Drive nyata **belum diuji** | | | | |
| K-05 | Tautan ditolak | Otomatis-CLI ✓ — folder/pemendek/http/IP privat ditolak dengan pesan; Pest `Arch/TanpaUnggahTest` (tanpa tombol unggah) | | | | |
| K-06 | Ajukan tidak lengkap | Pest ✓ `AlurPublikasiTest`, `AksiFilamentTest` | | | | |
| K-07 | Ajukan lengkap | Pest ✓ `AlurPublikasiTest` (Menunggu Verifikasi, terkunci) | | | | |
| K-08 | Penolakan | Pest ✓ `AksiFilamentTest` (banner "Dokumen ditolak verifikator"), ajukan ulang | | | | |
| K-09 | Tidak bisa terbitkan | Otomatis-CLI ✓; Pest `AksiFilamentTest` | | | | |
| K-10 | Draf milik orang lain | Pest ✓ `MatriksAksesTest` (403) | | | | |
| K-11 | Notifikasi status | Pest ✓ `NotifikasiDokumenTest`; lonceng di browser **belum diuji manusia** | | | | |

### 2.4 Admin Regulasi

| No | Skenario | Pra-uji pengembang | Penguji | Tanggal | Hasil manusia | Catatan |
|---|---|---|---|---|---|---|
| A-01 | MFA wajib | Otomatis-CLI ✓ — `uat.admin` tanpa MFA → `/admin/multi-factor-authentication/set-up`; pemasangan autentikator **belum diuji manusia** | | | | |
| A-02 | Antrean verifikasi | Pest ✓ `AksiFilamentTest` (kelengkapan x/y, badge) | | | | |
| A-03 | Tolak | Pest ✓ `AksiFilamentTest`, `NotifikasiDokumenTest`; surel nyata **belum diuji** | | | | |
| A-04 | Terbitkan | Pest ✓ `AlurPublikasiTest`, `IndeksDokumenTest`, `BerandaTest` | | | | |
| A-05 | Lengkapi PP 28/2020 | Sebagian — di data demo PP 27/2014 sudah "Berlaku dengan Perubahan"; alur melengkapi tanggal lalu terbit **belum diuji manusia** (tanggal dari naskah) | | | | |
| A-06 | Relasi mencabut | Pest ✓ `TambahRelasiTest`, `RelasiUiTest` | | | | |
| A-07 | Relasi sisi pasif | Pest ✓ `TambahRelasiTest` | | | | |
| A-08 | Relasi tertunda | Pest ✓ `SiklusRelasiTest` (+3 hari, penjadwal) | | | | |
| A-09 | Hapus relasi keliru | Pest ✓ `SiklusRelasiTest` | | | | |
| A-10 | Relasi tidak sah | Pest ✓ `TambahRelasiTest`, `RelasiUiTest` | | | | |
| A-11 | Dokumen rujukan | Pest ✓ `RelasiUiTest` | | | | |
| A-12 | Status manual | Pest ✓ `SiklusRelasiTest`, `RelasiUiTest` | | | | |
| A-13 | Arsipkan | Pest ✓ `AlurPublikasiTest`, `DetailDokumenTest`, `SitemapSeoTest`, `DokumenApiTest` | | | | |
| A-14 | Tautan bermasalah | Pest ✓ `PeriksaTautanBerkasTest`, `TautanBermasalahTest`; Drive nyata **belum diuji** | | | | |
| A-15 | Laporan kelengkapan | Pest ✓ `LaporanKoleksiTest` (filter, ekspor berbahasa Indonesia via antrean) | | | | |
| A-16 | Laporan evaluasi | Pest ✓ `LaporanEvaluasiTest` (PDF di-stream, final terkunci); struktur bab **perlu verifikasi** vs Lampiran Permenkumham 8/2019 | | | | |
| A-17 | Master "Peraturan Dekan" urutan 75 | Pest ✓ `ResourceMasterTest` (buat jenis, urutan); tampilan filter publik **belum diuji manusia** | | | | |
| A-18 | Penugasan Keuangan | Pest ✓ `ResourceMasterTest` (atur penugasan) | | | | |

### 2.5 Super Admin

| No | Skenario | Pra-uji pengembang | Penguji | Tanggal | Hasil manusia | Catatan |
|---|---|---|---|---|---|---|
| S-01 | Buat pengguna | Pest ✓ `UserResourceTest` (surel atur kata sandi); surel nyata **belum diuji** | | | | |
| S-02 | Nonaktifkan | Pest ✓ `PanelAksesTest`, `UserResourceTest` | | | | |
| S-03 | Klien API & token 90 hari | Pest ✓ `KlienApiTest` (token tampil sekali) | | | | |
| S-04 | Log aktivitas | Pest ✓ `LogAktivitasTest` | | | | |
| S-05 | Horizon | Otomatis-CLI ✓ — super-admin 200, admin-regulasi 403 | | | | |
| S-06 | Soft delete & pulihkan | Pest ✓ `DokumenResourceTest`, `MatriksAksesTest` (hanya super-admin; UUID tetap) | | | | |
| S-07 | Integritas berkas | Diganti `berkas:periksa-tautan` (perintah `regulasi:periksa-berkas` tidak pernah ditetapkan di rencana kerja); **belum diuji** dengan tautan nyata | | | | |

### 2.6 Klien API

| No | Skenario | Pra-uji pengembang | Penguji | Tanggal | Hasil manusia | Catatan |
|---|---|---|---|---|---|---|
| X-01 | Tanpa token | Otomatis-CLI ✓ — 401 JSON:API | | | | |
| X-02 | Filter kategori+status | Otomatis-CLI ✓ — 2 dokumen Aset berlaku; id UUIDv7 | | | | |
| X-03 | Alias sederhana | Otomatis-CLI ✓ — sama dengan X-02 | | | | |
| X-04 | Internal | Otomatis-CLI ✓ — token biasa 404, token internal 200 | | | | |
| X-05 | Relasi | Otomatis-CLI ✓ — `include=relasi` memuat `dicabut_oleh` | | | | |
| X-06 | Rate limit | Otomatis-CLI ✓ — ke-60 = 200, ke-61 = 429 + Retry-After | | | | |
| X-07 | Klien nonaktif | Otomatis-CLI ✓ — 401 | | | | |
| X-08 | Hanya baca | Otomatis-CLI ✓ — POST 405, data tidak berubah | | | | |

## Checklist keamanan (§3) — status pengembang

| No | Status | Bukti |
|---|---|---|
| KM-01 | ✓ | `tests/Feature/Keamanan/MatriksAksesTest.php` (60 rute × 5 peran + API/aksi); `KebijakanResourceTest` |
| KM-02 | ✓ | `KeamananTest` (id numerik → 404/401; UUID draf/ditolak/menunggu/arsip/terhapus → 404) |
| KM-03 / KM-03a | ✓ | `KeamananTest` (javascript:, data:, http:, IP privat/loopback, userinfo, pemendek, folder), `PengunduhAmanTest`, `Arch/TanpaUnggahTest`; uji asap Docker: tmp ekstraksi kosong |
| KM-04 | ✓ | `KeamananTest` (parameter `to/url/redirect/next/tujuan` diabaikan), `BukaTautanTest` (no-referrer, no-store) |
| KM-05 | ✓ | `PanelAksesTest` (masuk 5/menit), P-15 (buka 30/menit), X-06 (API 60/menit), `DaftarDokumenTest` (pencarian 60/menit) |
| KM-06 | Lulus (CLI + test) | POST `/admin/logout` & `/akun/logout` tanpa token dengan sesi masuk → 419, sesi tetap utuh; `Sec-Fetch-Site: cross-site` → 419. Tamu mendapat 302 ke halaman masuk karena `Authenticate` Filament berjalan sebelum `PreventRequestForgery` (prioritas middleware) — tanpa efek. Regresi: `tests/Feature/Keamanan/CsrfLogoutTest.php` |
| KM-07 | ✓ | `KeamananTest` (XSS judul/abstrak/keterangan relasi/tag; tidak ada `{!!` di `resources/views`) |
| KM-08 | ✓ (CLI) / belum diuji manusia di browser | `curl -I` & `KeamananTest`: CSP `frame-src https://drive.google.com https://docs.google.com`, `frame-ancestors 'self'`, X-Frame-Options, Permissions-Policy, COOP, HSTS (produksi); panel & portal dirender Playwright dengan CSP aktif (panduan F12.6) |
| KM-09 | ✓ | A-01; `UserResourceTest` |
| KM-10 | ✓ | X-01..X-08; `KlienApiTest` |
| KM-11 | ✓ | `BukaTautanTest` (ip_hash), `LogAktivitasTest`, jadwal prune 24 bulan (`JadwalTest`) |
| KM-12 | Tindakan manusia | `.env.production.example` (`APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`); `.env` server diisi TI |
| KM-13 | ✓ (konfigurasi) / tindakan manusia | `/horizon` hanya super-admin (S-05); Meilisearch tanpa port host di compose; pindai port dari luar oleh TI |
| KM-14 | ✓ 10 Okt 2026 | `composer audit`: tidak ada advisori (2 paket *abandoned*: dragon-code/*, dependensi tidak langsung); `npm audit --omit=dev`: 0 kerentanan |
| KM-15 | ✓ | `git log -p` tidak memuat rahasia nyata (hanya contoh kosong & rahasia uji MFA tetap di tes) |

## Daftar temuan

| No | Skenario | Temuan | Tingkat | Status perbaikan |
|---|---|---|---|---|
| | | | | |

## Pengesahan

| Peran | Nama | Tanda tangan | Tanggal |
|---|---|---|---|
| Admin Regulasi | | | |
| Kontributor (Aset/BMN) | | | |
| Kontributor (Persuratan) | | | |
| Pengguna | | | |
| Super Admin / TI fakultas | | | |
