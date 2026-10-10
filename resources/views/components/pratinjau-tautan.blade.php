<div class="pratinjau-tautan overflow-hidden rounded-xl border border-slate-200 bg-white">
    @if ($urlPratinjau)
        <div x-data="{ tampil: false }">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-sm text-slate-700">Pratinjau naskah dari {{ $penyedia }}</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" x-show="! tampil" @click="tampil = true"
                            class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-indigo-700 px-4 text-sm font-semibold text-indigo-800 hover:bg-indigo-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                        Tampilkan pratinjau
                    </button>
                    <a href="{{ $urlBuka }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex min-h-11 items-center rounded-lg bg-indigo-800 px-4 text-sm font-semibold text-white hover:bg-indigo-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700">
                        Buka berkas<span class="sr-only"> di {{ $penyedia }} (tab baru)</span>
                    </a>
                </div>
            </div>
            <template x-if="tampil">
                <div>
                    <iframe src="{{ $urlPratinjau }}" title="Pratinjau naskah" loading="lazy" referrerpolicy="no-referrer"
                            allow="autoplay" class="block h-[70vh] min-h-96 w-full bg-slate-100 sm:h-[80vh]"></iframe>
                    <p class="border-t border-slate-200 px-4 py-2 text-xs text-slate-600">
                        Pratinjau kosong atau meminta masuk Google? Gunakan tombol <strong>Buka berkas</strong>.
                    </p>
                </div>
            </template>
        </div>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-4">
            <p class="text-sm text-slate-700">Naskah tersedia di situs sumber resmi pemerintah.</p>
            <a href="{{ $urlBuka }}" target="_blank" rel="noopener noreferrer"
               class="inline-flex min-h-11 items-center rounded-lg bg-indigo-800 px-4 text-sm font-semibold text-white hover:bg-indigo-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700">
                Buka di situs sumber<span class="sr-only"> (tab baru)</span>
            </a>
        </div>
    @endif
</div>
