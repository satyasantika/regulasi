<?php

namespace App\Actions\Dokumen;

use App\Enums\JenisRiwayatStatus;
use App\Enums\StatusPublikasi;
use App\Models\Dokumen;
use App\Models\RiwayatStatusDokumen;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kerangka bersama aksi alur publikasi (PRD §6.1, 02-ARSITEKTUR §3.3):
 * Cache::lock("lock:publikasi:{id}") → DB::transaction → lockForUpdate baris dokumen → validasi transisi
 * (StatusPublikasi::bolehTransisiKe) → ubah status → tulis riwayat (jenis publikasi) → efek samping di afterCommit.
 * Otorisasi dilakukan oleh subkelas sebelum memanggil jalankan().
 */
abstract class TransisiPublikasi
{
    public function __construct(protected readonly PeriksaKelengkapan $periksaKelengkapan) {}

    /**
     * @param  list<StatusPublikasi>  $dari  Status asal yang diterima aksi ini.
     * @param  (Closure(Dokumen, StatusPublikasi): void)|null  $ubah  Pengubahan kolom tambahan sebelum disimpan.
     */
    protected function jalankan(
        Dokumen $dokumen,
        User $oleh,
        array $dari,
        StatusPublikasi $ke,
        ?string $catatan = null,
        bool $wajibLengkap = false,
        ?Closure $ubah = null,
    ): Dokumen {
        $kunci = Cache::lock('lock:publikasi:'.$dokumen->getKey(), (int) config('regulasi.kunci_publikasi.detik', 10));

        /** @var Dokumen */
        return $kunci->block((int) config('regulasi.kunci_publikasi.tunggu', 5), fn (): Dokumen => DB::transaction(
            function () use ($dokumen, $oleh, $dari, $ke, $catatan, $wajibLengkap, $ubah): Dokumen {
                /** @var Dokumen $baris */
                $baris = Dokumen::query()->whereKey($dokumen->getKey())->lockForUpdate()->firstOrFail();
                $asal = $baris->status_publikasi;

                if (! in_array($asal, $dari, true) || ! $asal->bolehTransisiKe($ke)) {
                    throw ValidationException::withMessages([
                        'status_publikasi' => "Dokumen berstatus \"{$asal->getLabel()}\" tidak dapat diubah menjadi \"{$ke->getLabel()}\".",
                    ]);
                }

                if ($wajibLengkap) {
                    $kekurangan = $this->periksaKelengkapan->handle($baris);

                    if ($kekurangan !== []) {
                        throw ValidationException::withMessages(['kelengkapan' => $kekurangan]);
                    }
                }

                $baris->status_publikasi = $ke;

                if ($ubah !== null) {
                    $ubah($baris, $asal);
                }

                $baris->save();

                RiwayatStatusDokumen::query()->create([
                    'dokumen_id' => $baris->getKey(),
                    'jenis' => JenisRiwayatStatus::Publikasi,
                    'dari' => $asal->value,
                    'ke' => $ke->value,
                    'catatan' => filled($catatan) ? trim($catatan) : null,
                    'oleh' => $oleh->getKey(),
                ]);

                DB::afterCommit(fn () => $this->setelahCommit($baris, $asal, $oleh));

                return $baris;
            },
        ));
    }

    /**
     * Efek samping setelah transaksi berhasil di-commit (cache, indeks, notifikasi kelak).
     */
    protected function setelahCommit(Dokumen $dokumen, StatusPublikasi $asal, User $oleh): void {}

    /**
     * Catatan wajib dengan panjang minimum (BR-04, BR-12).
     */
    protected function wajibCatatan(?string $catatan, int $minimum, string $konteks): string
    {
        $catatan = trim((string) $catatan);

        if (mb_strlen($catatan) < $minimum) {
            throw ValidationException::withMessages([
                'catatan' => "Catatan {$konteks} wajib diisi minimal {$minimum} karakter.",
            ]);
        }

        return $catatan;
    }
}
