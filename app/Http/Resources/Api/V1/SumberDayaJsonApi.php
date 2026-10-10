<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * Dasar resource JSON:API v1 (JsonApiResource bawaan Laravel 13). Menyeragamkan `included`: satu objek per
 * pasangan type+id (JSON:API 1.1 §7.4) — bawaan Laravel menggandakan objek dari relasi BelongsToMany.
 */
abstract class SumberDayaJsonApi extends JsonApiResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function with($request): array
    {
        return self::unikkanIncluded(parent::with($request));
    }

    /**
     * @param  array<string, mixed>  $tambahan
     * @return array<string, mixed>
     */
    public static function unikkanIncluded(array $tambahan): array
    {
        if (isset($tambahan['included']) && is_array($tambahan['included'])) {
            $tambahan['included'] = collect($tambahan['included'])
                ->unique(fn (array $objek): string => $objek['type'].':'.$objek['id'])
                ->values()
                ->all();
        }

        return $tambahan;
    }

    /**
     * @param  mixed  $resource
     */
    protected static function newCollection($resource): AnonymousResourceCollection
    {
        return new KoleksiJsonApi($resource, static::class);
    }
}
