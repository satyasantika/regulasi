<?php

namespace App\Filament\Admin\Resources\DokumenResource\Pages;

use App\Actions\Dokumen\PeriksaKelengkapan;
use App\Actions\Relasi\UbahStatusBerlaku;
use App\Enums\StatusBerlaku;
use App\Enums\StatusEkstraksi;
use App\Filament\Admin\Resources\DokumenResource;
use App\Filament\Admin\Resources\DokumenResource\Actions\AksiPublikasi;
use App\Models\Dokumen;
use App\Models\User;
use App\Support\RingkasanStatus;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

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
            $this->aksiUbahStatusBerlaku(),
            EditAction::make(),
        ];
    }

    public const string PILIHAN_TIDAK_BERLAKU = 'tidak_berlaku';

    public const string PILIHAN_OTOMATIS = 'otomatis';

    /**
     * Status berlaku manual (BR-12): "Tidak berlaku (manual)" atau "Kembalikan ke perhitungan otomatis".
     */
    protected function aksiUbahStatusBerlaku(): Action
    {
        return Action::make('ubahStatusBerlaku')
            ->label('Ubah status berlaku')
            ->icon(Heroicon::OutlinedScale)
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->can('ubahStatusBerlaku', $this->record) ?? false)
            ->modalHeading('Ubah status berlaku')
            ->modalDescription('Dicabut/diubah hanya terjadi lewat relasi. Status manual tidak ditimpa perhitungan relasi.')
            ->schema([
                Radio::make('pilihan')
                    ->label('Status')
                    ->options([
                        self::PILIHAN_TIDAK_BERLAKU => 'Tidak berlaku (manual)',
                        self::PILIHAN_OTOMATIS => 'Kembalikan ke perhitungan otomatis',
                    ])
                    ->default(fn (): string => $this->record->status_berlaku_manual ? self::PILIHAN_OTOMATIS : self::PILIHAN_TIDAK_BERLAKU)
                    ->required(),
                Textarea::make('catatan')
                    ->label('Catatan (wajib)')
                    ->required()
                    ->minLength(UbahStatusBerlaku::MINIMUM_CATATAN)
                    ->maxLength(2000)
                    ->rows(3),
            ])
            ->action(function (array $data, Action $action): void {
                /** @var User $pengguna */
                $pengguna = auth()->user();
                $ke = $data['pilihan'] === self::PILIHAN_TIDAK_BERLAKU ? StatusBerlaku::TidakBerlaku : StatusBerlaku::Berlaku;

                try {
                    app(UbahStatusBerlaku::class)->handle($this->record, $ke, $pengguna, (string) $data['catatan']);
                } catch (ValidationException|AuthorizationException|LockTimeoutException $e) {
                    Notification::make()
                        ->title('Gagal')
                        ->body(match (true) {
                            $e instanceof ValidationException => implode(' ', Arr::flatten($e->errors())),
                            $e instanceof LockTimeoutException => 'Dokumen sedang diproses pengguna lain. Coba lagi sebentar lagi.',
                            default => 'Anda tidak berwenang melakukan tindakan ini.',
                        })
                        ->danger()
                        ->send();

                    $action->halt();
                }

                $this->record->refresh();
                Notification::make()->title('Status berlaku diperbarui')->success()->send();
            });
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
            EmbeddedSchema::make('status'),
            EmbeddedSchema::make('kelengkapan'),
            $this->hasInfolist() ? $this->getInfolistContentComponent() : $this->getFormContentComponent(),
            $this->getRelationManagersContentComponent(),
        ]);
    }

    /**
     * Bagian "Status": badge status berlaku + kalimat ringkas dari relasi, dan peringatan relasi tertunda.
     */
    public function status(Schema $schema): Schema
    {
        $ringkasan = RingkasanStatus::untuk($this->record);

        return $schema
            ->record($this->record)
            ->components([
                Section::make('Status')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status_berlaku')
                            ->label('Status berlaku')
                            ->badge()
                            ->size(TextSize::Large),
                        TextEntry::make('ringkasan_status')
                            ->label('Ringkasan')
                            ->state($ringkasan['kalimat'])
                            ->columnSpan(2),
                        Callout::make('Relasi tertunda')
                            ->description(implode(' ', $ringkasan['tertunda']))
                            ->warning()
                            ->visible($ringkasan['tertunda'] !== [])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Panel "Kelengkapan": checklist syarat terbit BR-05 + butir tambahan non-wajib.
     */
    public function kelengkapan(Schema $schema): Schema
    {
        $dokumen = $this->record->loadMissing(['jenisDokumen', 'kategoriSistem', 'naskahUtama.ekstraksi', 'tag']);

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
            IconEntry::make('tambahan_teks')
                ->label('Teks terekstraksi')
                ->boolean()
                ->state($dokumen->naskahUtama?->ekstraksi?->status === StatusEkstraksi::Berhasil)
                ->tooltip($dokumen->naskahUtama?->ekstraksi?->status->getLabel() ?? 'Belum diekstraksi'),
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
