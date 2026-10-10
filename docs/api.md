# API Baca Regulasi FKIP — v1

API ini menyediakan metadata dokumen hukum dan dokumen pendukung Regulasi FKIP Universitas Siliwangi untuk
sistem FKIP lain (Aset/BMN, Akreditasi, SDM, Persuratan, LMS, dll.). API **hanya baca** (`GET`), berformat
[JSON:API 1.1](https://jsonapi.org/format/), dan hanya memuat dokumen berstatus **terbit**.

| Lingkungan | URL dasar |
|---|---|
| Produksi | `https://supportfkip.unsil.ac.id/regulasi/api/v1` |
| Lokal (pengembang) | `http://localhost:8034/regulasi/api/v1` |

Contoh di bawah memakai variabel shell:

```bash
export REGULASI=http://localhost:8034/regulasi/api/v1
export TOKEN='01a1...|xxxxxxxx'   # token polos dari super-admin
```

Gunakan `curl -g` agar tanda kurung siku pada `filter[...]`/`page[...]` tidak ditafsirkan sebagai pola oleh curl.

## 1. Autentikasi

Setiap permintaan wajib membawa token Bearer:

```
Authorization: Bearer <token>
Accept: application/vnd.api+json
```

### Cara mendapatkan token

1. Hubungi **super-admin** Regulasi FKIP (pengelola sistem) dengan menyebut nama sistem, kategori sistem, nama dan
   surel penanggung jawab teknis, serta apakah sistem perlu membaca **dokumen internal**.
2. Super-admin mendaftarkan sistem Anda di panel **Admin → Sistem → Klien API**, lalu memilih **Buat token**:
   nama token (mis. `Produksi`), centang *Boleh membaca dokumen internal* bila disetujui, dan masa berlaku
   90/180/365 hari.
3. Token polos ditampilkan **sekali saja**. Simpan di konfigurasi rahasia sistem Anda (mis. `.env`), jangan di repositori
   atau kode sisi peramban.
4. Sebelum kedaluwarsa, minta token baru lalu minta token lama dicabut. Token yang dicabut atau kedaluwarsa, serta semua
   token milik klien yang **dinonaktifkan**, langsung ditolak `401`.

### Abilities

| Ability | Wajib | Arti |
|---|---|---|
| `dokumen:baca` | ya | Membaca dokumen **publik** yang terbit, jenis dokumen, dan kategori sistem |
| `dokumen:baca-internal` | tidak | Juga membaca dokumen **internal** yang terbit (BR-02). Tautan berkasnya diberikan sebagai URL bertanda tangan 10 menit |

Dokumen draf, menunggu verifikasi, ditolak, atau diarsipkan **tidak pernah** muncul di API (BR-01).

## 2. Endpoint

| Metode & path | Fungsi |
|---|---|
| `GET /dokumen` | Daftar dokumen dengan filter, pencarian, urutan, paginasi |
| `GET /dokumen/{id}` | Detail satu dokumen (mendukung ETag) |
| `GET /jenis-dokumen` | Jenis dokumen aktif + jumlah dokumen |
| `GET /kategori-sistem` | Kategori sistem aktif + jumlah dokumen |

`id` setiap sumber daya adalah **UUIDv7** (36 karakter) — sama dengan yang dipakai URL portal
`/dokumen/{id}/{slug}`. Simpan `id`, bukan nomor atau slug, bila sistem Anda perlu merujuk dokumen.

### 2.1 `GET /dokumen`

| Parameter | Contoh | Keterangan |
|---|---|---|
| `filter[kategori]` | `aset` atau `aset,sdm` | Kode kategori sistem; koma = ATAU |
| `filter[status]` | `berlaku` | Status berlaku: `berlaku`, `diubah`, `dicabut`, `tidak_berlaku` (koma = ATAU) |
| `filter[jenis]` | `PERMEN,PP` | Kode jenis dokumen (lihat `/jenis-dokumen`) |
| `filter[tahun]` | `2016,2020` | Tahun dokumen |
| `filter[badan]` | `kementerian-keuangan` | Slug badan penetap |
| `q` | `barang milik negara` | Kata kunci (judul, sebutan, abstrak, nomor, isi teks PDF); maks. 200 karakter |
| `sort` | `-tahun` | `tahun`, `-tahun`, `tanggal_penetapan`, `-dipublikasikan_pada`. Bawaan `-tahun`; bila ada `q` tanpa `sort`, urut menurut relevansi |
| `page[number]` | `2` | Nomor halaman, mulai 1 |
| `page[size]` | `50` | Jumlah per halaman, bawaan 20, **maksimal 100** (nilai lebih besar dibatasi 100) |
| `include` | `kategori,relasi,tautan` | Sertakan relasi di `included` |

Alias sederhana juga diterima: `?kategori=aset&status=berlaku` sama dengan
`?filter[kategori]=aset&filter[status]=berlaku`.

```bash
curl -g -H "Authorization: Bearer $TOKEN" -H "Accept: application/vnd.api+json" \
  "$REGULASI/dokumen?filter[kategori]=aset&filter[status]=berlaku&include=kategori"
```

Respons (dipendekkan):

```json
{
  "data": [
    {
      "id": "01a1260b-5ed7-70d7-937f-5f1c2da36e51",
      "type": "dokumen",
      "attributes": {
        "sebutan": "Peraturan Menteri Keuangan Nomor 181/PMK.06/2016 Tahun 2016",
        "sebutan_singkat": "PMK 181/2016",
        "jenis": { "kode": "PERMEN", "nama": "Peraturan Menteri" },
        "nomor": "181/PMK.06/2016",
        "tahun": 2016,
        "judul": "Penatausahaan Barang Milik Negara",
        "badan_penetap": { "nama": "Kementerian Keuangan", "teu": "Indonesia. Kementerian Keuangan" },
        "tempat_penetapan": "Jakarta",
        "tanggal_penetapan": "2016-11-21",
        "tanggal_pengundangan": "2016-11-21",
        "tanggal_berlaku": null,
        "status_berlaku": "berlaku",
        "visibilitas": "publik",
        "abstrak": "…",
        "tanpa_berkas": false,
        "dipublikasikan_pada": "2026-10-10T20:40:41+07:00"
      },
      "relationships": {
        "kategori": { "data": [ { "id": "01a1260b-59b2-…", "type": "kategori-sistem" } ] }
      },
      "links": {
        "self": "http://localhost:8034/regulasi/api/v1/dokumen/01a1260b-5ed7-70d7-937f-5f1c2da36e51",
        "html": "http://localhost:8034/regulasi/dokumen/01a1260b-5ed7-70d7-937f-5f1c2da36e51/pmk-181-2016-…"
      }
    }
  ],
  "included": [
    {
      "id": "01a1260b-59b2-…",
      "type": "kategori-sistem",
      "attributes": { "kode": "aset", "nama": "Aset/BMN", "slug": "aset", "deskripsi": null, "ikon": "heroicon-o-building-office-2" },
      "links": { "html": "http://localhost:8034/regulasi/kategori/aset" }
    }
  ],
  "meta": {
    "total": 2,
    "page": { "number": 1, "size": 20, "last": 1 },
    "faset": { "jenis": { "PERMEN": 1, "PP": 1 }, "tahun": { "2020": 1, "2016": 1 }, "status_berlaku": { "berlaku": 2 }, "kategori": { "aset": 2 }, "badan_penetap": { "…": 1 } }
  },
  "links": {
    "self": "…/api/v1/dokumen?filter%5Bkategori%5D=aset&page%5Bnumber%5D=1&page%5Bsize%5D=20",
    "first": "…", "last": "…", "next": "… (bila ada)", "prev": "… (bila ada)"
  }
}
```

Nilai tanggal `Y-m-d`; `dipublikasikan_pada` ISO 8601 zona Asia/Jakarta. `meta.faset` berisi jumlah per nilai filter
untuk hasil saat ini (berguna untuk membangun filter di sistem Anda).

### 2.2 `GET /dokumen/{id}`

Parameter `include` sama dengan daftar. Dokumen yang tidak terbit, terhapus, atau internal tanpa ability
`dokumen:baca-internal` → `404`.

```bash
curl -g -H "Authorization: Bearer $TOKEN" "$REGULASI/dokumen/01a1260b-5ef0-73f2-9e5a-99f1bc7ac0b1?include=relasi,tautan"
```

```json
{
  "data": {
    "id": "01a1260b-5ef0-73f2-9e5a-99f1bc7ac0b1",
    "type": "dokumen",
    "attributes": { "sebutan_singkat": "Permendikbud 3/2021", "status_berlaku": "dicabut", "…": "…" },
    "relationships": {
      "relasi": { "data": [ { "id": "01a1260b-62b6-…", "type": "relasi" } ] },
      "tautan": { "data": [ { "id": "01a12633-a9eb-…", "type": "tautan" } ] }
    },
    "links": { "self": "…", "html": "…" }
  },
  "included": [
    {
      "id": "01a1260b-62b6-…",
      "type": "relasi",
      "attributes": {
        "jenis_relasi": "dicabut_oleh",
        "efektif": true,
        "efektif_pada": "2025-10-15T00:00:00+07:00",
        "keterangan": null,
        "dokumen_terkait": { "id": "01a1260b-6147-…", "sebutan_singkat": "Permendiktisaintek 42/2025", "status_berlaku": "berlaku" }
      },
      "links": { "dokumen_terkait": "…/api/v1/dokumen/01a1260b-6147-…" }
    },
    {
      "id": "01a12633-a9eb-…",
      "type": "tautan",
      "attributes": { "label": "Naskah PDF", "jenis": "naskah_utama", "penyedia": "google_drive", "status_cek": "dapat_diakses", "jumlah_halaman": 24 },
      "links": { "buka": "http://localhost:8034/regulasi/d/01a12633-a9eb-…/buka" }
    }
  ]
}
```

- `relasi.jenis_relasi` dibaca dari sisi dokumen ini: `mencabut`, `dicabut_oleh`, `mengubah`, `diubah_oleh`,
  `melaksanakan`, `dilaksanakan_oleh`. `efektif = false` berarti relasi tercatat tetapi tanggal efektifnya belum tiba.
  Relasi ke dokumen yang belum terbit (atau internal tanpa ability) tidak disertakan.
- `tautan.links.buka` **bukan** URL berkas mentah:
  - dokumen publik → `/d/{id}/buka` (permanen; dialihkan ke berkas dan dicatat),
  - dokumen internal → URL bertanda tangan `/d/{id}/buka-api?expires=…&klien=…&signature=…` yang **kedaluwarsa
    10 menit** setelah respons dibuat. Jangan disimpan; minta ulang detail dokumen saat pengguna akan membuka berkas.
    Pembukaan dicatat atas nama klien API Anda.

**ETag/Last-Modified.** Respons detail membawa `ETag` (lemah) dan `Last-Modified`. Kirim ulang dengan
`If-None-Match: <ETag>` (atau `If-Modified-Since`) → `304 Not Modified` tanpa isi bila dokumen tidak berubah.

```bash
curl -g -i -H "Authorization: Bearer $TOKEN" -H 'If-None-Match: W/"6f1c…"' "$REGULASI/dokumen/01a1260b-…"
```

### 2.3 `GET /jenis-dokumen` dan `GET /kategori-sistem`

Hanya master yang aktif, terurut menurut urutan tampilan. `meta.jumlah_dokumen` = jumlah dokumen terbit yang
terlihat oleh token Anda.

```bash
curl -H "Authorization: Bearer $TOKEN" "$REGULASI/kategori-sistem"
```

```json
{
  "data": [
    {
      "id": "01a1260b-59a8-71d7-bad6-57df3e20d3e1",
      "type": "kategori-sistem",
      "attributes": { "kode": "akreditasi", "nama": "Akreditasi", "slug": "akreditasi", "deskripsi": null, "ikon": "heroicon-o-check-badge" },
      "links": { "html": "http://localhost:8034/regulasi/kategori/akreditasi" },
      "meta": { "jumlah_dokumen": 20 }
    }
  ],
  "meta": { "total": 10 }
}
```

`/jenis-dokumen` serupa dengan `type: "jenis-dokumen"` dan atribut `kode`, `nama`, `singkatan`, `slug`, `kelompok`
(`peraturan`, `keputusan`, `kebijakan`, `pendukung`), `deskripsi`.

## 3. Kode galat

Semua galat berformat JSON:API dengan `Content-Type: application/vnd.api+json`:

```json
{ "errors": [ { "status": "422", "title": "Parameter tidak valid", "detail": "sort harus salah satu dari: tahun, -tahun, tanggal_penetapan, -dipublikasikan_pada.", "source": { "parameter": "sort" } } ] }
```

| Status | `title` | Penyebab |
|---|---|---|
| 401 | `Tidak terautentikasi` | Token tidak ada, salah, dicabut, atau kedaluwarsa |
| 401 | `Klien API tidak aktif` | Klien pemilik token dinonaktifkan super-admin |
| 403 | `Akses ditolak` | Token tidak memiliki ability `dokumen:baca` |
| 404 | `Tidak ditemukan` | Dokumen tidak ada, belum terbit, atau internal tanpa ability `dokumen:baca-internal` |
| 405 | `Metode tidak diizinkan` | Metode selain `GET`/`HEAD` |
| 422 | `Parameter tidak valid` | `sort`/`include`/`page[...]`/`filter[tahun]` tidak valid (lihat `source.parameter`) |
| 429 | `Terlalu banyak permintaan` | Melebihi batas laju; tunggu sesuai header `Retry-After` |

## 4. Batas laju (rate limit)

- **60 permintaan per menit per token** (BR-18). Setiap respons membawa `X-RateLimit-Limit` dan
  `X-RateLimit-Remaining`; respons `429` juga membawa `Retry-After` (detik) dan `X-RateLimit-Reset`.
- Token berbeda memiliki kuota terpisah. Jangan membagi satu token ke beberapa sistem.
- Manfaatkan cache sisi klien dan `ETag` agar tidak menghabiskan kuota.

## 5. Kebijakan versi

- `v1` **stabil**: atribut dan parameter yang ada tidak dihapus atau diubah artinya. Penambahan atribut, `meta`,
  filter, atau nilai enum baru dapat terjadi tanpa pemberitahuan — klien wajib mengabaikan field yang tidak dikenal.
- Perubahan yang merusak (menghapus/mengganti nama field, mengubah arti nilai) hanya dirilis sebagai **`/api/v2`**;
  `v1` tetap berjalan berdampingan sampai pengumuman penghentian kepada semua klien terdaftar.
- Riwayat perubahan dicatat di `CHANGELOG.md`.

## 6. Contoh pemakaian: halaman "Dasar Hukum" di Sistem Aset

Sistem Aset menampilkan daftar dasar hukum pengelolaan BMN yang selalu mutakhir, dengan cache 1 jam di sisi klien
agar tidak membebani API (contoh Laravel):

```php
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

$dasarHukum = Cache::remember('regulasi:dasar-hukum-aset', now()->addHour(), function (): array {
    $respons = Http::withToken(config('services.regulasi.token'))
        ->accept('application/vnd.api+json')
        ->timeout(10)
        ->get(config('services.regulasi.url').'/api/v1/dokumen', [
            'filter' => ['kategori' => 'aset', 'status' => 'berlaku'],
            'sort' => '-tahun',
            'page' => ['size' => 100],
        ]);

    if ($respons->failed()) {
        // Pakai salinan terakhir bila API sedang tidak tersedia.
        return Cache::get('regulasi:dasar-hukum-aset:cadangan', []);
    }

    $hasil = collect($respons->json('data'))->map(fn (array $d): array => [
        'sebutan' => $d['attributes']['sebutan_singkat'],
        'judul' => $d['attributes']['judul'],
        'tahun' => $d['attributes']['tahun'],
        'tautan' => $d['links']['html'],   // halaman detail di portal Regulasi FKIP
    ])->all();

    Cache::forever('regulasi:dasar-hukum-aset:cadangan', $hasil);

    return $hasil;
});
```

```blade
<h2>Dasar Hukum</h2>
<ul>
    @foreach ($dasarHukum as $dokumen)
        <li><a href="{{ $dokumen['tautan'] }}" target="_blank" rel="noopener">{{ $dokumen['sebutan'] }}</a> — {{ $dokumen['judul'] }}</li>
    @endforeach
</ul>
```

Catatan: arahkan pengguna ke `links.html` (portal) untuk membaca dokumen; jangan menyalin isi atau URL berkas ke
basis data sistem Anda. Pemeriksaan kesehatan layanan tersedia di `GET /api/health` (tanpa token).
