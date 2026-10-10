<?php

namespace App\Filament\Admin\Resources\BadanPenetapResource\Pages;

use App\Filament\Admin\Resources\BadanPenetapResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditBadanPenetap extends EditRecord
{
    protected static string $resource = BadanPenetapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
