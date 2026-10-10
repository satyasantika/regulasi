<?php

namespace Database\Seeders;

use App\Enums\KelompokJenisDokumen;
use App\Models\JenisDokumen;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Jenis dokumen awal (03-SKEMA §4.2). Idempoten berdasarkan kode.
 * Urutan 10–40 mengikuti hierarki Pasal 7 ayat (1) UU 12/2011.
 */
class JenisDokumenSeeder extends Seeder
{
    /**
     * @var list<array{0: string, 1: string, 2: string|null, 3: KelompokJenisDokumen, 4: int}>
     */
    private const array DATA = [
        ['UU', 'Undang-Undang', 'UU', KelompokJenisDokumen::Peraturan, 10],
        ['PERPPU', 'Peraturan Pemerintah Pengganti Undang-Undang', 'Perppu', KelompokJenisDokumen::Peraturan, 20],
        ['PP', 'Peraturan Pemerintah', 'PP', KelompokJenisDokumen::Peraturan, 30],
        ['PERPRES', 'Peraturan Presiden', 'Perpres', KelompokJenisDokumen::Peraturan, 40],
        ['PERMEN', 'Peraturan Menteri', 'Permen', KelompokJenisDokumen::Peraturan, 50],
        ['PERLEMBAGA', 'Peraturan BAN-PT/LAM', null, KelompokJenisDokumen::Peraturan, 60],
        ['PERREKTOR', 'Peraturan Rektor', 'Pertor', KelompokJenisDokumen::Peraturan, 70],
        ['KEPMEN', 'Keputusan Menteri', 'Kepmen', KelompokJenisDokumen::Keputusan, 80],
        ['KEPPEJABAT', 'Keputusan Dirjen/Kepala Lembaga', null, KelompokJenisDokumen::Keputusan, 90],
        ['KEPREKTOR', 'Keputusan Rektor', 'SK Rektor', KelompokJenisDokumen::Keputusan, 100],
        ['KEPDEKAN', 'Keputusan Dekan', 'SK Dekan', KelompokJenisDokumen::Keputusan, 110],
        ['SE', 'Surat Edaran', 'SE', KelompokJenisDokumen::Kebijakan, 120],
        ['PEDOMAN', 'Pedoman/Panduan', null, KelompokJenisDokumen::Pendukung, 130],
        ['JUKNIS', 'Petunjuk Teknis', 'Juknis', KelompokJenisDokumen::Pendukung, 140],
        ['SOP', 'Standar Operasional Prosedur', 'SOP', KelompokJenisDokumen::Pendukung, 150],
        ['MANUAL', 'Manual Aplikasi', null, KelompokJenisDokumen::Pendukung, 160],
        ['INSTRUMEN', 'Instrumen/Matriks Penilaian', null, KelompokJenisDokumen::Pendukung, 170],
        ['LAINNYA', 'Dokumen Lainnya', null, KelompokJenisDokumen::Pendukung, 999],
    ];

    public function run(): void
    {
        foreach (self::DATA as [$kode, $nama, $singkatan, $kelompok, $urutan]) {
            JenisDokumen::withTrashed()->updateOrCreate(['kode' => $kode], [
                'nama' => $nama,
                'singkatan' => $singkatan,
                'slug' => Str::slug(str_replace('/', ' ', $nama)),
                'kelompok' => $kelompok,
                'urutan' => $urutan,
                'wajib_nomor' => $kelompok->wajibNomor(),
                'aktif' => true,
            ]);
        }
    }
}
