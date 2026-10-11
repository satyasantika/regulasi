<?php

namespace App\Actions\Pengguna;

use App\Enums\Peran;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Super-admin mewajibkan (atau mencabut kewajiban) pengguna mengganti kata sandi pada kesempatan berikutnya.
 * Hanya akun yang nilainya berubah yang disimpan dan dicatat di log aktivitas.
 */
class AturWajibGantiSandi
{
    /**
     * @param  iterable<User>  $pengguna
     * @return int jumlah akun yang berubah
     */
    public function handle(iterable $pengguna, bool $wajib, User $oleh): int
    {
        if (! $oleh->hasRole(Peran::SuperAdmin->value)) {
            throw new AuthorizationException('Hanya super-admin yang boleh mengatur kewajiban ganti kata sandi.');
        }

        $berubah = Collection::make($pengguna)->filter(fn (User $akun): bool => $akun->wajib_ganti_sandi !== $wajib);

        DB::transaction(function () use ($berubah, $wajib, $oleh): void {
            foreach ($berubah as $akun) {
                $akun->forceFill(['wajib_ganti_sandi' => $wajib])->save();

                activity('pengguna')
                    ->performedOn($akun)
                    ->causedBy($oleh)
                    ->event($wajib ? 'wajib-ganti-sandi' : 'cabut-wajib-ganti-sandi')
                    ->log($wajib ? 'Mewajibkan ganti kata sandi' : 'Mencabut kewajiban ganti kata sandi');
            }
        });

        return $berubah->count();
    }
}
