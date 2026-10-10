<?php

namespace App\Http\Resources\Api\V1;

use App\Models\JenisDokumen;
use Illuminate\Http\Request;

/**
 * Jenis dokumen (type `jenis-dokumen`) dengan meta.jumlah_dokumen terbit yang terlihat oleh token.
 *
 * @mixin JenisDokumen
 *
 * @property JenisDokumen $resource
 */
class JenisDokumenResource extends SumberDayaJsonApi
{
    public function toType(Request $request): string
    {
        return 'jenis-dokumen';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'kode' => $this->resource->kode,
            'nama' => $this->resource->nama,
            'singkatan' => $this->resource->singkatan,
            'slug' => $this->resource->slug,
            'kelompok' => $this->resource->kelompok->value,
            'deskripsi' => $this->resource->deskripsi,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toLinks(Request $request): array
    {
        return ['html' => route('dokumen.jenis', $this->resource->slug)];
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
