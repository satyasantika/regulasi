<?php

namespace App\Filament\Admin\Resources\LaporanEvaluasiResource\Pages;

use App\Filament\Admin\Resources\LaporanEvaluasiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLaporanEvaluasi extends ListRecords
{
    protected static string $resource = LaporanEvaluasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat laporan evaluasi'),
        ];
    }
}
