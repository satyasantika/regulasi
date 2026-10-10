<?php

namespace App\Models;

use App\Enums\Peran;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string|null $nip
 * @property string|null $prodi_id
 * @property string|null $kode_eksternal
 * @property bool $aktif
 * @property CarbonImmutable|null $terakhir_masuk_pada
 */
#[Fillable(['name', 'email', 'password', 'nip', 'prodi_id', 'kode_eksternal', 'aktif'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuids, LogsActivity, Notifiable;

    protected $table = 'users';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'aktif' => 'boolean',
            'terakhir_masuk_pada' => 'datetime',
        ];
    }

    /**
     * Jejak audit: hanya identitas dan status aktif. Kata sandi, remember_token, dan rahasia MFA tidak pernah dicatat.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'nip', 'aktif'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('pengguna');
    }

    /**
     * Panel admin hanya untuk staf aktif; panel akun untuk semua pengguna aktif.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->aktif) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->hasAnyRole(Peran::nilai(Peran::staf())),
            'akun' => true,
            default => false,
        };
    }
}
