<?php

namespace App\Filament\Admin\Resources\DokumenResource\Pages;

use App\Actions\Dokumen\SimpanDokumen;
use App\Filament\Admin\Resources\DokumenResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDokumen extends CreateRecord
{
    protected static string $resource = DokumenResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $oleh */
        $oleh = auth()->user();

        return app(SimpanDokumen::class)->handle($data, $oleh);
    }

    /**
     * Setelah dibuat, lanjut ke halaman ubah untuk menambah tautan berkas.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl('edit', ['record' => $this->getRecord()]);
    }
}
