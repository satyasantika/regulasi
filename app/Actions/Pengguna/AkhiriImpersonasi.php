<?php

namespace App\Actions\Pengguna;

use App\Enums\Peran;
use App\Models\User;
use App\Support\Impersonasi;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

/**
 * Kembali ke akun super-admin asal dan mencatat akhir impersonasi.
 */
class AkhiriImpersonasi
{
    public function handle(): User
    {
        $asal = Impersonasi::asal();

        if (! $asal instanceof User || ! $asal->aktif || ! $asal->hasRole(Peran::SuperAdmin->value)) {
            throw new AuthorizationException('Tidak ada impersonasi yang dapat diakhiri.');
        }

        $disamar = Auth::guard('web')->user();

        activity('pengguna')
            ->performedOn($disamar instanceof User ? $disamar : $asal)
            ->causedBy($asal)
            ->event('impersonasi-akhir')
            ->log('Selesai masuk sebagai '.($disamar instanceof User ? $disamar->email : 'pengguna lain'));

        $sesi = session()->driver();
        $sesi->forget(Impersonasi::KUNCI);

        Auth::guard('web')->login($asal);
        $sesi->put('password_hash_web', $asal->getAuthPassword());

        return $asal;
    }
}
