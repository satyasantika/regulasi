@php
    use App\Support\Laporan\PdfLaporanEvaluasi;
    use Carbon\CarbonImmutable;

    $angka = fn (mixed $n): string => number_format((int) $n, 0, ',', '.');
    $persen = fn (mixed $n): string => $n === null ? '—' : $n.'%';
    $tanggal = fn (?string $t): string => $t ? CarbonImmutable::parse($t)->translatedFormat('d F Y') : '—';
    $koleksi = $r['koleksi'] ?? [];
    $penambahan = $r['penambahan'] ?? [];
    $perubahan = $r['perubahan_status'] ?? [];
    $kelengkapan = $r['kelengkapan'] ?? [];
    $tautan = $r['kesehatan_tautan'] ?? [];
    $buka = $r['pembukaan'] ?? [];
    $api = $r['penggunaan_api'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $laporan->judul }}</title>
    <style>
        @page { margin: 2cm 2cm 2cm 2.5cm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5pt; line-height: 1.45; color: #111; }
        .kop { text-align: center; border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 16px; }
        .kop .univ { font-size: 13pt; font-weight: bold; text-transform: uppercase; }
        .kop .fak { font-size: 12pt; font-weight: bold; text-transform: uppercase; }
        .kop .sistem { font-size: 9pt; }
        h1 { font-size: 13pt; text-align: center; margin: 4px 0 2px; }
        .subjudul { text-align: center; margin-bottom: 14px; }
        .draf { text-align: center; color: #b45309; font-weight: bold; margin-bottom: 8px; }
        h2 { font-size: 11.5pt; margin: 16px 0 6px; }
        h3 { font-size: 10.5pt; margin: 10px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin: 4px 0 10px; }
        th, td { border: 1px solid #666; padding: 3px 5px; vertical-align: top; }
        th { background: #e5e7eb; text-align: left; }
        td.angka, th.angka { text-align: right; }
        .kecil { font-size: 8.5pt; color: #444; }
        .lampiran { page-break-before: always; }
        p { margin: 0 0 6px; text-align: justify; }
    </style>
</head>
<body>
    <div class="kop">
        <div class="univ">Universitas Siliwangi</div>
        <div class="fak">Fakultas Keguruan dan Ilmu Pendidikan</div>
        <div class="sistem">Regulasi FKIP Unsil — Repositori Dokumen Hukum dan Dokumen Pendukung</div>
    </div>

    <h1>{{ $laporan->judul }}</h1>
    <div class="subjudul">Periode {{ $laporan->periode_mulai->translatedFormat('d F Y') }} s.d. {{ $laporan->periode_selesai->translatedFormat('d F Y') }}</div>
    @if (! $laporan->adalahFinal())
        <div class="draf">DRAF — belum difinalkan</div>
    @endif

    <h2>BAB I PENDAHULUAN</h2>
    <p>Laporan ini menyajikan evaluasi pengelolaan repositori dokumen hukum dan dokumen pendukung Fakultas Keguruan dan Ilmu Pendidikan Universitas Siliwangi untuk periode {{ $laporan->periode_mulai->translatedFormat('d F Y') }} sampai dengan {{ $laporan->periode_selesai->translatedFormat('d F Y') }}.</p>
    <p>Cakupan laporan: koleksi dokumen terbit, penambahan dokumen dalam periode, perubahan status keberlakuan, kelengkapan pengolahan metadata, kesehatan tautan berkas, serta pemanfaatan (pembukaan tautan dan penggunaan API). Angka merupakan potret data pada {{ $tanggal($r['dihitung_pada'] ?? null) }}.</p>

    <h2>BAB II KOLEKSI</h2>
    <p>Jumlah dokumen terbit: <strong>{{ $angka($koleksi['total_terbit'] ?? 0) }}</strong> dokumen ({{ $angka($koleksi['publik'] ?? 0) }} publik, {{ $angka($koleksi['internal'] ?? 0) }} internal).</p>
    <table>
        <thead><tr><th>Status berlaku</th><th class="angka">Jumlah</th></tr></thead>
        <tbody>
            @foreach ($koleksi['per_status'] ?? [] as $baris)
                <tr><td>{{ $baris['status'] }}</td><td class="angka">{{ $angka($baris['jumlah']) }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <p>Penambahan dalam periode: <strong>{{ $angka($penambahan['jumlah'] ?? 0) }}</strong> dokumen dipublikasikan. Perubahan status dalam periode: {{ $angka($perubahan['dicabut'] ?? 0) }} dokumen menjadi dicabut dan {{ $angka($perubahan['diubah'] ?? 0) }} dokumen menjadi berlaku dengan perubahan. Rincian per jenis dan daftar perubahan status tercantum pada lampiran.</p>

    <h2>BAB III PENGOLAHAN DAN KELENGKAPAN</h2>
    <table>
        <thead><tr><th>Indikator (dokumen terbit)</th><th class="angka">Capaian</th></tr></thead>
        <tbody>
            <tr><td>Memiliki abstrak</td><td class="angka">{{ $persen($kelengkapan['persen_abstrak'] ?? null) }}</td></tr>
            <tr><td>Memiliki tautan naskah utama</td><td class="angka">{{ $persen($kelengkapan['persen_naskah_utama'] ?? null) }}</td></tr>
            <tr><td>Teks berhasil diekstraksi</td><td class="angka">{{ $persen($kelengkapan['persen_teks'] ?? null) }}</td></tr>
            <tr><td>Tautan berkas dapat diakses ({{ $angka($tautan['dapat_diakses'] ?? 0) }} dari {{ $angka($tautan['total'] ?? 0) }} tautan)</td><td class="angka">{{ $persen($tautan['persen_dapat_diakses'] ?? null) }}</td></tr>
        </tbody>
    </table>
    <p>Tautan tidak dapat diakses: {{ $angka($tautan['tidak_dapat_diakses'] ?? 0) }}; belum diperiksa: {{ $angka($tautan['belum'] ?? 0) }}.</p>

    <h2>BAB IV PEMANFAATAN</h2>
    <p>Dalam periode ini tautan berkas dibuka sebanyak <strong>{{ $angka($buka['total'] ?? 0) }}</strong> kali: {{ $angka($buka['web'] ?? 0) }} melalui portal web dan {{ $angka($buka['api'] ?? 0) }} melalui API sistem FKIP lain. Pratinjau sematan Google Drive tidak tercatat. Sepuluh dokumen terpopuler dan penggunaan API per klien tercantum pada lampiran.</p>

    <h2>BAB V KENDALA</h2>
    @forelse (PdfLaporanEvaluasi::paragraf($laporan->analisis) as $paragraf)
        <p>{{ $paragraf }}</p>
    @empty
        <p>Belum ada catatan kendala/temuan.</p>
    @endforelse

    <h2>BAB VI RENCANA TINDAK LANJUT</h2>
    @forelse (PdfLaporanEvaluasi::paragraf($laporan->tindak_lanjut) as $paragraf)
        <p>{{ $paragraf }}</p>
    @empty
        <p>Belum ada rencana tindak lanjut.</p>
    @endforelse

    <div class="lampiran">
        <h2>LAMPIRAN</h2>

        <h3>Lampiran 1. Koleksi dokumen terbit per jenis</h3>
        <table>
            <thead><tr><th>Jenis dokumen</th><th class="angka">Jumlah terbit</th><th class="angka">Penambahan periode</th></tr></thead>
            <tbody>
                @php($tambahPerJenis = collect($penambahan['per_jenis'] ?? [])->pluck('jumlah', 'nama'))
                @forelse ($koleksi['per_jenis'] ?? [] as $baris)
                    <tr><td>{{ $baris['nama'] }}</td><td class="angka">{{ $angka($baris['jumlah']) }}</td><td class="angka">{{ $angka($tambahPerJenis[$baris['nama']] ?? 0) }}</td></tr>
                @empty
                    <tr><td colspan="3">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h3>Lampiran 2. Perubahan status dalam periode</h3>
        <table>
            <thead><tr><th>Dokumen</th><th>Judul</th><th>Menjadi</th><th>Tanggal</th></tr></thead>
            <tbody>
                @forelse ($perubahan['daftar'] ?? [] as $baris)
                    <tr><td>{{ $baris['sebutan'] }}</td><td>{{ $baris['judul'] }}</td><td>{{ $baris['ke'] }}</td><td>{{ $tanggal($baris['tanggal']) }}</td></tr>
                @empty
                    <tr><td colspan="4">Tidak ada perubahan status.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h3>Lampiran 3. Sepuluh dokumen paling sering dibuka</h3>
        <table>
            <thead><tr><th>Dokumen</th><th>Judul</th><th class="angka">Web</th><th class="angka">API</th><th class="angka">Total</th></tr></thead>
            <tbody>
                @forelse ($buka['terpopuler'] ?? [] as $baris)
                    <tr><td>{{ $baris['sebutan'] }}</td><td>{{ $baris['judul'] }}</td><td class="angka">{{ $angka($baris['web']) }}</td><td class="angka">{{ $angka($baris['api']) }}</td><td class="angka">{{ $angka($baris['total']) }}</td></tr>
                @empty
                    <tr><td colspan="5">Tidak ada pembukaan tautan.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h3>Lampiran 4. Penggunaan API per klien</h3>
        <table>
            <thead><tr><th>Klien</th><th>Status</th><th class="angka">Tautan dibuka via API</th><th>Terakhir dipakai</th></tr></thead>
            <tbody>
                @forelse ($api as $baris)
                    <tr><td>{{ $baris['nama'] }}</td><td>{{ $baris['aktif'] ? 'Aktif' : 'Nonaktif' }}</td><td class="angka">{{ $angka($baris['dibuka_api']) }}</td><td>{{ $tanggal($baris['terakhir_dipakai_pada']) }}</td></tr>
                @empty
                    <tr><td colspan="4">Belum ada klien API.</td></tr>
                @endforelse
            </tbody>
        </table>

        <p class="kecil">Dicetak {{ $dicetakPada->translatedFormat('d F Y H:i') }} WIB dari Regulasi FKIP Unsil. Status laporan: {{ $laporan->status->getLabel() }}@if ($laporan->difinalkan_pada), difinalkan {{ $laporan->difinalkan_pada->translatedFormat('d F Y H:i') }} WIB @endif.</p>
    </div>
</body>
</html>
