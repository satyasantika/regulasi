<?php

namespace App\Filament\Admin\Resources\KlienApiResource\Pages;

use App\Filament\Admin\Resources\KlienApiResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewKlienApi extends ViewRecord
{
    protected static string $resource = KlienApiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
