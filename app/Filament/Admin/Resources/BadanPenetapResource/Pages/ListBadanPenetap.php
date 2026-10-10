<?php

namespace App\Filament\Admin\Resources\BadanPenetapResource\Pages;

use App\Filament\Admin\Resources\BadanPenetapResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBadanPenetap extends ListRecords
{
    protected static string $resource = BadanPenetapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
