<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Actions\Pengguna\KirimTautanAturSandi;
use App\Actions\Pengguna\UbahPengguna;
use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

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

        /** @var array{name?: string, email?: string, nip?: string|null, kode_eksternal?: string|null, aktif?: bool, peran?: list<string>} $data */
        return app(UbahPengguna::class)->handle($record, $data, $oleh);
    }
}
