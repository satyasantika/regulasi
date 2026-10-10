<?php

namespace App\Filament\Admin\Resources\LaporanEvaluasiResource\Pages;

use App\Filament\Admin\Resources\LaporanEvaluasiResource;
use App\Models\LaporanEvaluasi;
use App\Support\Laporan\RingkasanEvaluasi;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Membuat laporan evaluasi draf sekaligus menghitung snapshot ringkasan pertamanya.
 */
class CreateLaporanEvaluasi extends CreateRecord
{
    protected static string $resource = LaporanEvaluasiResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $laporan = new LaporanEvaluasi([
            ...$data,
            'dibuat_oleh' => auth()->id(),
        ]);
        $laporan->forceFill(['ringkasan' => RingkasanEvaluasi::hitung(
            CarbonImmutable::parse((string) $data['periode_mulai']),
            CarbonImmutable::parse((string) $data['periode_selesai']),
        )])->save();

        return $laporan;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
