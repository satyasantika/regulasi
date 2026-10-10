<?php

namespace App\Filament\Admin\Resources\DokumenResource\Pages;

use App\Actions\Berkas\SimpanTautanBerkas;
use App\Actions\Dokumen\SimpanDokumen;
use App\Enums\JenisTautan;
use App\Filament\Admin\Resources\DokumenResource;
use App\Models\Dokumen;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

        return DB::transaction(function () use ($data, $oleh): Dokumen {
            $dokumen = app(SimpanDokumen::class)->handle($data, $oleh);

            if (filled($data['tautan_sumber'] ?? null)) {
                app(SimpanTautanBerkas::class)->handle($dokumen, [
                    'jenis' => JenisTautan::HalamanSumber,
                    'url' => (string) $data['tautan_sumber'],
                    'label' => 'Halaman sumber resmi',
                ], $oleh);
            }

            return $dokumen;
        });
    }

    /**
     * Setelah dibuat, lanjut ke halaman ubah untuk menambah tautan berkas.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl('edit', ['record' => $this->getRecord()]);
    }
}
