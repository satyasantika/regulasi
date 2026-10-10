<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatusPublikasi;
use App\Enums\Visibilitas;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\JenisDokumenResource;
use App\Models\JenisDokumen;
use App\Models\KlienApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/jenis-dokumen: jenis aktif terurut dengan meta.jumlah_dokumen terbit yang terlihat oleh token (BR-01, BR-02).
 */
class JenisDokumenController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $sertakanInternal = KlienApi::bolehBacaInternal($request);

        $jenis = JenisDokumen::query()
            ->aktif()
            ->terurut()
            ->withCount(['dokumen as jumlah_dokumen' => fn (Builder $q) => $q->where('status_publikasi', StatusPublikasi::Terbit->value)->when(! $sertakanInternal, fn (Builder $p) => $p->where('visibilitas', Visibilitas::Publik->value))])
            ->get();

        return JenisDokumenResource::collection($jenis)->additional(['meta' => ['total' => $jenis->count()]]);
    }
}
