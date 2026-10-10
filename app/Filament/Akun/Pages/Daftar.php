<?php

namespace App\Filament\Akun\Pages;

use App\Enums\Peran;
use App\Models\User;
use Closure;
use Filament\Auth\Pages\Register;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/**
 * Registrasi mandiri hanya untuk surel domain kampus (US-AUTH-02); akun baru berperan pengguna.
 */
class Daftar extends Register
{
    protected function getEmailFormComponent(): Component
    {
        $domain = (string) config('regulasi.domain_registrasi');

        /** @var TextInput $komponen */
        $komponen = parent::getEmailFormComponent();

        return $komponen->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($domain): void {
            if (! str_ends_with(mb_strtolower(trim((string) $value)), '@'.$domain)) {
                $fail("Gunakan surel @{$domain}");
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        /** @var User $pengguna */
        $pengguna = parent::handleRegistration($data);
        $pengguna->assignRole(Peran::Pengguna->value);

        return $pengguna;
    }
}
