<?php

namespace App\Filament\Admin\Resources\DokumenResource\Pages;

use App\Actions\Dokumen\SimpanDokumen;
use App\Enums\StatusPublikasi;
use App\Filament\Admin\Resources\DokumenResource;
use App\Filament\Admin\Resources\DokumenResource\Actions\AksiPublikasi;
use App\Models\Dokumen;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * @property Dokumen $record
 */
class EditDokumen extends EditRecord
{
    protected static string $resource = DokumenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...AksiPublikasi::semua(),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make('Dokumen ditolak verifikator')
                ->description(fn (): string => (string) $this->record->catatan_verifikasi)
                ->danger()
                ->visible(fn (): bool => $this->record->status_publikasi === StatusPublikasi::Ditolak && filled($this->record->catatan_verifikasi)),
            $this->getFormContentComponent(),
            $this->getRelationManagersContentComponent(),
        ]);
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

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Dokumen $record */
        /** @var User $oleh */
        $oleh = auth()->user();

        return app(SimpanDokumen::class)->handle($data, $oleh, $record);
    }
}
