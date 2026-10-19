<?php

namespace Database\Seeders;

use App\Enums\JenisTautan;
use App\Enums\PenyediaTautan;
use App\Enums\StatusBerlaku;
use App\Enums\StatusCekTautan;
use App\Enums\StatusPublikasi;
use App\Enums\Visibilitas;
use App\Models\BadanPenetap;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Data uji kinerja F12.2 (docs/KINERJA.md): 5.000 dokumen + 8.000 tautan lampiran rekaan dengan sebaran jenis,
 * badan, kategori, tahun, status, dan visibilitas. HANYA lingkungan lokal; dipakai pada basis data terpisah
 * (mis. db_regulasi_perf) agar data pengembangan tidak tercampur. Disisipkan massal (tanpa event model/indeks Scout);
 * kamus istilah pencarian dibangun terpisah dengan regulasi:bangun-kamus bila diperlukan.
 */
class PerfSeeder extends Seeder
{
    public const int JUMLAH_DOKUMEN = 5000;

    public const int JUMLAH_LAMPIRAN = 8000;

    private const int POTONGAN = 500;

    public function run(): void
    {
        if (! app()->isLocal()) {
            throw new RuntimeException('PerfSeeder hanya boleh dijalankan pada APP_ENV=local.');
        }

        $this->call([PeranDanIzinSeeder::class, JenisDokumenSeeder::class, BadanPenetapSeeder::class, KategoriSistemSeeder::class]);

        $jenis = JenisDokumen::query()->pluck('id')->all();
        $badan = BadanPenetap::query()->pluck('id')->all();
        $kategori = KategoriSistem::query()->pluck('id')->all();
        $statusBerlaku = [StatusBerlaku::Berlaku->value, StatusBerlaku::Berlaku->value, StatusBerlaku::Berlaku->value, StatusBerlaku::Diubah->value, StatusBerlaku::Dicabut->value];
        $sekarang = now();
        $idDokumen = [];

        mt_srand(20261010);

        for ($awal = 0; $awal < self::JUMLAH_DOKUMEN; $awal += self::POTONGAN) {
            $dokumen = [];
            $pivot = [];

            for ($i = $awal; $i < min($awal + self::POTONGAN, self::JUMLAH_DOKUMEN); $i++) {
                $id = (string) Str::uuid7();
                $tahun = mt_rand(1990, 2026);
                $nomor = 'PERF-'.($i + 1);
                $judul = 'Dokumen uji kinerja '.($i + 1).' tentang '.fake()->words(mt_rand(4, 9), true);
                $terbit = mt_rand(1, 10) <= 9;
                $tanggal = CarbonImmutable::create($tahun, mt_rand(1, 12), mt_rand(1, 28));

                $dokumen[] = [
                    'id' => $id,
                    'slug' => Str::slug("perf {$nomor} {$tahun}").'-'.Str::lower(Str::random(6)),
                    'jenis_dokumen_id' => $jenis[array_rand($jenis)],
                    'badan_penetap_id' => $badan[array_rand($badan)],
                    'nomor' => $nomor,
                    'tahun' => $tahun,
                    'judul' => Str::ucfirst($judul),
                    'sebutan' => "Dokumen Uji Nomor {$nomor} Tahun {$tahun}",
                    'sebutan_singkat' => "Uji {$nomor}/{$tahun}",
                    'tanggal_penetapan' => $tanggal->toDateString(),
                    'abstrak' => mt_rand(1, 10) <= 7 ? fake()->paragraph(4) : null,
                    'status_berlaku' => $statusBerlaku[array_rand($statusBerlaku)],
                    'status_publikasi' => $terbit ? StatusPublikasi::Terbit->value : StatusPublikasi::Draf->value,
                    'visibilitas' => mt_rand(1, 100) <= 85 ? Visibilitas::Publik->value : Visibilitas::Internal->value,
                    'jumlah_dibuka' => mt_rand(0, 500),
                    'dipublikasikan_pada' => $terbit ? $tanggal->setTime(mt_rand(7, 16), mt_rand(0, 59)) : null,
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ];

                foreach ((array) array_rand(array_flip($kategori), mt_rand(1, 2)) as $kategoriId) {
                    $pivot[] = ['dokumen_id' => $id, 'kategori_sistem_id' => $kategoriId];
                }

                $idDokumen[] = $id;
            }

            DB::table('dokumen')->insert($dokumen);
            DB::table('dokumen_kategori_sistem')->insert($pivot);
        }

        $this->lampiran($idDokumen, $sekarang);
    }

    /**
     * Satu naskah utama per dokumen + lampiran sampai JUMLAH_LAMPIRAN.
     *
     * @param  list<string>  $idDokumen
     */
    private function lampiran(array $idDokumen, DateTimeInterface $sekarang): void
    {
        $baris = [];
        $tautan = function (string $dokumenId, JenisTautan $jenis, int $urutan) use ($sekarang): array {
            $driveId = '1Perf'.Str::random(28);

            return [
                'id' => (string) Str::uuid7(),
                'pemilik_type' => 'dokumen',
                'pemilik_id' => $dokumenId,
                'jenis' => $jenis->value,
                'url' => "https://drive.google.com/file/d/{$driveId}/view",
                'penyedia' => PenyediaTautan::GoogleDrive->value,
                'drive_file_id' => $driveId,
                'status_cek' => StatusCekTautan::DapatDiakses->value,
                'urutan' => $urutan,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ];
        };

        foreach ($idDokumen as $dokumenId) {
            $baris[] = $tautan($dokumenId, JenisTautan::NaskahUtama, 0);
        }

        for ($i = 0; $i < self::JUMLAH_LAMPIRAN; $i++) {
            $baris[] = $tautan($idDokumen[array_rand($idDokumen)], JenisTautan::Lampiran, $i % 5 + 1);
        }

        foreach (array_chunk($baris, self::POTONGAN) as $potongan) {
            DB::table('tautan_berkas')->insert($potongan);
        }
    }
}
