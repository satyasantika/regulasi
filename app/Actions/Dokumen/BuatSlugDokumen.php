<?php

namespace App\Actions\Dokumen;

use App\Models\Dokumen;
use Illuminate\Support\Str;

/**
 * Slug dokumen (BR-19): Str::slug(sebutan_singkat + judul), maksimal 200 karakter, unik (-2, -3, …),
 * termasuk terhadap dokumen yang dihapus lunak.
 */
class BuatSlugDokumen
{
    public const int PANJANG_MAKS = 200;

    public function handle(Dokumen $dokumen): string
    {
        $teks = str_replace(['/', '.'], ' ', "{$dokumen->sebutan_singkat} {$dokumen->judul}");
        $dasar = rtrim(Str::substr(Str::slug($teks), 0, self::PANJANG_MAKS), '-') ?: 'dokumen';

        $slug = $dasar;
        $nomor = 2;

        while ($this->terpakai($slug, $dokumen)) {
            $akhiran = "-{$nomor}";
            $slug = rtrim(Str::substr($dasar, 0, self::PANJANG_MAKS - strlen($akhiran)), '-').$akhiran;
            $nomor++;
        }

        return $slug;
    }

    private function terpakai(string $slug, Dokumen $dokumen): bool
    {
        return Dokumen::withTrashed()
            ->where('slug', $slug)
            ->when($dokumen->exists, fn ($q) => $q->whereKeyNot($dokumen->getKey()))
            ->exists();
    }
}
