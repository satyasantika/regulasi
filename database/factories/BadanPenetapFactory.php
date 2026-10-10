<?php

namespace Database\Factories;

use App\Enums\TingkatBadanPenetap;
use App\Models\BadanPenetap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BadanPenetap>
 */
class BadanPenetapFactory extends Factory
{
    protected $model = BadanPenetap::class;

    public function definition(): array
    {
        $nama = 'Badan '.fake()->unique()->words(3, true);

        return [
            'nama' => $nama,
            'teu' => 'Indonesia. '.$nama,
            'singkatan' => null,
            'tingkat' => fake()->randomElement(TingkatBadanPenetap::cases()),
            'urutan' => fake()->numberBetween(1, 900),
            'aktif' => true,
        ];
    }
}
