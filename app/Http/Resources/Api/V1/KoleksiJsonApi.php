<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;

/**
 * Koleksi JSON:API v1 dengan `included` unik per type+id (lihat SumberDayaJsonApi).
 */
class KoleksiJsonApi extends AnonymousResourceCollection
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function with($request): array
    {
        return SumberDayaJsonApi::unikkanIncluded(parent::with($request));
    }
}
