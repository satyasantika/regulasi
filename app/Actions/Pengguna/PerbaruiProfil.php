<?php

namespace App\Actions\Pengguna;

use App\Models\User;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * Pengguna memperbarui profilnya sendiri (nama, NIP) dan/atau kata sandinya. Saat kata sandi diganti: kewajiban
 * ganti sandi dicabut, token "ingat saya" diganti, dan sesi lain akun ini tidak berlaku lagi (hash sandi di sesi lain
 * tidak cocok sehingga AuthenticateSession mengeluarkannya; sesi saat ini diperbarui halaman profil). Tercatat di
 * log aktivitas tanpa menyimpan sandi.
 */
class PerbaruiProfil
{
    /**
     * @param  array{name?: string, nip?: string|null, password?: string|null}  $data  `password` mentah atau sudah di-hash
     */
    public function handle(User $pengguna, #[SensitiveParameter] array $data): User
    {
        $gantiSandi = filled($data['password'] ?? null);

        DB::transaction(function () use ($pengguna, $data, $gantiSandi): void {
            $pengguna->fill(array_intersect_key($data, array_flip(['name', 'nip'])));

            if ($gantiSandi) {
                $pengguna->password = (string) $data['password'];
                $pengguna->forceFill([
                    'wajib_ganti_sandi' => false,
                    'sandi_diubah_pada' => now(),
                ]);
                $pengguna->setRememberToken(Str::random(60));
            }

            $pengguna->save();

            if ($gantiSandi) {
                activity('pengguna')
                    ->performedOn($pengguna)
                    ->causedBy($pengguna)
                    ->event('ganti-sandi')
                    ->withProperties(['sesi_lain_dikeluarkan' => true])
                    ->log('Mengganti kata sandi; sesi di perangkat lain dikeluarkan');
            }
        });

        if ($gantiSandi) {
            event(new OtherDeviceLogout('web', $pengguna));
        }

        return $pengguna;
    }
}
