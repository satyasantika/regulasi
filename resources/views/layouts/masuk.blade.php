@props([
    'after' => null,
    'heading' => null,
    'subheading' => null,
])

@php
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;

    $livewire ??= null;
    $renderHookScopes = $livewire?->getRenderHookScopes();
    $panelStaf = filament()->getCurrentPanel()?->getId() === 'admin';
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <link rel="stylesheet" href="{{ asset('css/regulasi.css') }}">

    <div class="r-pisah">
        {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, scopes: $renderHookScopes) }}

        <aside class="r-pisah__kiri">
            <a class="r-merek" href="{{ route('beranda') }}">
                <span class="r-logo" aria-hidden="true">§</span>
                <span>Regulasi FKIP Unsil<small>Repositori dokumen hukum FKIP</small></span>
            </a>
            <div>
                <span class="r-lencana">{{ $panelStaf ? 'Panel staf' : 'Akun pengguna kampus' }}</span>
                <h1>{{ $panelStaf ? 'Kelola regulasi fakultas dalam satu tempat.' : 'Baca regulasi internal FKIP dengan akun Unsil.' }}</h1>
                <p class="r-sembunyi">
                    {{ $panelStaf
                        ? 'Kontributor mengisi draf dan tautan naskah, admin regulasi memverifikasi dan menerbitkan, super-admin mengelola akun dan klien API.'
                        : 'Masuk dengan surel @unsil.ac.id untuk membaca dokumen internal di samping dokumen publik.' }}
                </p>
                <ul>
                    <li>Status berlaku otomatis dari relasi antardokumen</li>
                    <li>Naskah berupa tautan ke sumber resmi, tanpa unggah berkas</li>
                    <li>Setiap perubahan tercatat di jejak audit</li>
                </ul>
            </div>
            <small>Fakultas Keguruan dan Ilmu Pendidikan · Universitas Siliwangi · <a href="{{ route('beranda') }}">Beranda</a> · <a href="{{ asset('panduan/index.html') }}">Panduan</a></small>
        </aside>

        <div class="r-pisah__kanan">
            <main id="fi-main-content" tabindex="-1" class="r-pisah__form">
                {{ $slot }}
            </main>
            <div class="r-pisah__kaki">{{ FilamentView::renderHook(PanelsRenderHook::FOOTER, scopes: $renderHookScopes) }}</div>
        </div>

        {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $renderHookScopes) }}
    </div>
</x-filament-panels::layout.base>
