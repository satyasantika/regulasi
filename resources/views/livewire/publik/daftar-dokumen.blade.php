<div>
    <x-slot:breadcrumb>
        <x-publik.breadcrumb :butir="$breadcrumb" />
    </x-slot:breadcrumb>

    <div class="mx-auto max-w-6xl px-4 py-6 sm:py-8" x-data="{ filter: false }">
        <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl">{{ $judul }}</h1>

        {{-- Kotak pencarian --}}
        <form wire:submit.prevent class="mt-4" role="search">
            <label for="cari-dokumen" class="sr-only">Cari dokumen</label>
            <div class="relative">
                <svg class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                <input id="cari-dokumen" type="search" maxlength="200" autocomplete="off" wire:model.live.debounce.400ms="q"
                       placeholder="Cari judul, nomor, atau isi naskah"
                       class="h-12 w-full rounded-xl border border-slate-300 bg-white pr-3 pl-10 text-base text-slate-900 placeholder:text-slate-500 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600/30 focus:outline-none">
            </div>
        </form>

        @if ($hasil->dikoreksiDari !== null)
            <p class="mt-2 text-sm text-slate-700" role="status">
                Menampilkan hasil untuk <strong>{{ $hasil->kueriDipakai }}</strong>.
                <button type="button" wire:click="$set('persis', true)" class="font-medium text-indigo-700 underline hover:text-indigo-900">Cari persis &ldquo;{{ $hasil->dikoreksiDari }}&rdquo;</button>
            </p>
        @endif

        <div class="mt-6 grid gap-6 md:grid-cols-[16rem_1fr] lg:grid-cols-[18rem_1fr]">
            {{-- Filter --}}
            <aside aria-label="Filter">
                <button type="button" class="flex min-h-11 w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-4 font-medium text-slate-800 focus-visible:outline-2 focus-visible:outline-indigo-700 md:hidden"
                        @click="filter = ! filter" :aria-expanded="filter.toString()" aria-expanded="false" aria-controls="panel-filter">
                    <span>Filter ({{ $jumlahFilter }})</span>
                    <svg class="size-5 transition" :class="{ 'rotate-180': filter }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                </button>

                <div id="panel-filter" class="mt-3 hidden space-y-5 md:mt-0 md:block" :class="{ 'hidden': ! filter }">
                    @forelse ($faset as $dimensi => $grup)
                        <fieldset wire:key="faset-{{ $dimensi }}" class="rounded-xl border border-slate-200 bg-white p-4">
                            <legend class="float-left mb-2 w-full text-sm font-semibold text-slate-900">{{ $grup['legend'] }}</legend>
                            <ul class="clear-both max-h-72 space-y-0.5 overflow-y-auto">
                                @foreach ($grup['opsi'] as $nilai => $opsi)
                                    <li wire:key="opsi-{{ $dimensi }}-{{ $nilai }}">
                                        <label class="flex min-h-11 cursor-pointer items-center gap-2.5 rounded-md px-1.5 text-sm text-slate-700 hover:bg-slate-50 md:min-h-9">
                                            <input type="checkbox" value="{{ $nilai }}" wire:model.live="{{ $grup['properti'] }}"
                                                   class="size-4 rounded border-slate-400 accent-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700">
                                            <span class="flex-1">{{ $opsi['label'] }}</span>
                                            <span class="text-xs tabular-nums text-slate-600">{{ $opsi['jumlah'] }}</span>
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        </fieldset>
                    @empty
                        <p class="text-sm text-slate-600">Tidak ada filter tersedia.</p>
                    @endforelse

                    @if ($jumlahFilter > 0 || $q !== '')
                        <button type="button" wire:click="hapusSemuaFilter"
                                class="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 hover:border-rose-500 hover:text-rose-700 focus-visible:outline-2 focus-visible:outline-indigo-700">
                            Hapus semua filter
                        </button>
                    @endif
                </div>
            </aside>

            {{-- Hasil --}}
            <section id="hasil-pencarian" aria-labelledby="jumlah-hasil" class="min-w-0 scroll-mt-24">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="jumlah-hasil" class="text-base font-semibold text-slate-900" aria-live="polite" aria-atomic="true">
                        <span wire:loading.remove>{{ number_format($hasil->dokumen->total(), 0, ',', '.') }} dokumen</span>
                        <span wire:loading>Mencari…</span>
                    </h2>
                    <div class="flex items-center gap-2">
                        <label for="urut-dokumen" class="text-sm text-slate-600">Urutkan</label>
                        <select id="urut-dokumen" wire:model.live="urut"
                                class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600/30 focus:outline-none">
                            @foreach ($urutan as $nilai => $label)
                                <option value="{{ $nilai }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if ($filterAktif !== [])
                    <ul class="mt-3 flex flex-wrap gap-2" aria-label="Filter aktif">
                        @foreach ($filterAktif as $f)
                            <li wire:key="chip-{{ $f['properti'] }}-{{ $f['nilai'] }}">
                                <button type="button" wire:click="hapusFilter('{{ $f['properti'] }}', @js($f['nilai']))"
                                        class="inline-flex min-h-11 items-center gap-1.5 rounded-full bg-indigo-50 sm:min-h-9 px-3 text-sm text-indigo-900 ring-1 ring-indigo-600/30 ring-inset hover:bg-indigo-100 focus-visible:outline-2 focus-visible:outline-indigo-700">
                                    {{ $f['label'] }}
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                    <span class="sr-only">(hapus filter)</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($dibatasi)
                    <div role="alert" class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                        Terlalu banyak permintaan pencarian dalam satu menit. Tunggu sebentar lalu coba lagi.
                    </div>
                @endif

                <div class="mt-4 space-y-3" wire:loading.class="opacity-60">
                    @forelse ($hasil->dokumen as $dokumen)
                        <div wire:key="dokumen-{{ $dokumen->id }}">
                            <x-publik.kartu-dokumen :dokumen="$dokumen" />
                        </div>
                    @empty
                        @unless ($dibatasi)
                            <div class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">
                                <p class="font-medium text-slate-800">Tidak ada dokumen yang cocok.</p>
                                <p class="mt-1 text-sm">Coba kata kunci lain atau kurangi filter.</p>
                            </div>
                        @endunless
                    @endforelse
                </div>

                <div class="mt-6">
                    {{ $hasil->dokumen->links('components.publik.paginasi') }}
                </div>
            </section>
        </div>
    </div>
</div>
