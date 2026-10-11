<div>
    {{-- Hero + pencarian --}}
    <section class="relative overflow-hidden bg-linear-to-br from-indigo-950 via-indigo-800 to-teal-700 text-white">
        <div class="mx-auto max-w-6xl px-4 py-12 sm:py-16">
            <p class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold tracking-wide uppercase">
                <span class="size-1.5 rounded-full bg-teal-300" aria-hidden="true"></span>
                FKIP Universitas Siliwangi
            </p>
            <h1 class="mt-4 max-w-3xl text-3xl leading-tight font-bold sm:text-4xl">Temukan regulasi yang dipakai sistem-sistem FKIP</h1>
            <p class="mt-3 max-w-2xl text-base text-indigo-100 sm:text-lg">
                Peraturan, keputusan, surat edaran, SOP, dan pedoman beserta status berlakunya dalam satu tempat.
            </p>

            <form method="get" action="{{ route('dokumen.index') }}" role="search" class="mt-7 max-w-3xl">
                <label for="cari-beranda" class="sr-only">Cari dokumen</label>
                <div class="flex flex-col gap-2 rounded-2xl bg-white p-2 shadow-xl sm:flex-row">
                    <div class="relative flex-1">
                        <svg class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                        <input id="cari-beranda" name="q" type="search" maxlength="200" autocomplete="off"
                               placeholder="Contoh: tata naskah dinas, Permendikbudristek 53"
                               class="h-12 w-full rounded-xl border-0 bg-transparent pr-3 pl-10 text-base text-slate-900 placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-600 focus:outline-none">
                    </div>
                    <button type="submit" class="h-12 rounded-xl bg-indigo-800 px-6 font-semibold text-white hover:bg-indigo-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700">
                        Cari
                    </button>
                </div>
            </form>

            <div class="mt-5 flex flex-wrap gap-3 text-sm">
                <a href="{{ route('dokumen.index') }}" class="inline-flex min-h-11 items-center rounded-xl bg-teal-400 px-4 font-semibold text-indigo-950 hover:bg-teal-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Telusuri semua dokumen</a>
                <a href="#panduan" class="inline-flex min-h-11 items-center rounded-xl px-4 font-semibold text-white ring-1 ring-white/50 hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Panduan pengguna</a>
                @guest
                    <a href="{{ route('filament.akun.auth.login') }}" class="inline-flex min-h-11 items-center rounded-xl px-4 font-semibold text-white ring-1 ring-white/50 hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Masuk dengan akun Unsil</a>
                @endguest
            </div>

            <dl class="mt-8 grid max-w-3xl grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl bg-white/10 p-4 ring-1 ring-white/20">
                    <dt class="text-xs text-indigo-100">Dokumen terbit</dt>
                    <dd class="mt-1 text-2xl font-bold">{{ number_format($statistik['total'], 0, ',', '.') }}</dd>
                </div>
                <div class="rounded-xl bg-white/10 p-4 ring-1 ring-white/20">
                    <dt class="text-xs text-indigo-100">Masih berlaku</dt>
                    <dd class="mt-1 text-2xl font-bold">{{ number_format($statistik['per_status']['berlaku'] + $statistik['per_status']['diubah'], 0, ',', '.') }}</dd>
                </div>
                <div class="rounded-xl bg-white/10 p-4 ring-1 ring-white/20">
                    <dt class="text-xs text-indigo-100">Dicabut</dt>
                    <dd class="mt-1 text-2xl font-bold">{{ number_format($statistik['per_status']['dicabut'], 0, ',', '.') }}</dd>
                </div>
                <div class="rounded-xl bg-white/10 p-4 ring-1 ring-white/20">
                    <dt class="text-xs text-indigo-100">Kali dibuka</dt>
                    <dd class="mt-1 text-2xl font-bold">{{ number_format($statistik['total_dibuka'], 0, ',', '.') }}</dd>
                </div>
            </dl>
        </div>
    </section>

    <div class="mx-auto max-w-6xl px-4">
        {{-- Statistik per jenis --}}
        <section class="mt-10" aria-labelledby="judul-jenis">
            <h2 id="judul-jenis" class="text-xl font-bold text-slate-900">Jenis dokumen</h2>
            @if ($statistik['per_jenis'] === [])
                <p class="mt-3 text-slate-600">Belum ada dokumen yang diterbitkan.</p>
            @else
                <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($statistik['per_jenis'] as $jenis)
                        <li>
                            <a href="{{ route('dokumen.jenis', $jenis['slug']) }}"
                               class="flex h-full min-h-20 flex-col justify-between rounded-xl border border-slate-200 bg-white p-4 shadow-xs transition hover:border-indigo-600 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700">
                                <span class="text-sm font-medium text-slate-700">{{ $jenis['nama'] }}</span>
                                <span class="mt-2 text-2xl font-bold text-indigo-900">{{ number_format($jenis['jumlah'], 0, ',', '.') }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Kategori sistem --}}
        <section class="mt-10" aria-labelledby="judul-kategori">
            <h2 id="judul-kategori" class="text-xl font-bold text-slate-900">Kategori sistem FKIP</h2>
            <ul class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($statistik['per_kategori'] as $kategori)
                    <li>
                        <a href="{{ route('dokumen.kategori', $kategori['slug']) }}"
                           class="flex min-h-16 items-center gap-3 rounded-xl border border-slate-200 bg-white p-3.5 transition hover:border-teal-600 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700">
                            <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-teal-50 text-teal-800">
                                <x-publik.ikon :nama="$kategori['ikon']" class="size-6" />
                            </span>
                            <span class="min-w-0 flex-1 font-medium text-slate-800">{{ $kategori['nama'] }}</span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-sm font-semibold text-slate-700">
                                {{ $kategori['jumlah'] }}<span class="sr-only"> dokumen</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        <div class="mt-10 grid gap-10 lg:grid-cols-3">
            {{-- Dokumen terbaru --}}
            <section class="lg:col-span-2" aria-labelledby="judul-terbaru">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 id="judul-terbaru" class="text-xl font-bold text-slate-900">Dokumen terbaru</h2>
                    <a href="{{ route('dokumen.index').'?urut=terbaru' }}" class="inline-flex min-h-11 items-center text-sm font-medium text-indigo-800 underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-indigo-700">Lihat semua<span class="sr-only"> dokumen terbaru</span></a>
                </div>
                @if ($terbaru === [])
                    <p class="mt-3 text-slate-600">Belum ada dokumen yang diterbitkan.</p>
                @else
                    <ul class="mt-4 divide-y divide-slate-200 overflow-hidden rounded-xl border border-slate-200 bg-white">
                        @foreach ($terbaru as $dokumen)
                            <li>
                                <a href="{{ route('dokumen.show', ['dokumen' => $dokumen['id'], 'slug' => $dokumen['slug']]) }}" class="block p-4 hover:bg-slate-50 focus-visible:bg-slate-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-700">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-slate-900">{{ $dokumen['sebutan_singkat'] }}</span>
                                        <x-publik.badge-status :status="\App\Enums\StatusBerlaku::from($dokumen['status_berlaku'])" />
                                        @if ($dokumen['internal'])
                                            <span class="rounded-full bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-800 ring-1 ring-violet-600/30 ring-inset">Internal</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 line-clamp-2 text-sm text-slate-700">{{ $dokumen['judul'] }}</p>
                                    <p class="mt-1 text-xs text-slate-600">
                                        {{ $dokumen['jenis'] }}
                                        @if ($dokumen['dipublikasikan_pada'])
                                            · terbit {{ \Carbon\CarbonImmutable::parse($dokumen['dipublikasikan_pada'])->timezone(config('app.timezone'))->translatedFormat('j F Y') }}
                                        @endif
                                    </p>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Per tahun & status --}}
            <aside class="space-y-8" aria-label="Telusuri">
                <section aria-labelledby="judul-status">
                    <h2 id="judul-status" class="text-xl font-bold text-slate-900">Status berlaku</h2>
                    <ul class="mt-4 space-y-2">
                        @foreach ($statusBerlaku as $status)
                            <li>
                                <a href="{{ route('dokumen.index').'?'.http_build_query(['status' => [$status->value]]) }}"
                                   class="flex min-h-11 items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 hover:border-indigo-600 focus-visible:outline-2 focus-visible:outline-indigo-700">
                                    <x-publik.badge-status :status="$status" />
                                    <span class="font-semibold text-slate-800">{{ $statistik['per_status'][$status->value] ?? 0 }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>

                @if ($tahun !== [])
                    <section aria-labelledby="judul-tahun">
                        <h2 id="judul-tahun" class="text-xl font-bold text-slate-900">Tahun</h2>
                        <ul class="mt-4 flex flex-wrap gap-2">
                            @foreach ($tahun as $t)
                                <li>
                                    <a href="{{ route('dokumen.index').'?'.http_build_query(['tahun' => [$t]]) }}"
                                       class="inline-flex min-h-11 items-center rounded-lg border border-slate-200 bg-white px-3.5 text-sm font-medium text-slate-700 hover:border-indigo-600 hover:text-indigo-800 focus-visible:outline-2 focus-visible:outline-indigo-700">{{ $t }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </aside>
        </div>

        {{-- Keunggulan repositori --}}
        <section class="mt-14" aria-labelledby="judul-fitur">
            <h2 id="judul-fitur" class="text-xl font-bold text-slate-900">Mengapa memakai repositori ini</h2>
            <p class="mt-1 max-w-3xl text-slate-600">Satu rujukan regulasi bagi Akreditasi, SDM, Persuratan, Aset/BMN, Microteaching, LMS, Keuangan, Puspresma, Kerja Sama, dan FKIP Edu.</p>
            <ul class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($fitur as [$judulFitur, $isiFitur])
                    <li class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
                        <h3 class="font-semibold text-indigo-900">{{ $judulFitur }}</h3>
                        <p class="mt-1.5 text-sm text-slate-700">{{ $isiFitur }}</p>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Panduan per peran (panduan super-admin sengaja tidak ditampilkan di landing) --}}
        <section id="panduan" class="mt-14 scroll-mt-20" aria-labelledby="judul-panduan">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 id="judul-panduan" class="text-xl font-bold text-slate-900">Panduan pengguna per peran</h2>
                <a href="{{ asset('panduan/index.html') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-indigo-800 underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-indigo-700">Semua panduan</a>
            </div>
            <p class="mt-1 max-w-3xl text-slate-600">Panduan bergambar langkah demi langkah. Pilih sesuai peran Anda.</p>
            <ul class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($panduan as [$berkasPanduan, $namaPeran, $isiPeran])
                    <li>
                        <a href="{{ asset('panduan/'.$berkasPanduan) }}"
                           class="flex h-full flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-xs transition hover:-translate-y-0.5 hover:border-teal-600 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700">
                            <span class="font-semibold text-indigo-900">{{ $namaPeran }}</span>
                            <span class="mt-1.5 flex-1 text-sm text-slate-700">{{ $isiPeran }}</span>
                            <span class="mt-3 text-sm font-semibold text-teal-800">Buka panduan <span aria-hidden="true">→</span></span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</div>
