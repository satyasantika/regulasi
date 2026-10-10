<?php

namespace Database\Factories;

use App\Enums\StatusPublikasi;
use App\Enums\Visibilitas;
use App\Models\BadanPenetap;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Dokumen>
 */
class DokumenFactory extends Factory
{
    protected $model = Dokumen::class;

    public function definition(): array
    {
        $nomor = (string) fake()->unique()->numberBetween(1, 999999);
        $tahun = fake()->numberBetween(2000, 2026);
        $judul = Str::ucfirst(fake()->words(5, true));

        return [
            'jenis_dokumen_id' => JenisDokumen::factory(),
            'badan_penetap_id' => BadanPenetap::factory(),
            'nomor' => $nomor,
            'tahun' => $tahun,
            'judul' => $judul,
            'sebutan' => "Peraturan Contoh Nomor {$nomor} Tahun {$tahun}",
            'sebutan_singkat' => "PC {$nomor}/{$tahun}",
            'slug' => Str::slug("pc {$nomor} {$tahun} {$judul}").'-'.Str::lower(Str::random(6)),
            'visibilitas' => Visibilitas::Publik,
            'status_publikasi' => StatusPublikasi::Draf,
        ];
    }

    public function terbit(): static
    {
        return $this->state(fn (): array => [
            'status_publikasi' => StatusPublikasi::Terbit,
            'dipublikasikan_pada' => now(),
        ]);
    }

    public function internal(): static
    {
        return $this->state(['visibilitas' => Visibilitas::Internal]);
    }

    /**
     * Dokumen rujukan: metadata saja, langsung terbit (BR-11).
     */
    public function rujukan(): static
    {
        return $this->terbit()->state(['tanpa_berkas' => true]);
    }

    public function berstatus(StatusPublikasi $status): static
    {
        return $this->state(['status_publikasi' => $status]);
    }
}
