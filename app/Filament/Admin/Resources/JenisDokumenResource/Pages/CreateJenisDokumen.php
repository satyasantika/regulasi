<?php

namespace App\Filament\Admin\Resources\JenisDokumenResource\Pages;

use App\Filament\Admin\Resources\JenisDokumenResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJenisDokumen extends CreateRecord
{
    protected static string $resource = JenisDokumenResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
