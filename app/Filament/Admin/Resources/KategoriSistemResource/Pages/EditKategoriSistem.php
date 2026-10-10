<?php

namespace App\Filament\Admin\Resources\KategoriSistemResource\Pages;

use App\Filament\Admin\Resources\KategoriSistemResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditKategoriSistem extends EditRecord
{
    protected static string $resource = KategoriSistemResource::class;

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
