@props(['dokumen'])
{{-- Kartu ringkas dokumen di daftar hasil pencarian. Abstrak di-escape dan dipotong 200 karakter. --}}
<article class="rounded-xl border border-slate-200 bg-white p-4 transition hover:border-indigo-400 hover:shadow-sm sm:p-5">
    <div class="flex flex-wrap items-center gap-2">
        <h3 class="text-base font-bold text-slate-900">
            <a href="{{ url('dokumen/'.$dokumen->id.'/'.$dokumen->slug) }}" class="rounded-sm underline-offset-2 hover:text-indigo-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700">
                {{ $dokumen->sebutan_singkat }}
            </a>
        </h3>
        <x-publik.badge-status :status="$dokumen->status_berlaku" />
        @if ($dokumen->visibilitas === \App\Enums\Visibilitas::Internal)
            <span class="rounded-full bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-800 ring-1 ring-violet-600/30 ring-inset">Internal</span>
        @endif
        @if ($dokumen->tanpa_berkas)
            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 ring-1 ring-slate-500/30 ring-inset">
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z"/></svg>
                Metadata saja
            </span>
        @endif
    </div>
    <p class="mt-1.5 text-[0.95rem] text-slate-800">{{ $dokumen->judul }}</p>
    <p class="mt-1 text-sm text-slate-600">
        {{ $dokumen->jenisDokumen->nama }} · {{ $dokumen->badanPenetap->nama }} · {{ $dokumen->tahun }}
    </p>
    @if (filled($dokumen->abstrak))
        <p class="mt-2 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($dokumen->abstrak, 200) }}</p>
    @endif
</article>
