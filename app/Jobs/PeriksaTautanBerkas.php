<?php

namespace App\Jobs;

use App\Enums\StatusCekTautan;
use App\Enums\StatusPublikasi;
use App\Jobs\Concerns\DibatasiPerHost;
use App\Models\TautanBerkas;
use App\Notifications\TautanTidakDapatDiakses;
use App\Services\Berkas\PengunduhAman;
use App\Support\PenerimaNotifikasi;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Pemeriksaan keteraksesan tautan (antrean `tautan`, 02-ARSITEKTUR §3.2/§4.1, US-TAU-03): HEAD lalu GET Range
 * 0-1023 lewat PengunduhAman tanpa menyimpan isi; mengisi status_cek & dicek_pada. Informatif — tidak memblokir
 * simpan/terbit. Berubah menjadi tidak_dapat_diakses → peringatan log + notifikasi TautanTidakDapatDiakses ke penambah
 * tautan dan (bila dokumen terbit) admin-regulasi (02-ARSITEKTUR §10).
 */
class PeriksaTautanBerkas implements ShouldBeUnique, ShouldQueue
{
    use DibatasiPerHost, Queueable;

    public int $tries = 2;

    public int $timeout = 30;

    public int $uniqueFor = 600;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public TautanBerkas $tautan)
    {
        $this->onQueue('tautan');
    }

    public function uniqueId(): string
    {
        return (string) $this->tautan->getKey();
    }

    public function handle(PengunduhAman $pengunduh): void
    {
        $tautan = TautanBerkas::query()->find($this->tautan->getKey());

        if ($tautan === null) {
            return;
        }

        if ($this->melebihiBatasHost($tautan->url)) {
            $this->release(60);

            return;
        }

        $statusLama = $tautan->status_cek;
        $status = $pengunduh->periksa($tautan->url);

        $tautan->forceFill(['status_cek' => $status, 'dicek_pada' => now()])->saveQuietly();

        if ($status === StatusCekTautan::TidakDapatDiakses && $statusLama !== StatusCekTautan::TidakDapatDiakses) {
            Log::warning('Tautan berkas tidak dapat diakses.', [
                'tautan_berkas_id' => $tautan->getKey(),
                'pemilik_id' => $tautan->pemilik_id,
                'ditambahkan_oleh' => $tautan->ditambahkan_oleh,
            ]);

            $this->beriTahu($tautan);
        }
    }

    private function beriTahu(TautanBerkas $tautan): void
    {
        $dokumen = $tautan->dokumen();

        if ($dokumen === null || $dokumen->trashed()) {
            return;
        }

        $penerima = PenerimaNotifikasi::pengguna($tautan->ditambahkan_oleh)
            ->merge($dokumen->status_publikasi === StatusPublikasi::Terbit ? PenerimaNotifikasi::admin() : new Collection);

        PenerimaNotifikasi::kirim($penerima, new TautanTidakDapatDiakses($tautan, $dokumen));
    }
}
