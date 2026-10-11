<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Actions\Pengguna\AturPenugasanKategori;
use App\Actions\Pengguna\AturWajibGantiSandi;
use App\Actions\Pengguna\KirimTautanAturSandi;
use App\Actions\Pengguna\UbahPengguna;
use App\Enums\Peran;
use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * @property User $record
 */
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kirimTautanSandi')
                ->label('Kirim ulang tautan kata sandi')
                ->icon(Heroicon::OutlinedEnvelope)
                ->color('gray')
                ->authorize('update', $this->record)
                ->requiresConfirmation()
                ->action(function (): void {
                    app(KirimTautanAturSandi::class)->handle($this->record);

                    Notification::make()->title('Tautan atur kata sandi dikirim')->success()->send();
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['peran'] = $this->record->getRoleNames()->all();
        $data['kategori_sistem'] = $this->record->kategoriSistem()->pluck('kategori_sistem.id')->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        /** @var User $oleh */
        $oleh = auth()->user();

        /** @var list<string>|null $kategori */
        $kategori = array_key_exists('kategori_sistem', $data) ? array_values((array) $data['kategori_sistem']) : null;

        $wajibGantiSandi = array_key_exists('wajib_ganti_sandi', $data) ? (bool) $data['wajib_ganti_sandi'] : null;

        /** @var array{name?: string, email?: string, nip?: string|null, kode_eksternal?: string|null, aktif?: bool, peran?: list<string>} $data */
        $data = Arr::except($data, ['kategori_sistem', 'wajib_ganti_sandi']);

        $pengguna = app(UbahPengguna::class)->handle($record, $data, $oleh);

        if ($wajibGantiSandi !== null && $oleh->hasRole(Peran::SuperAdmin->value)) {
            app(AturWajibGantiSandi::class)->handle([$pengguna], $wajibGantiSandi, $oleh);
        }

        if ($kategori !== null && $pengguna->hasRole(Peran::Kontributor->value) && $oleh->can('aturPenugasan', $pengguna)) {
            app(AturPenugasanKategori::class)->handle($pengguna, $kategori, $oleh);
        }

        return $pengguna;
    }
}
