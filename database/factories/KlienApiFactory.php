<?php

namespace Database\Factories;

use App\Models\KlienApi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KlienApi>
 */
class KlienApiFactory extends Factory
{
    protected $model = KlienApi::class;

    public function definition(): array
    {
        return [
            'nama' => 'Sistem '.fake()->unique()->words(2, true).' FKIP',
            'kontak_nama' => fake()->name(),
            'kontak_email' => fake()->unique()->userName().'@unsil.ac.id',
            'aktif' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(['aktif' => false]);
    }
}
