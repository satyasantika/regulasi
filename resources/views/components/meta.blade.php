@props(['judul', 'deskripsi', 'canonical' => null, 'noindex' => false, 'tipe' => 'website'])
{{-- Metadata halaman portal: description, canonical, Open Graph dasar, dan robots (F8.4). --}}
<title>{{ $judul }}</title>
<meta name="description" content="{{ $deskripsi }}">
@if ($noindex)
    <meta name="robots" content="noindex, nofollow">
@endif
@if ($canonical)
    <link rel="canonical" href="{{ $canonical }}">
@endif
<meta property="og:site_name" content="Regulasi FKIP Unsil">
<meta property="og:locale" content="id_ID">
<meta property="og:type" content="{{ $tipe }}">
<meta property="og:title" content="{{ $judul }}">
<meta property="og:description" content="{{ $deskripsi }}">
@if ($canonical)
    <meta property="og:url" content="{{ $canonical }}">
@endif
