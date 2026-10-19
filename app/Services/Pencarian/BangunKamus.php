<?php

namespace App\Services\Pencarian;

use App\Enums\StatusPublikasi;
use App\Models\BadanPenetap;
use App\Models\Dokumen;
use App\Models\IstilahPencarian;
use App\Models\JenisDokumen;
use App\Models\SinonimPencarian;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Membangun ulang kamus istilah (03-SKEMA §3.15a): token unik huruf kecil dari judul dan sebutan singkat dokumen
 * terbit, nama/kode jenis, T.E.U., dan sinonim; upsert dengan frekuensi, lalu hapus istilah yang tidak muncul lagi.
 * Idempoten.
 */
class BangunKamus
{
    /** Urutan prioritas sumber bila satu istilah muncul dari beberapa sumber. */
    private const array URUTAN_SUMBER = ['judul', 'singkatan', 'jenis', 'teu', 'sinonim'];

    public function __construct(private readonly KamusPencarian $kamus) {}

    /**
     * @return array{istilah: int, dihapus: int, sinonim_baru: int}
     */
    public function handle(): array
    {
        $sinonimBaru = $this->kamus->semaiSinonim();

        /** @var array<string, array{sumber: string, frekuensi: int}> $hitung */
        $hitung = [];

        $catat = function (?string $teks, string $sumber) use (&$hitung): void {
            foreach ($this->token((string) $teks) as $token) {
                $hitung[$token] ??= ['sumber' => $sumber, 'frekuensi' => 0];
                $hitung[$token]['frekuensi']++;

                if (array_search($sumber, self::URUTAN_SUMBER, true) < array_search($hitung[$token]['sumber'], self::URUTAN_SUMBER, true)) {
                    $hitung[$token]['sumber'] = $sumber;
                }
            }
        };

        Dokumen::query()
            ->where('status_publikasi', StatusPublikasi::Terbit->value)
            ->select(['id', 'judul', 'sebutan_singkat'])
            ->chunkById(500, function ($dokumen) use ($catat): void {
                foreach ($dokumen as $d) {
                    $catat($d->judul, 'judul');
                    $catat($d->sebutan_singkat, 'singkatan');
                }
            });

        JenisDokumen::query()->get(['nama', 'kode'])->each(function (JenisDokumen $j) use ($catat): void {
            $catat($j->nama, 'jenis');
            $catat($j->kode, 'jenis');
        });

        BadanPenetap::query()->get(['nama', 'singkatan'])->each(function (BadanPenetap $b) use ($catat): void {
            $catat($b->nama, 'teu');
            $catat($b->singkatan, 'teu');
        });

        SinonimPencarian::query()->get(['istilah', 'sinonim'])->each(function (SinonimPencarian $s) use ($catat): void {
            $catat($s->istilah, 'sinonim');
            $catat($s->sinonim, 'sinonim');
        });

        $sekarang = now();

        foreach (array_chunk($hitung, 500, true) as $bagian) {
            $baris = [];

            foreach ($bagian as $istilah => $data) {
                $baris[] = [
                    'id' => (string) Str::uuid7(),
                    'istilah' => (string) $istilah,
                    'sumber' => $data['sumber'],
                    'frekuensi' => $data['frekuensi'],
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ];
            }

            DB::table('istilah_pencarian')->upsert($baris, ['istilah'], ['sumber', 'frekuensi', 'updated_at']);
        }

        $dihapus = IstilahPencarian::query()
            ->whereNotIn('istilah', array_map('strval', array_keys($hitung)))
            ->delete();

        $this->kamus->lupakan();

        return ['istilah' => count($hitung), 'dihapus' => $dihapus, 'sinonim_baru' => $sinonimBaru];
    }

    /**
     * @return list<string>
     */
    private function token(string $teks): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($teks), $cocok);

        return array_values(array_filter($cocok[0], fn (string $t): bool => mb_strlen($t) >= 2 && mb_strlen($t) <= 100));
    }
}
