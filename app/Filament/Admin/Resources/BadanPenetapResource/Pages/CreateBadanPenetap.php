<?php

namespace App\Filament\Admin\Resources\BadanPenetapResource\Pages;

use App\Filament\Admin\Resources\BadanPenetapResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBadanPenetap extends CreateRecord
{
    protected static string $resource = BadanPenetapResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
