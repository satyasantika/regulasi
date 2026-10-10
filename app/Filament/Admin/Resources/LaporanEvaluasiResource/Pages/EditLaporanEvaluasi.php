<?php

namespace App\Filament\Admin\Resources\LaporanEvaluasiResource\Pages;

use App\Filament\Admin\Resources\LaporanEvaluasiResource;
use App\Models\LaporanEvaluasi;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Ubah laporan draf. Laporan final tidak dapat dibuka di sini (juga bagi super-admin).
 */
class EditLaporanEvaluasi extends EditRecord
{
    protected static string $resource = LaporanEvaluasiResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $laporan = $this->getRecord();
        abort_if($laporan instanceof LaporanEvaluasi && $laporan->adalahFinal(), 403, 'Laporan final tidak dapat diubah.');
    }

    protected function getHeaderActions(): array
    {
        return [
            LaporanEvaluasiResource::aksiHitungRingkasan(),
            DeleteAction::make()->visible(fn (LaporanEvaluasi $record): bool => ! $record->adalahFinal()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
