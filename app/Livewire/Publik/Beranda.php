<?php

namespace App\Livewire\Publik;

use App\Enums\StatusBerlaku;
use App\Support\CacheRegulasi;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Beranda portal publik (US-PUB-01, US-PUB-03, BR-20): kotak pencarian (formulir GET biasa ke daftar dokumen), statistik per jenis & kategori, dan
 * dokumen terbaru. Statistik & daftar terbaru publik diambil dari cache (CacheRegulasi); pengguna login melihat
 * daftar terbaru yang juga memuat dokumen internal lewat query terpisah yang tidak di-cache (BR-02).
 */
class Beranda extends Component
{
    public function render(): View
    {
        $terbaru = auth()->check() ? CacheRegulasi::terbaruTermasukInternal() : CacheRegulasi::terbaru();

        return view('livewire.publik.beranda', [
            'statistik' => CacheRegulasi::statistikBeranda(),
            'terbaru' => $terbaru,
            'tahun' => array_slice(CacheRegulasi::tahunTersedia(), 0, 12),
            'statusBerlaku' => StatusBerlaku::cases(),
        ])->layout('components.layouts.publik', [
            'deskripsi' => 'Cari peraturan, keputusan, surat edaran, SOP, dan pedoman yang dipakai sistem-sistem FKIP Universitas Siliwangi.',
        ]);
    }
}
