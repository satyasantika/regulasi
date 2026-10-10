<?php

namespace Database\Factories;

use App\Enums\KelompokJenisDokumen;
use App\Models\JenisDokumen;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JenisDokumen>
 */
class JenisDokumenFactory extends Factory
{
    protected $model = JenisDokumen::class;

    public function definition(): array
    {
        $kelompok = fake()->randomElement(KelompokJenisDokumen::cases());

        return [
            'kode' => 'J'.Str::upper(Str::random(8)),
            'nama' => 'Jenis '.fake()->unique()->words(3, true),
            'singkatan' => null,
            'kelompok' => $kelompok,
            'urutan' => fake()->numberBetween(1, 900),
            'wajib_nomor' => $kelompok->wajibNomor(),
            'aktif' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(['aktif' => false]);
    }
}
