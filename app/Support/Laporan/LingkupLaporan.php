<?php

namespace App\Support\Laporan;

use App\Enums\Peran;
use App\Models\Dokumen;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder as KontrakBuilder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lingkup data laporan & dasbor (US-LAP-01, BR-03): pemegang dokumen.lihat-semua melihat angka global;
 * selain itu (kontributor) hanya dokumen yang memiliki minimal satu kategori sistem yang ditugaskan.
 */
final readonly class LingkupLaporan
{
    /**
     * @param  list<string>|null  $kategoriIds  null = semua kategori (global)
     */
    private function __construct(
        public string $peran,
        public ?array $kategoriIds,
    ) {}

    public static function untuk(User $pengguna): self
    {
        if ($pengguna->can('dokumen.lihat-semua')) {
            $peran = $pengguna->hasRole(Peran::SuperAdmin->value) ? Peran::SuperAdmin->value : Peran::AdminRegulasi->value;

            return new self($peran, null);
        }

        $kategori = $pengguna->kategoriSistem()
            ->pluck('kategori_sistem.id')
            ->map(fn (mixed $id): string => (string) $id)
            ->sort()
            ->values()
            ->all();

        return new self(Peran::Kontributor->value, $kategori);
    }

    public static function global(): self
    {
        return new self(Peran::AdminRegulasi->value, null);
    }

    public function adalahGlobal(): bool
    {
        return $this->kategoriIds === null;
    }

    /**
     * Hash kategori untuk kunci cache: "semua" bila global.
     */
    public function hashKategori(): string
    {
        return $this->kategoriIds === null ? 'semua' : substr(hash('sha256', implode(',', $this->kategoriIds)), 0, 16);
    }

    /**
     * Saring query Eloquent dokumen ke lingkup ini.
     *
     * @param  Builder<Dokumen>  $query
     * @return Builder<Dokumen>
     */
    public function terapkan(Builder $query): Builder
    {
        $this->terapkanPadaKolom($query->getQuery(), 'dokumen.id');

        return $query;
    }

    /**
     * Saring query builder dasar pada kolom dokumen tertentu (mis. log_buka_tautan.dokumen_id).
     */
    public function terapkanPadaKolom(KontrakBuilder $query, string $kolomDokumen): KontrakBuilder
    {
        if ($this->kategoriIds === []) {
            $query->whereRaw('1 = 0');
        } elseif ($this->kategoriIds !== null) {
            $kategori = $this->kategoriIds;
            $query->whereIn($kolomDokumen, fn ($sub) => $sub->select('dokumen_id')
                ->from('dokumen_kategori_sistem')
                ->whereIn('kategori_sistem_id', $kategori));
        }

        return $query;
    }
}
