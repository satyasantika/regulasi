<?php

namespace App\Filament\Admin\Resources\KlienApiResource\Pages;

use App\Filament\Admin\Resources\KlienApiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKlienApi extends ListRecords
{
    protected static string $resource = KlienApiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
