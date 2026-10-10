<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Actions\Pengguna\BuatPengguna;
use App\Filament\Admin\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var array{name: string, email: string, nip?: string|null, kode_eksternal?: string|null, aktif?: bool, peran?: list<string>} $data */
        return app(BuatPengguna::class)->handle($data);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Pengguna dibuat; tautan atur kata sandi telah dikirim.';
    }
}
