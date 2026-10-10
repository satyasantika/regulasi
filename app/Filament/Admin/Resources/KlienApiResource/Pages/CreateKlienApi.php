<?php

namespace App\Filament\Admin\Resources\KlienApiResource\Pages;

use App\Filament\Admin\Resources\KlienApiResource;
use Filament\Resources\Pages\CreateRecord;

class CreateKlienApi extends CreateRecord
{
    protected static string $resource = KlienApiResource::class;

    /**
     * Setelah dibuat langsung ke halaman lihat agar token dapat dibuat.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
