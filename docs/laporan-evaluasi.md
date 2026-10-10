# Laporan Evaluasi Periode (PDF)

Fitur F11.4 (US-LAP-03, PRD §8, 03-SKEMA §3.15). Menu **Laporan → Laporan Evaluasi** di panel admin.

> Struktur bab di bawah mengikuti semangat standar laporan evaluasi Permenkumham 8/2019 tentang Standar
> Pengelolaan Dokumen dan Informasi Hukum. **(Sesuaikan dengan standar laporan evaluasi Permenkumham 8/2019 —
> perlu verifikasi.)** Struktur bab persis lampiran peraturan tersebut belum diverifikasi terhadap naskah resmi;
> catatan ini sengaja tidak dicetak di PDF.

## Alur

1. **Buat** (pemegang `laporan.ekspor` dengan lingkup global: admin-regulasi, super-admin): isi judul (mis.
   "Laporan Evaluasi Semester Ganjil 2026/2027"), periode mulai–selesai, kendala/temuan (Bab V), dan rencana
   tindak lanjut (Bab VI). Snapshot `ringkasan` langsung dihitung.
2. **Hitung ringkasan** (hanya draf): menghitung ulang snapshot dari data saat ini (mis. setelah periode diubah).
3. **Finalkan**: status `final`, `difinalkan_pada` terisi; laporan tidak dapat diubah, dihapus, atau dihitung
   ulang lagi (dikunci di model, berlaku juga bagi super-admin).
4. **Unduh PDF** (`/laporan-evaluasi/{id}/pdf`): dirender ulang dari snapshot oleh dompdf (A4 portrait) lalu
   di-stream. PDF **tidak disimpan** di server (STANDAR-TEKNIS §1a.6). Laporan final selalu menghasilkan angka
   yang sama.
5. Arsipkan PDF final ke Shared Drive **"Regulasi FKIP"**; bila perlu catat sebagai dokumen jenis **LAINNYA**
   dengan tautan Drive tersebut.

Kontributor tidak dapat melihat, membuat, maupun mengunduh laporan evaluasi karena laporan memuat angka global
(kontributor hanya melihat statistik kategori yang ditugaskan).

## Isi snapshot `ringkasan`

| Kunci | Isi |
|---|---|
| `koleksi` | total terbit (publik/internal), per jenis, per status berlaku |
| `penambahan` | dokumen yang pertama kali dipublikasikan dalam periode, per jenis |
| `perubahan_status` | dokumen yang menjadi dicabut/diubah dalam periode (dari riwayat status) |
| `kelengkapan` | % dokumen terbit berabstrak, bertautan naskah utama, berteks terekstraksi |
| `kesehatan_tautan` | tautan dokumen terbit: dapat diakses / tidak / belum diperiksa, % dapat diakses |
| `pembukaan` | pembukaan tautan dalam periode per saluran (web/API) + 10 terpopuler |
| `penggunaan_api` | per klien API: tautan dibuka via API dalam periode, terakhir dipakai |

## Struktur PDF

Kop "Universitas Siliwangi — Fakultas Keguruan dan Ilmu Pendidikan", lalu:

- BAB I Pendahuluan (periode, cakupan)
- BAB II Koleksi
- BAB III Pengolahan dan Kelengkapan
- BAB IV Pemanfaatan
- BAB V Kendala (isian `analisis`)
- BAB VI Rencana Tindak Lanjut (isian `tindak_lanjut`)
- Lampiran: koleksi per jenis, perubahan status, 10 terpopuler, penggunaan API
