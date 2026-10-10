# Catatan Perubahan

Semua perubahan penting pada proyek ini dicatat di berkas ini.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/) dan proyek memakai [Semantic Versioning](https://semver.org/lang/id/).

## [Belum dirilis]

## [0.4.0] - 2026-10-10

### Ditambahkan
- Tabel `dokumen` (03-SKEMA §3.7, UUIDv7, soft delete) dengan `uk_dokumen_identitas` (BR-06), `idx_dokumen_publik`,
  `idx_dokumen_jenis_tahun`, dan FULLTEXT `ft_dokumen`; pivot `dokumen_kategori_sistem` dan `dokumen_tag`.
  Enum `StatusPublikasi` (`bolehTransisiKe()` sesuai PRD §6.1), `StatusBerlaku`, `Visibilitas`.
- Model `Dokumen`: scope `terbit()`, `publik()`, `terlihatOleh()` (tamu: terbit & publik; login: terbit),
  `dalamKategoriPengguna()`; `tanggal_efektif` (berlaku ?? pengundangan ?? penetapan); jejak audit tanpa
  `jumlah_dibuka`. `SebutanDokumen` (mis. "PMK 181/PMK.06/2016", "BAN-PT 13/2025") dan `BuatSlugDokumen` (unik, ≤ 200).
- Resource Filament "Dokumen" (`/admin/dokumen`): form bersection lewat aksi `SimpanDokumen` (nomor wajib untuk
  jenis peraturan/keputusan, tanggal pengundangan ≥ penetapan, duplikat BR-06 ditolak, slug dibentuk ulang saat
  nomor/judul berubah), tab status publikasi, filter jenis/tahun/status/kategori/badan/visibilitas. Status publikasi &
  berlaku tidak dapat diubah lewat form. `DokumenPolicy`: kontributor hanya kategori yang ditugaskan dan hanya
  mengubah/menghapus draf/ditolak miliknya (BR-03, BR-16); admin tidak mengubah dokumen diarsipkan.
- Tautan berkas tanpa unggahan (BR-13): tabel `tautan_berkas` polimorfik (alias morph `dokumen`, morph map wajib),
  `config/berkas.php`, rule `TautanBerkasValid` (https, tanpa userinfo/port/IP, daftar putih per label termasuk
  sumber resmi pemerintah, tolak pemendek & folder Drive, pesan perbaikan berbahasa Indonesia), `PenguraiTautan`
  (penyedia, `drive_file_id`, URL pratinjau/unduh), kontrak `PenyimpananBerkas` + `TautanEksternal`, aksi
  `SimpanTautanBerkas` (tolak URL/berkas ganda dalam dokumen, peringatan bila dipakai dokumen lain, naskah utama lama
  menjadi lampiran, `PeriksaTautanBerkas` diantrekan) dan `HapusTautanBerkas`; relation manager tautan berkas dengan
  petunjuk berbagi publik/internal. Uji arsitektur: tidak ada `FileUpload`/penulisan disk berkas.
- Buka tautan berotorisasi `GET /d/{id}/buka` (`tautan.buka`): `TautanBerkasPolicy::buka` (tamu: terbit & publik;
  login: terbit; staf: dokumen yang boleh dilihat), tamu ke dokumen internal diarahkan masuk, selain itu 404; redirect
  302 hanya ke URL tersimpan dengan `Referrer-Policy: no-referrer` dan `Cache-Control: no-store`; rate limit `buka`
  30/menit per pengguna/IP. Log `log_buka_tautan` (`ip_hash` SHA-256(IP + APP_KEY), tanpa IP mentah, prunable
  24 bulan) dan penghitung `jumlah_dibuka` lewat job `CatatBukaTautan` setelah respons. Komponen Blade
  `x-tautan-berkas` (Buka berkas + pratinjau sematan Drive bagi yang berhak, URL mentah tidak dicetak).

## [0.3.0] - 2026-10-10

### Ditambahkan
- Tabel master (03-SKEMA §3.2–§3.6, UUIDv7): `jenis_dokumen`, `badan_penetap` (T.E.U., induk SET NULL),
  `kategori_sistem` (ketiganya soft delete), `kategori_sistem_user` (PK komposit), `tag`; enum
  `KelompokJenisDokumen` (`wajibNomor()`) dan `TingkatBadanPenetap`; model dengan slug otomatis unik,
  scope `aktif()`/`terurut()`, jejak audit `master`.
- Seeder master idempoten: 18 jenis dokumen, 11 badan penetap (FKIP berinduk Universitas Siliwangi),
  11 kategori sistem berikon Heroicon, 16 tag subjek; konvensi T.E.U. ditandai perlu verifikasi.
- Cache master Redis `App\Support\CacheRegulasi::master()` (`master:{nama}`, 24 jam, aktif & terurut)
  yang dibersihkan otomatis oleh `MasterCacheObserver` dan setelah pengurutan ulang.
- Resource Filament grup "Master": Jenis Dokumen (urut seret-lepas), Badan Penetap, Kategori Sistem, Tag;
  filter aktif/terhapus, pulihkan; kode jenis/kategori terkunci setelah dibuat. Policy: lihat `master.lihat`,
  kelola `master.kelola`, tag baru `tag.buat`, tanpa hapus permanen.
- Penugasan kontributor ke kategori sistem: aksi "Atur penugasan" (`penugasan.kelola`, admin-regulasi tanpa
  hak ubah data pengguna lain) dan isian di form pengguna; perubahan tercatat di log aktivitas.
  Akun lokal `kontributor.aset@unsil.test` ditugaskan ke kategori Aset/BMN.

## [0.2.0] - 2026-10-10

### Ditambahkan
- Peran & permission (spatie/laravel-permission, UUID): `super-admin`, `admin-regulasi`, `kontributor`, `pengguna`
  dengan 22 permission (PRD §3.3, 03-SKEMA §4.1); `Gate::before` super-admin; Horizon hanya untuk `horizon.akses`.
- Seeder `PeranDanIzinSeeder` (idempoten) dan `PenggunaAwalSeeder` (super-admin dari `SEED_SUPERADMIN_*`,
  akun contoh `*@unsil.test` hanya di lokal).
- Kolom `users`: `nip`, `prodi_id`, `kode_eksternal`, `aktif`, `terakhir_masuk_pada` (dicatat saat masuk).
- Filament 5: panel `admin` (`/admin`, staf) dan panel `akun` (`/akun`, masuk/daftar/verifikasi surel/profil),
  berjalan di bawah subpath `/regulasi` (middleware `AwalanSubpath` untuk URL Livewire).
- Registrasi mandiri hanya surel `@unsil.ac.id`, otomatis berperan `pengguna`; masuk dibatasi 5 percobaan/menit per surel + IP.
- Jejak audit (spatie/laravel-activitylog, UUID, retensi 365 hari): perubahan nama/surel/NIP/status aktif dan
  pemberian/pencabutan peran pengguna; halaman baca-saja "Log Aktivitas" (`audit.lihat`).
- Manajemen pengguna oleh super-admin (tautan atur kata sandi, tanpa hapus permanen, tidak dapat menonaktifkan diri sendiri);
  admin-regulasi hanya melihat.
- MFA aplikasi autentikator (kode pemulihan) di kedua panel; wajib bagi super-admin dan admin-regulasi.

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
