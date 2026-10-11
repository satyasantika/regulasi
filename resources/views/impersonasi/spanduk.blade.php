@php($asal = \App\Support\Impersonasi::asal())
@if ($asal)
    <div role="alert" style="position:sticky;top:0;z-index:60;background:#b45309;color:#fff;padding:.5rem 1rem;display:flex;gap:1rem;align-items:center;justify-content:center;flex-wrap:wrap;font-size:.9rem;font-family:system-ui,sans-serif">
        <span>Impersonasi: Anda ({{ $asal->email }}) sedang masuk sebagai <strong>{{ auth()->user()?->name }}</strong> ({{ auth()->user()?->email }}). Semua tindakan tercatat.</span>
        <form method="POST" action="{{ route('impersonasi.berhenti') }}" style="margin:0">
            @csrf
            <button type="submit" style="background:#fff;color:#92400e;border:0;border-radius:.4rem;padding:.3rem .8rem;font-weight:600;cursor:pointer">Kembali ke akun saya</button>
        </form>
    </div>
@endif
