# Catatan Perubahan

Semua perubahan penting pada proyek ini dicatat di berkas ini.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/) dan proyek memakai [Semantic Versioning](https://semver.org/lang/id/).

## [Belum dirilis]

## [0.8.0] - 2026-10-10

### Ditambahkan
- Tata letak portal publik `x-layouts.publik` (Blade + Livewire 4 + Tailwind CSS 4 via `@tailwindcss/vite`, US-PUB-03):
  `<html lang="id">`, tautan lewati-ke-konten, header dengan navigasi Beranda/Dokumen/Kategori (dropdown dari master
  ter-cache) dan tombol Masuk/nama pengguna → `/akun`, menu hamburger Alpine di < 768 px, slot breadcrumb, kaki berisi
  alamat FKIP (`config('regulasi.alamat')`, `REGULASI_ALAMAT`) dan versi. Komponen `x-publik.breadcrumb`,
  `x-publik.badge-status`, `x-publik.ikon` (Heroicon kategori), `x-publik.kartu-dokumen`, `x-publik.paginasi`.
- Beranda Livewire `Publik\Beranda` (`/`, `beranda`, US-PUB-01): hero dengan kotak pencarian (GET ke daftar dokumen),
  ringkasan angka, kartu per jenis (→ `/jenis/{slug}`), kartu kategori sistem berikon (→ `/kategori/{slug}`), 10 dokumen
  terbaru, tautan per status berlaku dan per tahun. `CacheRegulasi::statistikBeranda()`, `terbaru()`, `tahunTersedia()`
  (kunci `beranda:statistik`, `beranda:terbaru`, `faset:tahun`, 60 menit; hanya terbit & publik); pengguna login melihat
  dokumen internal pada daftar terbaru lewat query terpisah yang tidak di-cache (BR-02, BR-20).
- Daftar dokumen berfaset Livewire `Publik\DaftarDokumen` untuk `/dokumen`, `/jenis/{jenisDokumen:slug}` (jenis
  terkunci), dan `/kategori/{kategoriSistem:slug}` (kategori terkunci) (US-CARI-01..03): `q` (debounce 400 ms), `jenis[]`,
  `tahun[]`, `status[]`, `kategori[]`, `badan[]`, `urut` tersinkron ke URL; memakai `PencarianDokumen` F7.3 dengan
  `sertakanInternal` = login; faset berjumlah per dimensi dalam `fieldset/legend`, panel "Filter (n)" lipat di ponsel,
  chip filter aktif, "Hapus semua filter", jumlah hasil `aria-live`, catatan "Pencarian sederhana" saat mesin database,
  breadcrumb dan `<title>` per rute. Rate limit `pencarian` 60/menit per IP (mesin tidak dipanggil bila terlampaui).
- Halaman detail `/dokumen/{dokumen}/{slug?}` (`dokumen.show`, `Publik\DetailDokumenController`, US-PUB-02, BR-19): 404
  bila tidak terbit/terhapus, dokumen internal untuk tamu → masuk akun, slug kosong/lama → 301 (BR-16); breadcrumb
  Beranda › Jenis › sebutan; badge status + kalimat `RingkasanStatus` dan relasi tertunda; tabel metadata (T.E.U., tanggal
  `j F Y`, sumber + halaman sumber resmi, kategori, subjek → pencarian, bahasa); abstrak ter-escape per paragraf; relasi
  dikelompokkan (Mencabut … Dilaksanakan oleh) dengan badge dan tanda "Tertunda" (dokumen terkait yang belum terbit
  tidak ditampilkan, internal tanpa tautan bagi tamu); daftar tautan `x-tautan-berkas` (penyedia, jumlah halaman
  terekstraksi); kotak "Metadata saja" untuk `tanpa_berkas`; jumlah dibuka. Policy `DokumenPolicy::lihatDiPortal`.
- Pratinjau sematan `x-pratinjau-tautan` tanpa PDF.js/berkas lokal: Google Drive/Docs → iframe `/preview`
  (`loading="lazy"`, `referrerpolicy="no-referrer"`) yang baru dimuat setelah "Tampilkan pratinjau", dengan tombol
  "Buka berkas" sebagai cadangan; sumber resmi pemerintah → "Buka di situs sumber". URL pratinjau hanya bagi yang berhak.
- Middleware `HeaderKeamanan` (dasar F12.1): CSP dengan `frame-src https://drive.google.com https://docs.google.com`,
  `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`.
- SEO (F8.4): komponen `x-meta` (title, description, canonical slug terkini, Open Graph dasar); `noindex` untuk dokumen
  internal serta panel `/admin` dan `/akun` (render hook); JSON-LD schema.org `Legislation` (peraturan/keputusan:
  `legislationIdentifier`, `legislationDate`, `legislationPassedBy`, `legislationType`, `legislationLegalForce`) atau
  `CreativeWork` (`App\Support\DataTerstruktur`); `/sitemap.xml` (`Publik\SitemapController`) berisi beranda, daftar,
  jenis & kategori yang berdokumen, dan dokumen terbit & publik (`lastmod`), cache `sitemap` 1 jam yang ikut dibersihkan
  `lupakanPublik()`; `public/robots.txt` dengan baris untuk robots.txt gateway.
- Aksesibilitas: label untuk semua input, ikon `aria-hidden`, urutan heading h1→h2→h3, `focus-visible`, target sentuh
  ≥ 44 px di ponsel, tombol buka menyebut penyedia dan membuka tab baru `rel="noopener noreferrer"`; audit axe-core
  (WCAG 2.2 AA + best practice) tanpa pelanggaran untuk beranda, daftar, jenis, dan detail di 360 px dan 1280 px.
- Uji `tests/Feature/Publik/` (BerandaTest, DaftarDokumenTest, DetailDokumenTest, SitemapSeoTest), termasuk uji bahwa
  halaman detail dokumen internal tidak memuat URL Drive/URL mentah bagi tamu (tindak lanjut F4).

### Diubah
- `CacheRegulasi::master()` menyimpan larik atribut lalu meng-hydrate model: store Redis menolak unserialize objek
  (`cache.serializable_classes = false`), sehingga cache master yang hangat sebelumnya menimbulkan galat.
- Komponen `x-tautan-berkas` ditata ulang dengan Tailwind, menampilkan penyedia & jumlah halaman, dan menerima
  `:pratinjau="false"`. Rute `/` kini beranda portal (`welcome.blade.php` dihapus). Font Instrument Sans menambah bobot 700.
- `APP_VERSION=0.8.0`.

## [0.7.0] - 2026-10-10

### Ditambahkan
- Ekstraksi teks PDF dari tautan tanpa menyimpan berkas (BR-14, US-EKS-01/02): tabel `ekstraksi_teks` (03-SKEMA §3.10a,
  FULLTEXT `ft_ekstraksi_isi`), enum `StatusEkstraksi`, model `EkstraksiTeks` (alias morph `ekstraksi_teks`),
  `TautanBerkas::ekstraksi()`. `spatie/pdf-to-text` (poppler-utils) lewat `PengekstrakTeksPdf` (pdfinfo + pdftotext UTF-8,
  normalisasi spasi & karakter kontrol); teks minim → `tanpa_teks`.
- `PengunduhAman` anti-SSRF (02-ARSITEKTUR §4.3): daftar putih host (+ `domain_unduhan_tambahan`), resolusi DNS sendiri
  (`PemecahDns`) yang menolak IP privat/loopback/link-local/CGNAT/IPv4-mapped, sambungan dipaku `CURLOPT_RESOLVE`, redirect
  manual ≤ 3 dengan validasi ulang, batas 30 MB (Content-Length + penghitung progress saat streaming ke `sink`), timeout
  5/30 dtk, tanpa cookie, User-Agent `RegulasiFKIPUnsil/1.0`, tolak respons HTML Drive dan isi tanpa magic bytes `%PDF-`;
  berkas sementara `storage/app/tmp/ekstraksi/{uuid}.pdf` 0600. Kontrak `PenyimpananBerkas::ambilSementara()` →
  `BerkasSementara`.
- Job `PeriksaTautanBerkas` (antrean `tautan`, unik per tautan; HEAD lalu GET Range 0-1023 tanpa menyimpan isi; login
  Google/401/403 = dapat diakses; berubah menjadi tidak dapat diakses → peringatan log sampai notifikasi F10) dan
  `EkstrakTeksTautan` (antrean `ekstraksi`, unik, timeout 300, 3 percobaan backoff 60/300/900; penolakan terkendali tanpa
  retry; `failed()` → `gagal` + percobaan; hash berubah → `isi_berubah_pada` + event `IsiBerkasBerubah`; selesai → event
  `TeksTautanDiekstraksi`; berkas sementara selalu dihapus di `finally`). Pembatas `unduh-host:{host}` 30/menit (job
  dilepas ulang 60 dtk). `SimpanTautanBerkas` mengantrekan keduanya setelah commit; URL berubah mereset status cek &
  ekstraksi; jenis `halaman_sumber`/`lainnya` → `tidak_berlaku` tanpa job.
- Perintah `regulasi:ekstraksi-ulang {--gagal} {--tanpa-teks} {--tautan=*}` dan `berkas:bersihkan-tmp` (> 24 jam).
  Supervisor Horizon `supervisor-tautan` (1 proses) dan `supervisor-ekstraksi` (1 proses, timeout 330).
- Halaman admin "Tautan Bermasalah" (`/admin/tautan-bermasalah`, `dokumen.verifikasi`): tautan mati, ekstraksi bermasalah,
  dan isi berubah; aksi periksa ulang, ekstrak ulang (per baris & massal), tandai ditinjau. Kolom "Ekstraksi" di relation
  manager tautan berkas dan butir "Teks terekstraksi" di panel Kelengkapan membaca status naskah utama.
- Pencarian Laravel Scout + Meilisearch (02-ARSITEKTUR §5.1): indeks `regulasi_dokumen` hanya dokumen terbit & tidak
  dihapus, `toSearchableArray` (kategori, tag, isi teks ekstraksi berhasil naskah utama lebih dulu dipotong
  `potong_isi_teks_kata`), pengaturan filter/sort/typo dan sinonim dua arah dari `config('regulasi.sinonim')`, job indeks di
  antrean `indeks`. Indeks diperbarui eksplisit setelah commit: terbit/pulih/arsip, simpan dokumen terbit, tautan dihapus
  atau URL-nya berubah, pendengar `PerbaruiIndeksDokumen` (teks diekstraksi, status berlaku berubah).
- Layanan pencarian berfaset `PencarianDokumen` (`KriteriaPencarian` → `HasilPencarian`: paginator, faset jenis/tahun/
  status/kategori/badan, mesin): `PencarianMeilisearch` (filter IN, visibilitas, sort, facetDistribution, faset
  disjungtif) dan cadangan `PencarianDatabase` (MariaDB FULLTEXT BOOLEAN MODE + nomor LIKE + teks ekstraksi, faset
  GROUP BY). `PemilihMesinPencarian` turun ke database bila Meilisearch tidak sehat (`pencarian:sehat`, 60 dtk) dengan
  peringatan log. Perintah `regulasi:reindeks {--hapus}`.
- Uji: `Http::preventStrayRequests()` + DNS tiruan di semua uji; suite `tests/Integrasi` (grup `meilisearch`, di-skip bila
  Meilisearch tak terjangkau, mis. CI).

### Diubah
- Batas memori PHPUnit 512 MB (uji arsitektur mem-parse dependensi Scout).
- CI menjalankan uji ekstraksi dengan `--fail-on-skipped` (poppler-utils terpasang); `APP_VERSION=0.7.0`.

## [0.6.0] - 2026-10-10

### Ditambahkan
- Tabel `dokumen_relasi` (03-SKEMA §3.11: `uk_relasi`, `pasangan_id` UNIQUE FK diri SET NULL, CHECK
  `dokumen_id <> dokumen_terkait_id`, indeks `efektif`+`efektif_pada`) dan migrasi baru FK
  `riwayat_status_dokumen.dokumen_relasi_id` → `dokumen_relasi` SET NULL. Enum `JenisRelasi` (`kebalikan()`,
  `adalahArahAktif()`, `dampakStatus()`), model `DokumenRelasi` (alias morph `dokumen_relasi`, jejak audit `relasi`),
  `Dokumen::relasi()`, event `StatusBerlakuBerubah` (setelah commit; pendengar di F7/F10).
- Relasi simetris (BR-07..BR-10) lewat `TambahRelasiDokumen`: mencabut/mengubah/melaksanakan beserta kebalikannya
  dalam satu transaksi, jenis pasif dari sisi target dinormalkan, tolak relasi ke diri sendiri/ganda/siklik/sumber
  lebih lama; kunci `lock:status-dokumen:{id}` berurutan id menaik (`KunciStatusDokumen`) + `lockForUpdate`.
- Hitung ulang status berlaku (`HitungUlangStatusBerlaku`, BR-09: dicabut > diubah > berlaku; status manual tidak
  ditimpa) dengan riwayat berjenis `berlaku` yang menunjuk relasi pemicu.
- `HapusRelasiDokumen` (berpasangan, alasan wajib di log aktivitas), status manual `UbahStatusBerlaku`
  (tidak berlaku manual / kembalikan otomatis, catatan wajib, `status-berlaku.ubah`), `TerapkanRelasiDokumen`
  yang dipanggil setelah terbit, arsip, pulih, dan perubahan tanggal dokumen terbit; perintah
  `regulasi:terapkan-status` (`--dry-run`) untuk relasi yang tanggal efektifnya tiba (penjadwalan di F10.2).
- Antarmuka relasi: relation manager "Relasi" (tambah relasi dengan label "Dokumen ini mencabut …", pencarian
  dokumen di sisi server tanpa dokumen itu sendiri, pintas "Tambah dokumen rujukan" yang langsung terbit (BR-11),
  hapus dengan alasan, tanpa ubah), tombol "Ubah status berlaku", bagian "Status" berkalimat ringkas
  (`RingkasanStatus`, mis. "Dicabut oleh BAN-PT 13/2025 sejak 18 Agustus 2025") dan peringatan relasi tertunda.
  `DokumenRelasiPolicy`, `DokumenPolicy::ubahStatusBerlaku`.
- Seeder `DokumenContohSeeder` (03-SKEMA §4.5, lewat Action, idempoten): 26 dokumen contoh (buku 3 & 4 IAPSK 3.0
  masing-masing tiga entri), 9 dokumen rujukan, 7 relasi; fakta tak pasti dikosongkan dan ditandai "perlu verifikasi"
  di catatan internal; URL naskah dari data atau `database/seeders/data/tautan-contoh.local.php` (di-.gitignore);
  `REGULASI_SEED_TERBITKAN_SEMUA` untuk demo lokal. Dipanggil `DatabaseSeeder` di luar produksi.
- `docs/RELASI.md`: panduan singkat relasi untuk admin.

### Diubah
- Kait `setelahCommit`/`setelahTerbit`/`setelahArsip` alur publikasi menerima pengguna pelaku.

## [0.5.0] - 2026-10-10

### Ditambahkan
- Tabel `riwayat_status_dokumen` (03-SKEMA §3.12, append-only tanpa `updated_at`; `dokumen_relasi_id` tanpa FK
  sampai F6.1), enum `JenisRiwayatStatus` (publikasi, berlaku), model `RiwayatStatusDokumen` (ubah/hapus ditolak),
  `Dokumen::riwayatStatus()` terbaru lebih dulu.
- Alur publikasi lewat Action bertransaksi (`Cache::lock('lock:publikasi:{id}')` + `DB::transaction` +
  `lockForUpdate()`, satu baris riwayat per transisi, efek samping di `afterCommit`): `AjukanVerifikasi`
  (draf/ditolak → menunggu verifikasi), `TerbitkanDokumen` (menunggu verifikasi/draf → terbit; mengisi
  `diverifikasi_oleh`/`diverifikasi_pada`, `dipublikasikan_pada` hanya pertama kali; titik kait `setelahTerbit`),
  `TolakDokumen` (catatan wajib ≥ 10 karakter ke `catatan_verifikasi`), `ArsipkanDokumen` (catatan wajib; titik kait
  `setelahArsip`), `PulihkanDokumen` (diarsipkan → terbit). `PeriksaKelengkapan` memeriksa syarat terbit BR-05.
  `DokumenPolicy::ajukan`, `verifikasi`, `arsipkan`; `CacheRegulasi::lupakanPublik()` dipanggil saat terbit,
  arsip, dan pulih (BR-20).
- Tombol alur publikasi di halaman lihat & ubah dokumen (Ajukan verifikasi, Terbitkan, Tolak, Arsipkan, Pulihkan)
  menurut Policy + status; kekurangan kelengkapan tampil sebagai notifikasi. Panel "Kelengkapan" (checklist BR-05 +
  abstrak, tanggal berlaku, tag, teks terekstraksi), relation manager riwayat status (baca saja), dan banner catatan
  penolakan di halaman ubah.
- Halaman "Antrean Verifikasi" (`/admin/antrean-verifikasi`, `dokumen.verifikasi`, badge jumlah menunggu): urut paling
  lama menunggu, kolom pembuat, kategori, lama menunggu, kelengkapan x/y; aksi baris Terbitkan & Tolak dan Terbitkan
  massal yang melewati dokumen tidak lengkap dan melaporkannya.

### Diubah
- `StatusPublikasi::bolehTransisiKe()`: dokumen ditolak boleh langsung diajukan ulang ke menunggu verifikasi.

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
