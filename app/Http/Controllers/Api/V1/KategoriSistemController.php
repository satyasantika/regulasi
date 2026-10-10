<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatusPublikasi;
use App\Enums\Visibilitas;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\KategoriSistemResource;
use App\Models\KategoriSistem;
use App\Models\KlienApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /api/v1/kategori-sistem: kategori aktif terurut dengan meta.jumlah_dokumen terbit yang terlihat oleh token.
 */
class KategoriSistemController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $sertakanInternal = KlienApi::bolehBacaInternal($request);

        $kategori = KategoriSistem::query()
            ->aktif()
            ->terurut()
            ->withCount(['dokumen as jumlah_dokumen' => fn (Builder $q) => $q->where('status_publikasi', StatusPublikasi::Terbit->value)->when(! $sertakanInternal, fn (Builder $p) => $p->where('visibilitas', Visibilitas::Publik->value))])
            ->get();

        return KategoriSistemResource::collection($kategori)->additional(['meta' => ['total' => $kategori->count()]]);
    }
}
