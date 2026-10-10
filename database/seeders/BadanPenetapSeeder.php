<?php

namespace Database\Seeders;

use App\Enums\TingkatBadanPenetap;
use App\Models\BadanPenetap;
use Illuminate\Database\Seeder;

/**
 * Badan penetap / T.E.U. awal (03-SKEMA §4.3). Idempoten berdasarkan slug.
 * Konvensi penulisan T.E.U. perlu verifikasi terhadap lampiran Permenkumham 8/2019; dapat disunting admin.
 */
class BadanPenetapSeeder extends Seeder
{
    /**
     * slug => [nama, teu, singkatan, tingkat, urutan, slug induk].
     *
     * @var array<string, array{0: string, 1: string, 2: string|null, 3: TingkatBadanPenetap, 4: int, 5: string|null}>
     */
    private const array DATA = [
        'pemerintah-republik-indonesia' => ['Pemerintah Republik Indonesia', 'Indonesia', null, TingkatBadanPenetap::Negara, 10, null],
        'presiden-republik-indonesia' => ['Presiden Republik Indonesia', 'Indonesia. Presiden', null, TingkatBadanPenetap::Negara, 20, null],
        'kemendikbud' => ['Kementerian Pendidikan dan Kebudayaan', 'Indonesia. Kementerian Pendidikan dan Kebudayaan', 'Kemendikbud', TingkatBadanPenetap::Kementerian, 30, null],
        'kemendikbudristek' => ['Kementerian Pendidikan, Kebudayaan, Riset, dan Teknologi', 'Indonesia. Kementerian Pendidikan, Kebudayaan, Riset, dan Teknologi', 'Kemendikbudristek', TingkatBadanPenetap::Kementerian, 40, null],
        'kemendiktisaintek' => ['Kementerian Pendidikan Tinggi, Sains, dan Teknologi', 'Indonesia. Kementerian Pendidikan Tinggi, Sains, dan Teknologi', 'Kemendiktisaintek', TingkatBadanPenetap::Kementerian, 50, null],
        'kemenkeu' => ['Kementerian Keuangan', 'Indonesia. Kementerian Keuangan', 'Kemenkeu', TingkatBadanPenetap::Kementerian, 60, null],
        'kemenkumham' => ['Kementerian Hukum dan Hak Asasi Manusia', 'Indonesia. Kementerian Hukum dan Hak Asasi Manusia', 'Kemenkumham', TingkatBadanPenetap::Kementerian, 70, null],
        'ban-pt' => ['Badan Akreditasi Nasional Perguruan Tinggi', 'Indonesia. Badan Akreditasi Nasional Perguruan Tinggi', 'BAN-PT', TingkatBadanPenetap::Lembaga, 80, null],
        'lamdik' => ['Lembaga Akreditasi Mandiri Kependidikan', 'Lembaga Akreditasi Mandiri Kependidikan', 'LAMDIK', TingkatBadanPenetap::Lembaga, 90, null],
        'unsil' => ['Universitas Siliwangi', 'Universitas Siliwangi', 'Unsil', TingkatBadanPenetap::Universitas, 100, null],
        'fkip-unsil' => ['Fakultas Keguruan dan Ilmu Pendidikan Universitas Siliwangi', 'Universitas Siliwangi. Fakultas Keguruan dan Ilmu Pendidikan', 'FKIP Unsil', TingkatBadanPenetap::Fakultas, 110, 'unsil'],
    ];

    public function run(): void
    {
        /** @var array<string, string> $idPerSlug */
        $idPerSlug = [];

        foreach (self::DATA as $slug => [$nama, $teu, $singkatan, $tingkat, $urutan, $slugInduk]) {
            $badan = BadanPenetap::withTrashed()->updateOrCreate(['slug' => $slug], [
                'nama' => $nama,
                'teu' => $teu,
                'singkatan' => $singkatan,
                'tingkat' => $tingkat,
                'induk_id' => $slugInduk !== null ? $idPerSlug[$slugInduk] : null,
                'urutan' => $urutan,
                'aktif' => true,
            ]);

            $idPerSlug[$slug] = $badan->id;
        }
    }
}
