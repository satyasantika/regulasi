<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\EksporLaporanGagal;
use App\Notifications\EksporLaporanSiap;
use App\Support\Laporan\KatalogEkspor;
use App\Support\Laporan\PenulisExcel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Menyusun ekspor Excel laporan di antrean `ekspor` (STANDAR-TEKNIS §1a.6): berkas SEMENTARA di
 * storage/app/tmp/ekspor/{uuid}.xlsx (dihapus ≤ 24 jam oleh berkas:bersihkan-tmp), lalu peminta menerima
 * notifikasi database berisi tautan unduh bertanda tangan yang hanya berlaku untuk dirinya.
 */
class BuatEksporLaporan implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 2;

    /**
     * @param  array<string, mixed>  $parameter
     */
    public function __construct(
        public string $jenis,
        public array $parameter,
        public string $penggunaId,
    ) {
        $this->onConnection('database-berat')->onQueue('berat');
    }

    public static function direktori(): string
    {
        return trim((string) config('regulasi.direktori_ekspor', 'ekspor'), '/') ?: 'ekspor';
    }

    public static function jalur(string $berkas): string
    {
        return storage_path('app/tmp/'.self::direktori().'/'.$berkas);
    }

    public function handle(): void
    {
        $pengguna = User::query()->find($this->penggunaId);

        if (! $pengguna instanceof User || ! $pengguna->can('laporan.ekspor')) {
            return;
        }

        $data = KatalogEkspor::bangun($this->jenis, $this->parameter, $pengguna);
        $berkas = Str::uuid7()->toString().'.xlsx';
        $jalur = self::jalur($berkas);
        File::ensureDirectoryExists(dirname($jalur));

        try {
            PenulisExcel::tulis($data, $jalur);
        } catch (Throwable $e) {
            File::delete($jalur);

            throw $e;
        }

        $pengguna->notify(new EksporLaporanSiap($data->judul, $berkas, KatalogEkspor::namaBerkas($this->jenis), (string) $pengguna->getKey()));
    }

    public function failed(?Throwable $e): void
    {
        User::query()->find($this->penggunaId)?->notify(new EksporLaporanGagal($this->jenis));
    }
}
