@php
    /** @var \App\Models\Dokumen $dokumen */
    $tanggal = fn (?\Carbon\CarbonInterface $t): ?string => $t?->translatedFormat('j F Y');
    $paragrafAbstrak = filled($dokumen->abstrak)
        ? array_values(array_filter(array_map('trim', preg_split('/\R+/u', (string) $dokumen->abstrak) ?: []), fn (string $p): bool => $p !== ''))
        : [];
    $metadata = [
        'Jenis' => $dokumen->jenisDokumen->nama,
        'Nomor' => $dokumen->nomor,
        'Tahun' => (string) $dokumen->tahun,
        'T.E.U. Badan' => $dokumen->badanPenetap->teu,
        'Tempat penetapan' => $dokumen->tempat_penetapan,
        'Tanggal penetapan' => $tanggal($dokumen->tanggal_penetapan),
        'Tanggal pengundangan' => $tanggal($dokumen->tanggal_pengundangan),
        'Tanggal berlaku' => $tanggal($dokumen->tanggal_berlaku),
    ];
    $bahasa = ['id' => 'Indonesia', 'en' => 'Inggris'][$dokumen->bahasa] ?? $dokumen->bahasa;
@endphp
<x-layouts.publik :judul="$dokumen->sebutan_singkat" :deskripsi="\Illuminate\Support\Str::limit($dokumen->abstrak ?: $dokumen->sebutan.' tentang '.$dokumen->judul, 160)">
    <x-slot:breadcrumb>
        <x-publik.breadcrumb :butir="[
            ['Beranda', route('beranda')],
            [$dokumen->jenisDokumen->nama, route('dokumen.jenis', $dokumen->jenisDokumen->slug)],
            [$dokumen->sebutan_singkat, null],
        ]" />
    </x-slot:breadcrumb>

    <article class="mx-auto max-w-6xl px-4 py-6 sm:py-8">
        <header>
            <div class="flex flex-wrap items-center gap-2">
                <x-publik.badge-status :status="$dokumen->status_berlaku" :besar="true" />
                @if ($dokumen->visibilitas === \App\Enums\Visibilitas::Internal)
                    <span class="rounded-full bg-violet-50 px-3 py-1 text-sm font-medium text-violet-800 ring-1 ring-violet-600/30 ring-inset">Internal FKIP</span>
                @endif
                <span class="text-sm text-slate-600">Dibuka {{ number_format($dokumen->jumlah_dibuka, 0, ',', '.') }} kali</span>
            </div>
            <h1 class="mt-3 text-2xl leading-snug font-bold text-slate-900 sm:text-3xl">
                {{ $dokumen->sebutan }} <span class="font-normal text-slate-600">tentang</span> {{ $dokumen->judul }}
            </h1>
            <p class="mt-3 text-[0.95rem] text-slate-700">{{ $kalimatStatus }}</p>
            @foreach ($tertunda as $kalimat)
                <p class="mt-1 text-sm text-amber-900">{{ $kalimat }}</p>
            @endforeach
        </header>

        <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_22rem]">
            <div class="min-w-0 space-y-8">
                {{-- Berkas --}}
                <section aria-labelledby="judul-berkas">
                    <h2 id="judul-berkas" class="text-lg font-bold text-slate-900">Naskah dan berkas</h2>
                    @if ($dokumen->tanpa_berkas || $dokumen->tautanBerkas->isEmpty())
                        <div class="mt-3 flex items-start gap-3 rounded-xl border border-dashed border-slate-300 bg-white p-4">
                            <svg class="mt-0.5 size-5 shrink-0 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/></svg>
                            <div>
                                <p class="font-semibold text-slate-900">Metadata saja — naskah belum tersedia</p>
                                <p class="mt-0.5 text-sm text-slate-600">
                                    {{ $dokumen->tanpa_berkas
                                        ? 'Dokumen ini dicatat sebagai rujukan. Naskah resmi dapat dicari di situs penerbitnya.'
                                        : 'Tautan naskah belum ditambahkan oleh pengelola.' }}
                                </p>
                            </div>
                        </div>
                    @else
                        <div class="mt-3 space-y-3">
                            <x-pratinjau-tautan :tautan="$naskahUtama" />
                            @foreach ($dokumen->tautanBerkas as $tautan)
                                <x-tautan-berkas :tautan="$tautan" :pratinjau="false" />
                            @endforeach
                        </div>
                    @endif
                </section>

                {{-- Abstrak --}}
                @if ($paragrafAbstrak !== [])
                    <section aria-labelledby="judul-abstrak">
                        <h2 id="judul-abstrak" class="text-lg font-bold text-slate-900">Abstrak</h2>
                        <div class="mt-3 space-y-3 rounded-xl border border-slate-200 bg-white p-4 leading-relaxed text-slate-800 sm:p-5">
                            @foreach ($paragrafAbstrak as $paragraf)
                                <p>{{ $paragraf }}</p>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Relasi --}}
                @if ($kelompokRelasi !== [])
                    <section aria-labelledby="judul-relasi">
                        <h2 id="judul-relasi" class="text-lg font-bold text-slate-900">Relasi</h2>
                        <div class="mt-3 space-y-4">
                            @foreach ($kelompokRelasi as $kelompok)
                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <h3 class="text-sm font-semibold tracking-wide text-slate-600 uppercase">{{ $kelompok['jenis']->label() }}</h3>
                                    <ul class="mt-2 divide-y divide-slate-100">
                                        @foreach ($kelompok['butir'] as $butir)
                                            <li class="py-2.5">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    @if ($butir['bolehDibuka'])
                                                        <a href="{{ route('dokumen.show', ['dokumen' => $butir['dokumen']->id, 'slug' => $butir['dokumen']->slug]) }}"
                                                           class="rounded-sm font-semibold text-indigo-800 underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-indigo-700">{{ $butir['dokumen']->sebutan_singkat }}</a>
                                                    @else
                                                        <span class="font-semibold text-slate-800">{{ $butir['dokumen']->sebutan_singkat }}</span>
                                                        <span class="text-xs text-slate-600">(internal — masuk untuk membuka)</span>
                                                    @endif
                                                    <x-publik.badge-status :status="$butir['dokumen']->status_berlaku" />
                                                    @unless ($butir['relasi']->efektif)
                                                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900 ring-1 ring-amber-600/30 ring-inset">
                                                            {{ $butir['relasi']->efektif_pada ? 'Tertunda hingga '.$tanggal($butir['relasi']->efektif_pada) : 'Tertunda' }}
                                                        </span>
                                                    @endunless
                                                </div>
                                                <p class="mt-0.5 text-sm text-slate-700">{{ $butir['dokumen']->judul }}</p>
                                                @if (filled($butir['relasi']->keterangan))
                                                    <p class="mt-0.5 text-xs text-slate-600">{{ $butir['relasi']->keterangan }}</p>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            {{-- Metadata --}}
            <aside aria-labelledby="judul-metadata">
                <h2 id="judul-metadata" class="text-lg font-bold text-slate-900">Metadata</h2>
                <dl class="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white px-4 text-sm">
                    @foreach ($metadata as $label => $nilai)
                        <div class="grid grid-cols-[9rem_1fr] gap-2 py-2.5">
                            <dt class="text-slate-600">{{ $label }}</dt>
                            <dd class="text-slate-900">{{ filled($nilai) ? $nilai : '—' }}</dd>
                        </div>
                    @endforeach
                    <div class="grid grid-cols-[9rem_1fr] gap-2 py-2.5">
                        <dt class="text-slate-600">Sumber</dt>
                        <dd class="text-slate-900">
                            {{ filled($dokumen->sumber) ? $dokumen->sumber : '—' }}
                            @if ($halamanSumber)
                                <a href="{{ route('tautan.buka', $halamanSumber) }}" target="_blank" rel="noopener noreferrer"
                                   class="mt-1 block text-indigo-800 underline underline-offset-2 hover:text-indigo-950">Halaman sumber resmi<span class="sr-only"> (tab baru)</span></a>
                            @endif
                        </dd>
                    </div>
                    <div class="grid grid-cols-[9rem_1fr] gap-2 py-2.5">
                        <dt class="text-slate-600">Kategori sistem</dt>
                        <dd>
                            <ul class="flex flex-wrap gap-1.5">
                                @forelse ($dokumen->kategoriSistem as $kategori)
                                    <li><a href="{{ route('dokumen.kategori', $kategori->slug) }}" class="inline-block rounded-md bg-teal-50 px-2 py-1 text-teal-900 hover:underline">{{ $kategori->nama }}</a></li>
                                @empty
                                    <li class="text-slate-900">—</li>
                                @endforelse
                            </ul>
                        </dd>
                    </div>
                    <div class="grid grid-cols-[9rem_1fr] gap-2 py-2.5">
                        <dt class="text-slate-600">Subjek</dt>
                        <dd>
                            <ul class="flex flex-wrap gap-1.5">
                                @forelse ($dokumen->tag as $tag)
                                    <li><a href="{{ route('dokumen.index').'?'.http_build_query(['q' => $tag->nama]) }}" class="inline-block rounded-md bg-slate-100 px-2 py-1 text-slate-800 hover:underline">{{ $tag->nama }}</a></li>
                                @empty
                                    <li class="text-slate-900">—</li>
                                @endforelse
                            </ul>
                        </dd>
                    </div>
                    <div class="grid grid-cols-[9rem_1fr] gap-2 py-2.5">
                        <dt class="text-slate-600">Bahasa</dt>
                        <dd class="text-slate-900">{{ $bahasa }}</dd>
                    </div>
                </dl>
            </aside>
        </div>
    </article>
</x-layouts.publik>
