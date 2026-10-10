<?php

namespace App\Filament\Admin\Resources\TagResource\Pages;

use App\Filament\Admin\Resources\TagResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTag extends ListRecords
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
