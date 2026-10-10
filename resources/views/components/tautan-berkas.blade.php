<div class="tautan-berkas flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
    <div class="min-w-0">
        <p class="tautan-berkas__judul font-semibold text-slate-900">{{ $judul }}</p>
        <p class="mt-0.5 text-sm text-slate-600">
            {{ $tautan->jenis->getLabel() }} · {{ $tautan->penyedia->getLabel() }}{{ $jumlahHalaman ? ' · '.$jumlahHalaman.' halaman' : '' }}
        </p>
    </div>
    <a href="{{ $urlBuka }}" target="_blank" rel="noopener noreferrer"
       class="tautan-berkas__buka inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-indigo-800 px-4 text-sm font-semibold text-white hover:bg-indigo-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700">
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
        {{ $berhak ? 'Buka berkas' : 'Masuk untuk membuka berkas' }}
        <span class="sr-only">{{ $judul }} di {{ $penyedia }} (tab baru)</span>
    </a>
    @if ($urlPratinjau)
        <details class="tautan-berkas__pratinjau w-full sm:basis-full">
            <summary class="cursor-pointer text-sm font-medium text-indigo-800">Pratinjau</summary>
            <iframe src="{{ $urlPratinjau }}" title="Pratinjau {{ $judul }}" loading="lazy" referrerpolicy="no-referrer"
                    width="100%" height="640" allow="autoplay" class="mt-2 rounded-lg border border-slate-200"></iframe>
        </details>
    @endif
</div>
