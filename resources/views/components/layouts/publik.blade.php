@props(['judul' => null, 'deskripsi' => null])
@php
    $namaSitus = 'Regulasi FKIP Unsil';
    $kategoriNavigasi = \App\Support\CacheRegulasi::master('kategori-sistem');
    $pengguna = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $judul ? $judul.' — '.$namaSitus : $namaSitus.' — Repositori Regulasi FKIP Universitas Siliwangi' }}</title>
    <meta name="description" content="{{ $deskripsi ?? 'Repositori dokumen hukum dan dokumen pendukung FKIP Universitas Siliwangi: peraturan, keputusan, surat edaran, SOP, dan pedoman.' }}">
    {{ $meta ?? '' }}
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="flex min-h-screen flex-col bg-slate-50 font-sans text-slate-800 antialiased">
<a href="#konten" class="sr-only z-50 rounded-md bg-white px-4 py-2 font-semibold text-indigo-900 shadow focus:not-sr-only focus:fixed focus:top-3 focus:left-3">
    Lewati ke konten utama
</a>

<header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur" x-data="{ menu: false }" @keydown.escape.window="menu = false">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-2.5">
        <a href="{{ route('beranda') }}" class="flex min-h-11 items-center gap-2.5 rounded-md text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700">
            <span class="grid size-9 place-items-center rounded-lg bg-linear-to-br from-indigo-800 to-teal-700 text-xs font-extrabold tracking-wide text-white" aria-hidden="true">§</span>
            <span class="leading-tight">
                <span class="block font-bold">{{ $namaSitus }}</span>
                <span class="block text-xs font-medium text-slate-600">Repositori dokumen hukum FKIP</span>
            </span>
        </a>

        <button type="button" class="inline-flex size-11 items-center justify-center rounded-lg text-slate-700 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-indigo-700 md:hidden"
                @click="menu = ! menu" :aria-expanded="menu.toString()" aria-expanded="false" aria-controls="navigasi-utama">
            <span class="sr-only">Buka/tutup menu</span>
            <svg x-show="! menu" class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
            <svg x-show="menu" x-cloak class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>

        <nav id="navigasi-utama" aria-label="Menu utama"
             class="absolute inset-x-0 top-full hidden border-b border-slate-200 bg-white px-4 pb-4 shadow-md md:static md:block md:border-0 md:p-0 md:shadow-none"
             :class="{ 'hidden': ! menu }">
            <ul class="flex flex-col gap-1 text-[0.95rem] md:flex-row md:items-center md:gap-1">
                <li>
                    <a href="{{ route('beranda') }}" @if (request()->routeIs('beranda')) aria-current="page" @endif
                       class="flex min-h-11 items-center rounded-md px-3 font-medium text-slate-700 hover:bg-slate-100 hover:text-indigo-800 focus-visible:outline-2 focus-visible:outline-indigo-700 aria-[current=page]:text-indigo-800 aria-[current=page]:underline aria-[current=page]:underline-offset-4">Beranda</a>
                </li>
                <li>
                    <a href="{{ route('dokumen.index') }}" @if (request()->routeIs('dokumen.index', 'dokumen.jenis', 'dokumen.show')) aria-current="page" @endif
                       class="flex min-h-11 items-center rounded-md px-3 font-medium text-slate-700 hover:bg-slate-100 hover:text-indigo-800 focus-visible:outline-2 focus-visible:outline-indigo-700 aria-[current=page]:text-indigo-800 aria-[current=page]:underline aria-[current=page]:underline-offset-4">Dokumen</a>
                </li>
                <li class="relative" x-data="{ buka: false }" @click.outside="buka = false" @keydown.escape="buka = false">
                    <button type="button" @click="buka = ! buka" :aria-expanded="buka.toString()" aria-expanded="false" aria-controls="menu-kategori"
                            class="flex min-h-11 w-full items-center justify-between gap-1 rounded-md px-3 font-medium text-slate-700 hover:bg-slate-100 hover:text-indigo-800 focus-visible:outline-2 focus-visible:outline-indigo-700 md:w-auto">
                        Kategori
                        <svg class="size-4 transition" :class="{ 'rotate-180': buka }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                    </button>
                    <ul id="menu-kategori" x-show="buka" x-cloak x-transition.origin.top
                        class="mt-1 grid gap-0.5 rounded-lg border border-slate-200 bg-white p-1.5 md:absolute md:right-0 md:mt-2 md:w-64 md:shadow-lg">
                        @foreach ($kategoriNavigasi as $kategori)
                            <li>
                                <a href="{{ route('dokumen.kategori', $kategori) }}"
                                   class="flex min-h-11 items-center rounded-md px-3 text-sm text-slate-700 hover:bg-indigo-50 hover:text-indigo-800 focus-visible:outline-2 focus-visible:outline-indigo-700">{{ $kategori->nama }}</a>
                            </li>
                        @endforeach
                    </ul>
                </li>
                <li class="mt-2 md:mt-0 md:ml-2">
                    @if ($pengguna)
                        <a href="{{ url('akun') }}" class="flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 px-3 font-medium text-slate-800 hover:border-indigo-700 hover:text-indigo-800 focus-visible:outline-2 focus-visible:outline-indigo-700">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                            <span class="max-w-40 truncate">{{ $pengguna->name }}</span>
                        </a>
                    @else
                        <a href="{{ route('filament.akun.auth.login') }}" class="flex min-h-11 items-center justify-center rounded-lg bg-indigo-800 px-4 font-semibold text-white hover:bg-indigo-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700">Masuk</a>
                    @endif
                </li>
            </ul>
        </nav>
    </div>
</header>

@isset($breadcrumb)
    <div class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-2.5">
            {{ $breadcrumb }}
        </div>
    </div>
@endisset

<main id="konten" tabindex="-1" class="flex-1 focus:outline-none">
    {{ $slot }}
</main>

<footer class="mt-12 border-t border-slate-200 bg-white">
    <div class="mx-auto grid max-w-6xl gap-6 px-4 py-8 text-sm text-slate-600 sm:grid-cols-2">
        <div>
            <p class="font-semibold text-slate-900">{{ $namaSitus }}</p>
            <p class="mt-1">Fakultas Keguruan dan Ilmu Pendidikan, Universitas Siliwangi</p>
            <address class="mt-1 not-italic">{{ config('regulasi.alamat') }}</address>
        </div>
        <div class="sm:text-right">
            <p>Bukan portal JDIH resmi. Naskah resmi mengacu pada sumber penerbitnya.</p>
            <p class="mt-1">Versi {{ config('regulasi.versi') }}</p>
        </div>
    </div>
</footer>
@livewireScripts
</body>
</html>
