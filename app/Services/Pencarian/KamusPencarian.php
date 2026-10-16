<?php

namespace App\Services\Pencarian;

use App\Models\IstilahPencarian;
use App\Models\SinonimPencarian;
use App\Support\SinonimPencarian as PemetaSinonim;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Akses baca kamus pencarian (istilah dan sinonim) dengan cache 60 menit (02-ARSITEKTUR §5.1). Hanya larik/skalar
 * yang disimpan di cache (cache.serializable_classes = false). Dibersihkan oleh BangunKamus.
 */
class KamusPencarian
{
    public const string KUNCI_ISTILAH = 'pencarian:kamus';

    public const string KUNCI_SINONIM = 'pencarian:sinonim';

    public const int TTL_DETIK = 60 * 60;

    /**
     * @return array<string, int> istilah => frekuensi
     */
    public function istilah(): array
    {
        /** @var array<string, int> */
        return Cache::remember(self::KUNCI_ISTILAH, self::TTL_DETIK, fn (): array => IstilahPencarian::query()
            ->pluck('frekuensi', 'istilah')
            ->map(fn (mixed $f): int => (int) $f)
            ->all());
    }

    /**
     * Sinonim dua arah: istilah => daftar padanan. Bila tabel kosong (belum disemai), memakai config/regulasi.php.
     *
     * @return array<string, list<string>>
     */
    public function sinonim(): array
    {
        /** @var array<string, list<string>> */
        return Cache::remember(self::KUNCI_SINONIM, self::TTL_DETIK, function (): array {
            $peta = [];

            foreach (SinonimPencarian::query()->get(['istilah', 'sinonim']) as $baris) {
                $peta[$baris->istilah][] = $baris->sinonim;
            }

            return $peta !== [] ? $peta : PemetaSinonim::duaArah((array) config('regulasi.sinonim', []));
        });
    }

    /**
     * Muat sinonim dari config/regulasi.php ke tabel (idempoten, dua arah). Mengembalikan jumlah baris baru.
     */
    public function semaiSinonim(): int
    {
        $baru = 0;

        foreach (PemetaSinonim::duaArah((array) config('regulasi.sinonim', [])) as $istilah => $padanan) {
            foreach ($padanan as $sinonim) {
                $baru += DB::table('sinonim_pencarian')->insertOrIgnore([
                    'id' => (string) Str::uuid7(),
                    'istilah' => mb_substr($istilah, 0, 100),
                    'sinonim' => mb_substr($sinonim, 0, 255),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $baru;
    }

    public function lupakan(): void
    {
        Cache::forget(self::KUNCI_ISTILAH);
        Cache::forget(self::KUNCI_SINONIM);
    }
}
