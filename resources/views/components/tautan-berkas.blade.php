<div class="tautan-berkas">
    <span class="tautan-berkas__judul">{{ $judul }}</span>
    <a href="{{ $urlBuka }}" target="_blank" rel="noopener noreferrer" class="tautan-berkas__buka">
        {{ $berhak ? 'Buka berkas' : 'Masuk untuk membuka berkas' }}
    </a>
    @if ($urlPratinjau)
        <details class="tautan-berkas__pratinjau">
            <summary>Pratinjau</summary>
            <iframe src="{{ $urlPratinjau }}" title="Pratinjau {{ $judul }}" loading="lazy" referrerpolicy="no-referrer"
                    width="100%" height="640" allow="autoplay"></iframe>
        </details>
    @endif
</div>
