<?php

namespace App\Support;

use App\Models\User;

/**
 * Status impersonasi: id super-admin asal disimpan di sesi selama ia masuk sebagai pengguna lain.
 */
class Impersonasi
{
    public const string KUNCI = 'impersonasi_oleh';

    public static function aktif(): bool
    {
        return self::idAsal() !== null;
    }

    public static function idAsal(): ?string
    {
        if (! app()->bound('session')) {
            return null;
        }

        $id = session()->get(self::KUNCI);

        return is_string($id) && $id !== '' ? $id : null;
    }

    public static function asal(): ?User
    {
        $id = self::idAsal();

        return $id === null ? null : User::query()->find($id);
    }
}
