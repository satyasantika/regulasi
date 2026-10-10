<?php

namespace App\Actions\Laporan;

use App\Exceptions\LaporanFinalTidakDapatDiubah;
use App\Models\LaporanEvaluasi;
use App\Models\User;
use App\Support\Laporan\RingkasanEvaluasi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Mengisi ulang snapshot `ringkasan` laporan evaluasi draf (US-LAP-03). Laporan final tidak dapat dihitung ulang.
 */
class HitungRingkasanLaporan
{
    public function handle(LaporanEvaluasi $laporan, User $oleh): LaporanEvaluasi
    {
        Gate::forUser($oleh)->authorize('update', $laporan);

        return DB::transaction(function () use ($laporan): LaporanEvaluasi {
            /** @var LaporanEvaluasi $baris */
            $baris = LaporanEvaluasi::query()->lockForUpdate()->findOrFail($laporan->getKey());

            if ($baris->adalahFinal()) {
                throw LaporanFinalTidakDapatDiubah::untuk('dihitung ulang');
            }

            $baris->forceFill(['ringkasan' => RingkasanEvaluasi::hitung($baris->periode_mulai, $baris->periode_selesai)])->save();

            return $baris;
        });
    }
}
