<?php

namespace App\Filament\Admin\Resources\KategoriSistemResource\Pages;

use App\Filament\Admin\Resources\KategoriSistemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKategoriSistem extends ListRecords
{
    protected static string $resource = KategoriSistemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
