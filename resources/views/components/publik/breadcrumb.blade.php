@props(['butir' => []])
{{-- Breadcrumb: daftar [label, url|null]; butir terakhir adalah halaman saat ini. --}}
<nav aria-label="Breadcrumb">
    <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm text-slate-600">
        @foreach ($butir as $i => [$label, $url])
            <li class="flex min-w-0 items-center gap-1.5">
                @if ($i > 0)
                    <span aria-hidden="true" class="text-slate-400">›</span>
                @endif
                @if ($url && ! $loop->last)
                    <a href="{{ $url }}" class="rounded-sm py-1 text-indigo-800 underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-indigo-700">{{ $label }}</a>
                @else
                    <span @if ($loop->last) aria-current="page" @endif class="truncate py-1 font-medium text-slate-800">{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
