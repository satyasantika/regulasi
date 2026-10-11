<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'nip' => null,
            'prodi_id' => null,
            'kode_eksternal' => null,
            'aktif' => true,
            'terakhir_masuk_pada' => null,
            'wajib_ganti_sandi' => false,
        ];
    }

    /**
     * Pengguna nonaktif (tidak bisa masuk panel).
     */
    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => [
            'aktif' => false,
        ]);
    }

    /**
     * Pengguna yang wajib mengganti kata sandi pada kesempatan pertama.
     */
    public function wajibGantiSandi(): static
    {
        return $this->state(fn (array $attributes) => [
            'wajib_ganti_sandi' => true,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
