<?php

namespace App\Http\Resources\Api\V1;

use App\Models\KategoriSistem;
use Illuminate\Http\Request;

/**
 * Kategori sistem FKIP (type `kategori-sistem`). meta.jumlah_dokumen hanya pada GET /api/v1/kategori-sistem.
 *
 * @mixin KategoriSistem
 *
 * @property KategoriSistem $resource
 */
class KategoriSistemResource extends SumberDayaJsonApi
{
    public function toType(Request $request): string
    {
        return 'kategori-sistem';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'kode' => $this->resource->kode,
            'nama' => $this->resource->nama,
            'slug' => $this->resource->slug,
            'deskripsi' => $this->resource->deskripsi,
            'ikon' => $this->resource->ikon,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toLinks(Request $request): array
    {
        return ['html' => route('dokumen.kategori', $this->resource->slug)];
    }

    /**
     * @return array<string, mixed>
     */
    public function toMeta(Request $request): array
    {
        $atribut = $this->resource->getAttributes();

        return array_key_exists('jumlah_dokumen', $atribut) ? ['jumlah_dokumen' => (int) $atribut['jumlah_dokumen']] : [];
    }
}
