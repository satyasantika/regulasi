<?php

namespace App\Actions\Relasi;

use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Kunci status berlaku (02-ARSITEKTUR §3.3): `lock:status-dokumen:{id}` untuk setiap dokumen yang terlibat,
 * diambil berurutan id menaik agar dua admin yang menyentuh pasangan dokumen yang sama tidak saling mengunci,
 * lalu dijalankan di dalam DB::transaction. Pemanggil tetap melakukan lockForUpdate() pada baris dokumen.
 */
class KunciStatusDokumen
{
    /**
     * @template T
     *
     * @param  list<string>  $dokumenId
     * @param  Closure(): T  $proses
     * @return T
     */
    public function jalankan(array $dokumenId, Closure $proses): mixed
    {
        $dokumenId = array_values(array_unique($dokumenId));
        sort($dokumenId, SORT_STRING);

        $detik = (int) config('regulasi.kunci_status.detik', 15);
        $tunggu = (int) config('regulasi.kunci_status.tunggu', 5);

        /** @var list<Lock> $terkunci */
        $terkunci = [];

        try {
            foreach ($dokumenId as $id) {
                $kunci = Cache::lock(self::kunci($id), $detik);
                $kunci->block($tunggu);
                $terkunci[] = $kunci;
            }

            return DB::transaction($proses);
        } finally {
            foreach (array_reverse($terkunci) as $kunci) {
                $kunci->release();
            }
        }
    }

    public static function kunci(string $dokumenId): string
    {
        return 'lock:status-dokumen:'.$dokumenId;
    }
}
