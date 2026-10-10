<?php

namespace App\Actions\Pengguna;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Super-admin membuat akun (US-AUTH-01). Admin tidak mengetik sandi: pengguna menerima tautan atur kata sandi.
 */
class BuatPengguna
{
    public function __construct(private KirimTautanAturSandi $kirimTautan) {}

    /**
     * @param  array{name: string, email: string, nip?: string|null, kode_eksternal?: string|null, aktif?: bool, peran?: list<string>}  $data
     */
    public function handle(array $data): User
    {
        $pengguna = DB::transaction(function () use ($data): User {
            $pengguna = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'nip' => $data['nip'] ?? null,
                'kode_eksternal' => $data['kode_eksternal'] ?? null,
                'aktif' => $data['aktif'] ?? true,
                // Sandi acak yang tidak pernah diketahui siapa pun; diganti lewat tautan.
                'password' => Str::password(48),
            ]);
            // Alamat surel institusi dimasukkan oleh super-admin, dianggap sudah terverifikasi.
            $pengguna->forceFill(['email_verified_at' => now()])->save();

            if (($data['peran'] ?? []) !== []) {
                $pengguna->assignRole($data['peran']);
            }

            return $pengguna;
        });

        $this->kirimTautan->handle($pengguna);

        return $pengguna;
    }
}
