<?php

namespace App\Actions\Relasi;

use App\Enums\JenisRelasi;
use App\Models\Dokumen;
use App\Models\DokumenRelasi;
use App\Models\User;
use App\Support\CacheRegulasi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Menyelaraskan status `efektif` semua relasi arah aktif milik dokumen sumber (BR-08): efektif bila sumber terbit
 * dan tanggal efektifnya (berlaku ?? pengundangan ?? penetapan) sudah tiba. Kedua baris pasangan diperbarui dan
 * status berlaku target yang terdampak dihitung ulang (BR-09).
 *
 * Dipanggil setelah dokumen terbit/diarsipkan/dipulihkan, setelah tanggal dokumen terbit diubah, dan oleh
 * perintah terjadwal `regulasi:terapkan-status`.
 */
class TerapkanRelasiDokumen
{
    public function __construct(
        private readonly KunciStatusDokumen $kunci,
        private readonly HitungUlangStatusBerlaku $hitungUlang,
    ) {}

    /**
     * @return int Jumlah relasi yang status efektifnya berubah.
     */
    public function handle(Dokumen $sumber, ?User $oleh = null): int
    {
        $targetId = $this->kueriRelasiAktif($sumber)->pluck('dokumen_terkait_id')->map(fn (mixed $id): string => (string) $id)->all();

        if ($targetId === []) {
            return 0;
        }

        /** @var int */
        return $this->kunci->jalankan([$sumber->getKey(), ...$targetId], function () use ($sumber, $oleh): int {
            /** @var Dokumen $barisSumber */
            $barisSumber = Dokumen::withTrashed()->whereKey($sumber->getKey())->lockForUpdate()->firstOrFail();

            $efektif = $barisSumber->efektifSebagaiSumber();
            $efektifPada = $barisSumber->tanggal_efektif?->startOfDay();
            $berubah = 0;

            $relasi = $this->kueriRelasiAktif($barisSumber)->with(['pasangan', 'dokumenTerkait'])->lockForUpdate()->get();

            foreach ($relasi as $baris) {
                $statusBerubah = $baris->efektif !== $efektif;

                if (! $statusBerubah && $baris->efektif_pada?->toDateString() === $efektifPada?->toDateString()) {
                    continue;
                }

                foreach (array_filter([$baris, $baris->pasangan]) as $sisi) {
                    $sisi->efektif = $efektif;
                    $sisi->efektif_pada = $efektifPada;
                    $sisi->save();
                }

                if ($statusBerubah) {
                    $berubah++;
                    $this->hitungUlang->handle($baris->dokumenTerkait, $oleh, $baris->pasangan);
                }
            }

            if ($berubah > 0) {
                DB::afterCommit(fn () => CacheRegulasi::lupakanPublik());
            }

            return $berubah;
        });
    }

    /**
     * @return Builder<DokumenRelasi>
     */
    private function kueriRelasiAktif(Dokumen $sumber): Builder
    {
        return DokumenRelasi::query()
            ->where('dokumen_id', $sumber->getKey())
            ->whereIn('jenis_relasi', array_map(fn (JenisRelasi $j): string => $j->value, JenisRelasi::arahAktif()))
            ->orderBy('id');
    }
}
