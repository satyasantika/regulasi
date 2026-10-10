<?php

namespace App\Http\Resources\Api\V1;

use App\Contracts\PenyimpananBerkas;
use App\Enums\Visibilitas;
use App\Models\KlienApi;
use App\Models\TautanBerkas;
use Illuminate\Http\Request;

/**
 * Tautan berkas (type `tautan`) TANPA URL mentah (BR-17): links.buka untuk dokumen publik = /d/{id}/buka;
 * untuk dokumen internal = URL bertanda tangan 10 menit ke /d/{id}/buka-api yang mencatat klien API.
 *
 * @mixin TautanBerkas
 *
 * @property TautanBerkas $resource
 */
class TautanBerkasResource extends SumberDayaJsonApi
{
    public function toType(Request $request): string
    {
        return 'tautan';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'label' => $this->resource->label,
            'jenis' => $this->resource->jenis->value,
            'penyedia' => $this->resource->penyedia->value,
            'status_cek' => $this->resource->status_cek->value,
            'jumlah_halaman' => $this->resource->ekstraksi?->jumlah_halaman,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toLinks(Request $request): array
    {
        $penyimpanan = app(PenyimpananBerkas::class);
        $klien = KlienApi::dariPermintaan($request);

        if ($this->resource->dokumen()?->visibilitas === Visibilitas::Internal && $klien !== null) {
            return ['buka' => $penyimpanan->urlBukaApi($this->resource, $klien)];
        }

        return ['buka' => $penyimpanan->urlBuka($this->resource)];
    }
}
