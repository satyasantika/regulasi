<?php

namespace App\Support;

use App\Models\BadanPenetap;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Pembungkus cache Redis aplikasi (02-ARSITEKTUR §3.1). Kunci ditulis tanpa prefix `regulasi:`.
 */
class CacheRegulasi
{
    public const int TTL_MASTER_DETIK = 60 * 60 * 24;

    /**
     * Nama master yang dapat di-cache beserta modelnya.
     *
     * @var array<string, class-string<JenisDokumen|KategoriSistem|BadanPenetap>>
     */
    public const array MASTER = [
        'jenis-dokumen' => JenisDokumen::class,
        'kategori-sistem' => KategoriSistem::class,
        'badan-penetap' => BadanPenetap::class,
    ];

    /**
     * Daftar master aktif dan terurut (urutan lalu nama), di-cache 24 jam.
     *
     * @return Collection<int, JenisDokumen>|Collection<int, KategoriSistem>|Collection<int, BadanPenetap>
     */
    public static function master(string $nama): Collection
    {
        $model = self::modelMaster($nama);

        return Cache::remember(self::kunciMaster($nama), self::TTL_MASTER_DETIK, fn (): Collection => $model::query()->aktif()->terurut()->get());
    }

    public static function lupakanMaster(string $nama): void
    {
        self::modelMaster($nama);

        Cache::forget(self::kunciMaster($nama));
    }

    /**
     * Nama master untuk sebuah model (dipakai observer), atau null bila bukan master ber-cache.
     */
    public static function namaMasterUntuk(Model $model): ?string
    {
        $nama = array_search($model::class, self::MASTER, true);

        return $nama === false ? null : $nama;
    }

    public static function kunciMaster(string $nama): string
    {
        return "master:{$nama}";
    }

    /**
     * @return class-string<JenisDokumen|KategoriSistem|BadanPenetap>
     */
    private static function modelMaster(string $nama): string
    {
        return self::MASTER[$nama] ?? throw new InvalidArgumentException("Master [{$nama}] tidak dikenal.");
    }
}
