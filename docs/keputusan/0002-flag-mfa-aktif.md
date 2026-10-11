# 0002 — Flag `MFA_AKTIF` dan super-admin awal `fkip@unsil.ac.id`

- Status: Diterima
- Tanggal: 2026-10-12

## Konteks

Super-admin dan admin-regulasi menerbitkan dokumen, mengubah status berlaku, mengelola akun, token API, dan dapat
menyamar sebagai pengguna lain (impersonasi). Sejak v1.0.0 kedua peran itu wajib MFA (aplikasi autentikator).
Sistem saudara di FKIP (mis. SDM) menyediakan flag untuk mematikan MFA sementara saat masa transisi. Regulasi
membutuhkan saklar yang sama, tetapi dengan bawaan yang aman.

Rilis 1.1.0 juga menetapkan akun super-admin awal `fkip@unsil.ac.id` yang dibuat seeder.

## Keputusan

1. `config('regulasi.mfa_aktif')` dibaca dari `MFA_AKTIF`, **bawaan `true`** di kode, `.env.example`, dan
   `.env.production.example`.
   - `true`: MFA tersedia di kedua panel; **wajib** bagi super-admin dan admin-regulasi (middleware `WajibMfa`
     mengalihkan ke halaman pengaturan MFA); opsional bagi kontributor dan pengguna.
   - `false`: MFA dimatikan seluruhnya — penyedia MFA panel kosong (tidak ada tantangan kode saat masuk, bagian MFA
     tidak tampil di profil) dan `User::wajibMfa()` selalu `false`. Rahasia MFA yang sudah tersimpan tidak dihapus,
     sehingga MFA langsung berlaku lagi ketika flag dinyalakan kembali.
2. Mengubah flag cukup dengan mengganti `.env` lalu `php artisan config:cache` (produksi: `up -d` ulang container).
   Keputusan mematikan flag ada pada pemilik sistem; pengembang tidak mematikannya.
3. Super-admin awal dibuat `PenggunaAwalSeeder` secara idempoten: surel `REGULASI_SUPERADMIN_EMAIL` (bawaan
   `fkip@unsil.ac.id`), kata sandi **hanya** dari `REGULASI_SUPERADMIN_PASSWORD` di `.env` (kosong di contoh, wajib di
   produksi). Akun baru ditandai `wajib_ganti_sandi`; menjalankan ulang seeder tidak mengubah sandi maupun tanda itu.
   Kata sandi tidak pernah ditulis di berkas yang di-commit.

## Risiko bila `MFA_AKTIF=false`

- Akun berhak tinggi hanya dilindungi kata sandi: kebocoran/penebakan sandi super-admin memberi akses penuh ke
  pengguna, peran, token API, impersonasi, dan penerbitan dokumen hukum.
- Impersonasi oleh super-admin menjadi jalur eskalasi bila akun super-admin diambil alih.
- Kepatuhan: jejak audit tetap mencatat tindakan, tetapi tidak dapat membuktikan pemilik akun yang sebenarnya.
- Mitigasi sementara bila terpaksa dimatikan: wajib ganti sandi (`wajib_ganti_sandi`) untuk akun admin, batas
  5 percobaan masuk/menit, kata sandi panjang, pantau log aktivitas, dan nyalakan kembali secepatnya.

## Rekomendasi

Biarkan `MFA_AKTIF=true` di produksi. Pakai `false` hanya di lingkungan lokal/uji atau sementara saat pemulihan
akses (mis. ponsel autentikator hilang dan kode pemulihan tidak tersedia), dicatat di `docs/OPERASIONAL.md`, lalu
kembalikan ke `true`.
