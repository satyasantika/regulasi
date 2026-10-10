<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Tag subjek awal (03-SKEMA §4.6). Idempoten berdasarkan slug.
 */
class TagSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const array DATA = [
        'Akreditasi Program Studi',
        'LAMDIK',
        'IAPSK 3.0',
        'Laporan Evaluasi Diri',
        'DKPS',
        'Penjaminan Mutu',
        'Barang Milik Negara',
        'Tata Naskah Dinas',
        'Kerja Sama',
        'Keuangan Negara',
        'Badan Layanan Umum',
        'Dosen',
        'Guru',
        'Pendidikan Jarak Jauh',
        'Kekerasan di Satuan Pendidikan',
        'Dokumentasi Hukum',
    ];

    public function run(): void
    {
        foreach (self::DATA as $nama) {
            Tag::updateOrCreate(['slug' => Str::slug($nama)], ['nama' => $nama]);
        }
    }
}
