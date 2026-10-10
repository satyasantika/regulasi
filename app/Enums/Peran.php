<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Peran pengguna (PRD §3.1). Tamu dan klien API bukan peran.
 */
enum Peran: string implements HasLabel
{
    case SuperAdmin = 'super-admin';
    case AdminRegulasi = 'admin-regulasi';
    case Kontributor = 'kontributor';
    case Pengguna = 'pengguna';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin (TI Fakultas)',
            self::AdminRegulasi => 'Admin Regulasi Fakultas',
            self::Kontributor => 'Kontributor Sistem/Unit',
            self::Pengguna => 'Pengguna Terdaftar',
        };
    }

    /**
     * Peran staf yang boleh membuka panel admin.
     *
     * @return list<self>
     */
    public static function staf(): array
    {
        return [self::SuperAdmin, self::AdminRegulasi, self::Kontributor];
    }

    /**
     * Peran penerbit dokumen yang wajib MFA (US-AUTH-03).
     *
     * @return list<self>
     */
    public static function wajibMfa(): array
    {
        return [self::SuperAdmin, self::AdminRegulasi];
    }

    /**
     * @param  list<self>  $peran
     * @return list<string>
     */
    public static function nilai(array $peran): array
    {
        return array_map(fn (self $p): string => $p->value, $peran);
    }
}
