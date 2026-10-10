# Berkas PDF peraturan (tidak di-commit)

Folder ini menampung salinan PDF peraturan untuk keperluan pengembangan lokal. Berkas `*.pdf` di sini masuk
`.gitignore` dan **tidak boleh di-commit**: sistem tidak menerima unggahan berkas, seeder hanya memakai tautan
(skema basis data §4.5).

Salin PDF peraturan dari repositori supportfkip ke folder ini dengan pola nama:

```
<kode-jenis>-<nomor>-<tahun>.pdf
```

- `nomor`: ganti "/" dengan "_", huruf kecil.
- Contoh: `perlembaga-13-2025.pdf`, `permen-181_pmk.06_2016-2016.pdf`.
