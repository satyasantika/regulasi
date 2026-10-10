<?php

namespace App\Filament\Admin\Resources\LaporanEvaluasiResource\Pages;

use App\Filament\Admin\Resources\LaporanEvaluasiResource;
use App\Models\LaporanEvaluasi;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLaporanEvaluasi extends ViewRecord
{
    protected static string $resource = LaporanEvaluasiResource::class;

    public function getSubheading(): ?string
    {
        $laporan = $this->getRecord();

        return $laporan instanceof LaporanEvaluasi && $laporan->adalahFinal()
            ? 'Laporan final. Arsipkan PDF ke Shared Drive "Regulasi FKIP"; bila perlu catat sebagai dokumen jenis LAINNYA.'
            : 'Draf: hitung ulang ringkasan bila data atau periode berubah, lalu finalkan.';
    }

    protected function getHeaderActions(): array
    {
        return [
            LaporanEvaluasiResource::aksiUnduhPdf(),
            LaporanEvaluasiResource::aksiHitungRingkasan(),
            LaporanEvaluasiResource::aksiFinalkan(),
            EditAction::make()->visible(fn (LaporanEvaluasi $record): bool => ! $record->adalahFinal() && (auth()->user()?->can('update', $record) ?? false)),
        ];
    }
}
