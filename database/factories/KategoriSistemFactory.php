<?php

namespace Database\Factories;

use App\Models\KategoriSistem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KategoriSistem>
 */
class KategoriSistemFactory extends Factory
{
    protected $model = KategoriSistem::class;

    public function definition(): array
    {
        return [
            'kode' => 'k-'.Str::lower(Str::random(8)),
            'nama' => 'Kategori '.fake()->unique()->words(2, true),
            'ikon' => 'heroicon-o-folder',
            'warna' => 'gray',
            'urutan' => fake()->numberBetween(1, 900),
            'aktif' => true,
        ];
    }
}
