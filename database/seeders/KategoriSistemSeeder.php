<?php

namespace Database\Seeders;

use App\Models\KategoriSistem;
use Illuminate\Database\Seeder;

/**
 * Kategori sistem FKIP awal (03-SKEMA §4.4). Idempoten berdasarkan kode (dipakai API `filter[kategori]`).
 */
class KategoriSistemSeeder extends Seeder
{
    /**
     * kode => [nama, slug, urutan, ikon Heroicon].
     *
     * @var array<string, array{0: string, 1: string, 2: int, 3: string}>
     */
    private const array DATA = [
        'akreditasi' => ['Akreditasi', 'akreditasi', 10, 'heroicon-o-check-badge'],
        'sdm' => ['SDM', 'sdm', 20, 'heroicon-o-users'],
        'persuratan' => ['Persuratan', 'persuratan', 30, 'heroicon-o-envelope'],
        'aset' => ['Aset/BMN', 'aset', 40, 'heroicon-o-building-office-2'],
        'microteaching' => ['Microteaching', 'microteaching', 50, 'heroicon-o-presentation-chart-bar'],
        'lms' => ['LMS', 'lms', 60, 'heroicon-o-computer-desktop'],
        'keuangan' => ['Keuangan', 'keuangan', 70, 'heroicon-o-banknotes'],
        'puspresma' => ['Puspresma', 'puspresma', 80, 'heroicon-o-trophy'],
        'kerjasama' => ['Kerja Sama', 'kerjasama', 90, 'heroicon-o-link'],
        'fkip-edu' => ['FKIP Edu', 'fkip-edu', 100, 'heroicon-o-academic-cap'],
        'umum' => ['Regulasi/Umum', 'umum', 110, 'heroicon-o-scale'],
    ];

    public function run(): void
    {
        foreach (self::DATA as $kode => [$nama, $slug, $urutan, $ikon]) {
            KategoriSistem::withTrashed()->updateOrCreate(['kode' => $kode], [
                'nama' => $nama,
                'slug' => $slug,
                'ikon' => $ikon,
                'urutan' => $urutan,
                'aktif' => true,
            ]);
        }
    }
}
