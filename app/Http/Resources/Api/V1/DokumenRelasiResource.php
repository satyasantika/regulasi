<?php

namespace App\Http\Resources\Api\V1;

use App\Models\DokumenRelasi;
use Illuminate\Http\Request;

/**
 * Relasi status dokumen (type `relasi`): jenis relasi dari sisi dokumen induk, efektif, dan ringkasan dokumen terkait.
 *
 * @mixin DokumenRelasi
 *
 * @property DokumenRelasi $resource
 */
class DokumenRelasiResource extends SumberDayaJsonApi
{
    public function toType(Request $request): string
    {
        return 'relasi';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        $terkait = $this->resource->dokumenTerkait;

        return [
            'jenis_relasi' => $this->resource->jenis_relasi->value,
            'efektif' => $this->resource->efektif,
            'efektif_pada' => $this->resource->efektif_pada?->toIso8601String(),
            'keterangan' => $this->resource->keterangan,
            'dokumen_terkait' => [
                'id' => $terkait->id,
                'sebutan_singkat' => $terkait->sebutan_singkat,
                'status_berlaku' => $terkait->status_berlaku->value,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toLinks(Request $request): array
    {
        return ['dokumen_terkait' => route('api.v1.dokumen.show', $this->resource->dokumen_terkait_id)];
    }
}
