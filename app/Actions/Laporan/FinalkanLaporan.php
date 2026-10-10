<?php

namespace App\Actions\Laporan;

use App\Enums\StatusLaporan;
use App\Exceptions\LaporanFinalTidakDapatDiubah;
use App\Models\LaporanEvaluasi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Memfinalkan laporan evaluasi (US-LAP-03): status final + difinalkan_pada; setelah itu laporan terkunci dan
 * PDF selalu dirender dari snapshot ringkasan yang sama.
 */
class FinalkanLaporan
{
    public function handle(LaporanEvaluasi $laporan, User $oleh): LaporanEvaluasi
    {
        Gate::forUser($oleh)->authorize('finalkan', $laporan);

        return DB::transaction(function () use ($laporan): LaporanEvaluasi {
            /** @var LaporanEvaluasi $baris */
            $baris = LaporanEvaluasi::query()->lockForUpdate()->findOrFail($laporan->getKey());

            if ($baris->adalahFinal()) {
                throw LaporanFinalTidakDapatDiubah::untuk('difinalkan ulang');
            }

            $baris->forceFill(['status' => StatusLaporan::Final, 'difinalkan_pada' => now()])->save();

            return $baris;
        });
    }
}
