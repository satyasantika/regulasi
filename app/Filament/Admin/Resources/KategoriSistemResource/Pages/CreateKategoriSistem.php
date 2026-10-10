<?php

namespace App\Filament\Admin\Resources\KategoriSistemResource\Pages;

use App\Filament\Admin\Resources\KategoriSistemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateKategoriSistem extends CreateRecord
{
    protected static string $resource = KategoriSistemResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
