<?php

namespace App\Actions\Pengguna;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mengubah data dan peran pengguna. Peran diubah per selisih agar log aktivitas hanya mencatat perubahan nyata.
 * Pengguna tidak dapat menonaktifkan akunnya sendiri.
 */
class UbahPengguna
{
    /**
     * @param  array{name?: string, email?: string, nip?: string|null, kode_eksternal?: string|null, aktif?: bool, peran?: list<string>}  $data
     */
    public function handle(User $pengguna, array $data, User $oleh): User
    {
        if ($pengguna->is($oleh) && array_key_exists('aktif', $data) && ! $data['aktif']) {
            throw ValidationException::withMessages([
                'aktif' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
            ]);
        }

        return DB::transaction(function () use ($pengguna, $data): User {
            $pengguna->fill(collect($data)->only(['name', 'email', 'nip', 'kode_eksternal', 'aktif'])->all())->save();

            if (array_key_exists('peran', $data)) {
                $sekarang = $pengguna->getRoleNames()->all();
                $baru = $data['peran'];

                $dicabut = array_values(array_diff($sekarang, $baru));
                $ditambah = array_values(array_diff($baru, $sekarang));

                if ($dicabut !== []) {
                    $pengguna->removeRole($dicabut);
                }

                if ($ditambah !== []) {
                    $pengguna->assignRole($ditambah);
                }
            }

            return $pengguna->refresh();
        });
    }
}
