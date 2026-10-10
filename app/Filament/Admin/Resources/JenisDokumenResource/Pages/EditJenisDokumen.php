<?php

namespace App\Filament\Admin\Resources\JenisDokumenResource\Pages;

use App\Filament\Admin\Resources\JenisDokumenResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditJenisDokumen extends EditRecord
{
    protected static string $resource = JenisDokumenResource::class;

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
