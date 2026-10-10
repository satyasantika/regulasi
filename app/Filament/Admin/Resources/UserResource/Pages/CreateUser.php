<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Actions\Pengguna\AturPenugasanKategori;
use App\Actions\Pengguna\BuatPengguna;
use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var list<string>|null $kategori */
        $kategori = array_key_exists('kategori_sistem', $data) ? array_values((array) $data['kategori_sistem']) : null;

        /** @var array{name: string, email: string, nip?: string|null, kode_eksternal?: string|null, aktif?: bool, peran?: list<string>} $data */
        $data = Arr::except($data, ['kategori_sistem']);

        $pengguna = app(BuatPengguna::class)->handle($data);

        if ($kategori !== null && $kategori !== []) {
            /** @var User $oleh */
            $oleh = auth()->user();

            app(AturPenugasanKategori::class)->handle($pengguna, $kategori, $oleh);
        }

        return $pengguna;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Pengguna dibuat; tautan atur kata sandi telah dikirim.';
    }
}
