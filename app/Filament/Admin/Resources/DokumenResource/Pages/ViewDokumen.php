<?php

namespace App\Filament\Admin\Resources\DokumenResource\Pages;

use App\Actions\Dokumen\PeriksaKelengkapan;
use App\Filament\Admin\Resources\DokumenResource;
use App\Filament\Admin\Resources\DokumenResource\Actions\AksiPublikasi;
use App\Models\Dokumen;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * @property Dokumen $record
 */
class ViewDokumen extends ViewRecord
{
    protected static string $resource = DokumenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...AksiPublikasi::semua(),
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

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('kelengkapan'),
            $this->hasInfolist() ? $this->getInfolistContentComponent() : $this->getFormContentComponent(),
            $this->getRelationManagersContentComponent(),
        ]);
    }

    /**
     * Panel "Kelengkapan": checklist syarat terbit BR-05 + butir tambahan non-wajib.
     */
    public function kelengkapan(Schema $schema): Schema
    {
        $dokumen = $this->record->loadMissing(['jenisDokumen', 'kategoriSistem', 'naskahUtama', 'tag']);

        $wajib = array_map(
            fn (array $butir): IconEntry => IconEntry::make('kelengkapan_'.$butir['kunci'])
                ->label($butir['label'])
                ->boolean()
                ->state($butir['terpenuhi']),
            app(PeriksaKelengkapan::class)->daftar($dokumen),
        );

        $tambahan = [
            IconEntry::make('tambahan_abstrak')->label('Abstrak')->boolean()->state(filled($dokumen->abstrak)),
            IconEntry::make('tambahan_tanggal_berlaku')->label('Tanggal mulai berlaku')->boolean()->state($dokumen->tanggal_berlaku !== null),
            IconEntry::make('tambahan_tag')->label('Subjek/tag')->boolean()->state($dokumen->tag->isNotEmpty()),
            // Ekstraksi teks dibangun di F7; sampai itu selalu "belum".
            IconEntry::make('tambahan_teks')->label('Teks terekstraksi')->boolean()->state(false),
        ];

        return $schema
            ->record($dokumen)
            ->components([
                Section::make('Kelengkapan')
                    ->description('Syarat terbit (BR-05) dan butir tambahan yang dianjurkan.')
                    ->collapsible()
                    ->columns(2)
                    ->schema([
                        Section::make('Syarat terbit (wajib)')->columns(2)->schema($wajib)->columnSpan(1),
                        Section::make('Tambahan (dianjurkan)')->columns(2)->schema($tambahan)->columnSpan(1),
                    ]),
            ]);
    }
}
