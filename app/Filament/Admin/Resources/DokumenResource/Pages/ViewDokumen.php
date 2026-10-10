<?php

namespace App\Filament\Admin\Resources\DokumenResource\Pages;

use App\Filament\Admin\Resources\DokumenResource;
use App\Models\Dokumen;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * @property Dokumen $record
 */
class ViewDokumen extends ViewRecord
{
    protected static string $resource = DokumenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['kategori_sistem'] = $this->record->kategoriSistem()->pluck('kategori_sistem.id')->all();
        $data['tag'] = $this->record->tag()->pluck('tag.id')->all();

        return $data;
    }
}
