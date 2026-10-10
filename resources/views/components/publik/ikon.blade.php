@props(['nama' => null, 'cadangan' => 'heroicon-o-document-text'])
@php
    try {
        $svg = svg($nama ?: $cadangan, $attributes->get('class', 'size-6'), ['aria-hidden' => 'true', 'focusable' => 'false']);
    } catch (\Throwable) {
        $svg = svg($cadangan, $attributes->get('class', 'size-6'), ['aria-hidden' => 'true', 'focusable' => 'false']);
    }
@endphp
{{ $svg }}
