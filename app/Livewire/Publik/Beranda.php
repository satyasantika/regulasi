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
    /**
     * Keunggulan repositori di landing page: [judul, isi].
     *
     * @var list<array{0: string, 1: string}>
     */
    public const array FITUR = [
        ['Metadata lengkap', 'Jenis, nomor, tahun, tentang, badan penetap, tanggal penetapan hingga berlaku, subjek, dan abstrak untuk setiap dokumen.'],
        ['Status berlaku otomatis', 'Relasi mencabut, mengubah, dan melaksanakan memperbarui status dokumen terkait secara simetris.'],
        ['Pencarian teks lengkap', 'Cari judul, nomor, dan isi naskah PDF, toleran salah ketik, dengan filter jenis, tahun, status, dan kategori.'],
        ['Naskah dari sumber resmi', 'Berkas berupa tautan ke Shared Drive fakultas atau situs resmi pemerintah; pratinjau langsung di halaman dokumen.'],
        ['Terverifikasi sebelum terbit', 'Dokumen diajukan kontributor dan diperiksa admin regulasi sebelum tampil di portal.'],
        ['API untuk sistem FKIP', 'Sistem lain membaca dokumen terbit lewat API JSON:API bertoken, tanpa menyalin berkas.'],
    ];

    /**
     * Kartu panduan per peran di landing page: [berkas, nama peran, ringkasan]. Panduan super-admin tetap ada di
     * public/panduan tetapi tidak ditampilkan di sini.
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    public const array PANDUAN = [
        ['index.html', 'Pengunjung umum', 'Mencari, memfilter, dan membaca dokumen publik beserta status berlakunya tanpa perlu akun.'],
        ['pengguna.html', 'Pengguna terdaftar', 'Masuk dengan surel @unsil.ac.id untuk membaca dokumen internal dan mengelola profil.'],
        ['kontributor.html', 'Kontributor sistem/unit', 'Mengisi draf dokumen dan tautan naskah untuk kategori yang ditugaskan, lalu mengajukan verifikasi.'],
        ['admin-regulasi.html', 'Admin regulasi', 'Memverifikasi, menerbitkan, mengelola relasi dan status berlaku, master data, serta laporan.'],
    ];

    public function render(): View
    {
        $terbaru = auth()->check() ? CacheRegulasi::terbaruTermasukInternal() : CacheRegulasi::terbaru();

        return view('livewire.publik.beranda', [
            'statistik' => CacheRegulasi::statistikBeranda(),
            'terbaru' => $terbaru,
            'tahun' => array_slice(CacheRegulasi::tahunTersedia(), 0, 12),
            'statusBerlaku' => StatusBerlaku::cases(),
            'fitur' => self::FITUR,
            'panduan' => self::PANDUAN,
        ])->layout('components.layouts.publik', [
            'canonical' => rtrim(route('beranda'), '/').'/',
            'deskripsi' => 'Cari peraturan, keputusan, surat edaran, SOP, dan pedoman yang dipakai sistem-sistem FKIP Universitas Siliwangi.',
        ]);
    }
}
