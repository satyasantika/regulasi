<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Selamat datang, {{ auth()->user()?->name }}</x-slot>

        <p class="text-sm text-gray-600 dark:text-gray-300">
            Akun ini memberi Anda akses membaca dokumen regulasi FKIP Universitas Siliwangi,
            termasuk dokumen yang hanya untuk warga kampus.
        </p>

        <div class="mt-4 flex flex-wrap gap-3">
            <x-filament::button tag="a" :href="$this->getUrlBeranda()" icon="heroicon-o-home">
                Ke beranda portal
            </x-filament::button>

            @if ($urlAdmin = $this->getUrlPanelAdmin())
                <x-filament::button tag="a" :href="$urlAdmin" color="gray" icon="heroicon-o-cog-6-tooth">
                    Ke panel admin
                </x-filament::button>
            @endif
        </div>
    </x-filament::section>
</x-filament-panels::page>
