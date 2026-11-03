@if (config('services.google.aktif'))
    <div class="mt-4 flex flex-col gap-3">
        @if (session('galat_google'))
            <p role="alert" class="text-sm text-danger-600 dark:text-danger-400">{{ session('galat_google') }}</p>
        @endif

        <a
            href="{{ route('auth.google.redirect', ['asal' => url()->current()]) }}"
            class="fi-btn fi-color-gray fi-size-md relative inline-flex w-full items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-gray-950 shadow-sm ring-1 ring-gray-950/10 hover:bg-gray-50 dark:text-white dark:ring-white/20 dark:hover:bg-white/5"
        >
            Masuk dengan Google
        </a>
    </div>
@endif
