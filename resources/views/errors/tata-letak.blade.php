{{-- Halaman eror bertema landing. Mandiri (CSS statis public/css/regulasi.css, tanpa Vite) agar tetap tampil bila aset/sesi bermasalah;
     semua URL lewat url()/route()/asset() sehingga aman di subpath /regulasi. Tidak menampilkan pesan pengecualian. --}}
@php
    $kode = (int) ($kode ?? 500);
    $teks = trans('galat.'.$kode);
    $teks = is_array($teks) ? $teks : trans('galat.'.($kode >= 500 ? '5xx' : '4xx'));
    $bisaUlang = ($teks['ulang'] ?? false) && request()->isMethod('GET');
    $urlMasuk = \Illuminate\Support\Facades\Route::has('filament.akun.auth.login') ? route('filament.akun.auth.login') : url('akun/login');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $kode }} {{ $teks['judul'] }} — Regulasi FKIP Unsil</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/regulasi.css') }}">
</head>
<body class="r-galat">
<header class="r-galat__atas">
    <div class="r-wadah">
        <a class="r-merek" href="{{ url('/') }}">
            <span class="r-logo" aria-hidden="true">§</span>
            <span>Regulasi FKIP Unsil<small>Repositori dokumen hukum FKIP</small></span>
        </a>
    </div>
</header>
<main>
    <div class="r-galat__kotak">
        <p class="r-galat__kode">{{ $kode }}</p>
        <h1>{{ $teks['judul'] }}</h1>
        <p>{{ $teks['pesan'] }}</p>
        @if (! empty($teks['saran']))
            <ul class="r-galat__saran">
                @foreach ($teks['saran'] as $saran)
                    <li>{{ $saran }}</li>
                @endforeach
            </ul>
        @endif
        <div class="r-galat__aksi">
            @if ($bisaUlang)
                <a class="r-tombol r-tombol--teal" href="{{ request()->fullUrl() }}">Coba lagi</a>
            @endif
            @if ($kode === 401 || $kode === 419)
                <a class="r-tombol r-tombol--teal" href="{{ $urlMasuk }}">Masuk</a>
            @endif
            <button type="button" class="r-tombol r-tombol--garis" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/') }}')">Kembali</button>
            <a class="r-tombol" href="{{ url('/') }}">Ke beranda</a>
        </div>
        @if ($kode >= 500)
            <p class="r-galat__rujukan">Waktu kejadian: <code>{{ now()->timezone('Asia/Jakarta')->format('d-m-Y H:i:s') }} WIB</code></p>
        @endif
    </div>
</main>
<footer class="r-galat__kaki">
    <div class="r-wadah">Regulasi FKIP Universitas Siliwangi · <a href="{{ asset('panduan/index.html') }}">Panduan</a></div>
</footer>
</body>
</html>
