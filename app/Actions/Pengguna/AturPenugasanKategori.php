<?php

namespace App\Actions\Pengguna;

use App\Models\KategoriSistem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Menugaskan kontributor ke kategori sistem (US-MAS-03, BR-03). Perubahan dicatat ke log aktivitas
 * sebagai selisih kode kategori yang ditambahkan dan dicabut.
 */
class AturPenugasanKategori
{
    /**
     * @param  list<string>  $kategoriId
     */
    public function handle(User $pengguna, array $kategoriId, User $oleh): User
    {
        Gate::forUser($oleh)->authorize('aturPenugasan', $pengguna);

        return DB::transaction(function () use ($pengguna, $kategoriId, $oleh): User {
            $baru = KategoriSistem::query()->whereKey($kategoriId)->pluck('kode', 'id');
            $lama = $pengguna->kategoriSistem()->pluck('kode', 'kategori_sistem.id');

            $hasil = $pengguna->kategoriSistem()->sync($baru->keys()->all());

            $ditambahkan = $baru->only($hasil['attached'])->values()->sort()->values()->all();
            $dicabut = $lama->only($hasil['detached'])->values()->sort()->values()->all();

            if ($ditambahkan !== [] || $dicabut !== []) {
                activity('pengguna')
                    ->performedOn($pengguna)
                    ->causedBy($oleh)
                    ->event('penugasan-diubah')
                    ->withProperties([
                        'ditambahkan' => $ditambahkan,
                        'dicabut' => $dicabut,
                        'kategori' => $baru->values()->sort()->values()->all(),
                    ])
                    ->log('Penugasan kategori sistem diubah');
            }

            return $pengguna->load('kategoriSistem');
        });
    }
}
