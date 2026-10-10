@props(['status', 'besar' => false])
@php
    /** @var \App\Enums\StatusBerlaku $status */
    $warna = match ($status) {
        \App\Enums\StatusBerlaku::Berlaku => 'bg-emerald-50 text-emerald-800 ring-emerald-600/30',
        \App\Enums\StatusBerlaku::Diubah => 'bg-amber-50 text-amber-900 ring-amber-600/30',
        \App\Enums\StatusBerlaku::Dicabut => 'bg-rose-50 text-rose-800 ring-rose-600/30',
        \App\Enums\StatusBerlaku::TidakBerlaku => 'bg-slate-100 text-slate-700 ring-slate-500/30',
    };
    $titik = match ($status) {
        \App\Enums\StatusBerlaku::Berlaku => 'bg-emerald-600',
        \App\Enums\StatusBerlaku::Diubah => 'bg-amber-500',
        \App\Enums\StatusBerlaku::Dicabut => 'bg-rose-600',
        \App\Enums\StatusBerlaku::TidakBerlaku => 'bg-slate-500',
    };
@endphp
<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-full font-medium ring-1 ring-inset whitespace-nowrap',
    $warna,
    'px-3 py-1 text-sm' => $besar,
    'px-2 py-0.5 text-xs' => ! $besar,
]) }}>
    <span class="size-1.5 rounded-full {{ $titik }}" aria-hidden="true"></span>
    {{ $status->label() }}
</span>
