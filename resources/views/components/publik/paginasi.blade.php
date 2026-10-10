{{-- Paginasi Livewire untuk portal publik (dipakai lewat $paginator->links('components.publik.paginasi')). --}}
@if ($paginator->hasPages())
    @php($nama = $paginator->getPageName())
    <nav role="navigation" aria-label="Navigasi halaman" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-600">
            Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}
        </p>
        <ul class="flex flex-wrap items-center gap-1">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm text-slate-400" aria-disabled="true">‹ Sebelumnya</span>
                @else
                    <button type="button" wire:click="previousPage('{{ $nama }}')" x-on:click="document.getElementById('hasil-pencarian')?.scrollIntoView({ behavior: 'smooth' })"
                            class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 hover:border-indigo-600 focus-visible:outline-2 focus-visible:outline-indigo-700">‹ Sebelumnya</button>
                @endif
            </li>
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="hidden sm:block"><span class="px-2 text-slate-500">{{ $element }}</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li class="hidden sm:block">
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg bg-indigo-800 px-3 text-sm font-semibold text-white">{{ $page }}</span>
                            @else
                                <button type="button" wire:click="gotoPage({{ $page }}, '{{ $nama }}')" x-on:click="document.getElementById('hasil-pencarian')?.scrollIntoView({ behavior: 'smooth' })" aria-label="Halaman {{ $page }}"
                                        class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 hover:border-indigo-600 focus-visible:outline-2 focus-visible:outline-indigo-700">{{ $page }}</button>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
            <li>
                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $nama }}')" x-on:click="document.getElementById('hasil-pencarian')?.scrollIntoView({ behavior: 'smooth' })"
                            class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 hover:border-indigo-600 focus-visible:outline-2 focus-visible:outline-indigo-700">Berikutnya ›</button>
                @else
                    <span class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm text-slate-400" aria-disabled="true">Berikutnya ›</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
