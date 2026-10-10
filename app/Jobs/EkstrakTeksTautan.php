<?php

namespace App\Jobs;

use App\Contracts\PenyimpananBerkas;
use App\Enums\StatusEkstraksi;
use App\Events\IsiBerkasBerubah;
use App\Events\TeksTautanDiekstraksi;
use App\Exceptions\Berkas\PengunduhanDitolak;
use App\Jobs\Concerns\DibatasiPerHost;
use App\Models\EkstraksiTeks;
use App\Models\TautanBerkas;
use App\Services\Berkas\BerkasSementara;
use App\Services\Berkas\PengekstrakTeksPdf;
use App\Services\Berkas\PenguraiTautan;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Ekstraksi teks PDF di balik tautan (antrean `ekstraksi`, US-EKS-01/02, BR-14, 02-ARSITEKTUR §13.2):
 * unduh sementara lewat PenyimpananBerkas::ambilSementara (PengunduhAman) → pdfinfo → pdftotext → simpan ke
 * ekstraksi_teks → berkas sementara SELALU dihapus di finally.
 *
 * Penolakan terkendali (tidak dapat diunduh, terlalu besar, bukan PDF) langsung menjadi status tanpa retry;
 * galat lain diulang (backoff 60/300/900 dtk) lalu failed() → status gagal.
 */
class EkstrakTeksTautan implements ShouldBeUnique, ShouldQueue
{
    use DibatasiPerHost, Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 3600;

    public function __construct(public string $tautanId)
    {
        $this->onQueue('ekstraksi');
    }

    public function uniqueId(): string
    {
        return $this->tautanId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(PenyimpananBerkas $penyimpanan, PengekstrakTeksPdf $pengekstrak, PenguraiTautan $pengurai): void
    {
        $tautan = TautanBerkas::query()->with('ekstraksi')->find($this->tautanId);

        if ($tautan === null) {
            return;
        }

        $ekstraksi = $tautan->ekstraksi ?? new EkstraksiTeks(['tautan_berkas_id' => $tautan->getKey()]);

        if (! $tautan->jenis->diekstraksi()) {
            $ekstraksi->setelUlang(StatusEkstraksi::TidakBerlaku);
            $ekstraksi->save();

            return;
        }

        if ($this->melebihiBatasHost($pengurai->urlUnduh($tautan->url))) {
            $this->release(60);

            return;
        }

        $ekstraksi->forceFill(['status' => StatusEkstraksi::Diproses])->save();
        $berkas = null;

        try {
            $berkas = $penyimpanan->ambilSementara($tautan);
            $halaman = $pengekstrak->jumlahHalaman($berkas->path);
            $teks = $pengekstrak->teks($berkas->path);

            $this->simpanHasil($tautan, $ekstraksi, $berkas, $halaman, $teks);
        } catch (PengunduhanDitolak $e) {
            $ekstraksi->forceFill([
                'status' => $e->status(),
                'pesan' => mb_substr($e->getMessage(), 0, 500),
                'diekstraksi_pada' => now(),
            ])->save();
        } finally {
            $berkas?->hapus();
        }
    }

    /**
     * Percobaan habis karena galat tak terduga: status gagal, pesan dipotong 500, percobaan bertambah.
     */
    public function failed(?Throwable $galat): void
    {
        $ekstraksi = EkstraksiTeks::query()->firstOrNew(['tautan_berkas_id' => $this->tautanId]);

        if (! $ekstraksi->exists && ! TautanBerkas::withTrashed()->whereKey($this->tautanId)->exists()) {
            return;
        }

        $ekstraksi->forceFill([
            'status' => StatusEkstraksi::Gagal,
            'pesan' => mb_substr($galat?->getMessage() ?? 'Ekstraksi gagal.', 0, 500),
            'percobaan' => min(255, $ekstraksi->percobaan + 1),
        ])->save();
    }

    private function simpanHasil(TautanBerkas $tautan, EkstraksiTeks $ekstraksi, BerkasSementara $berkas, ?int $halaman, string $teks): void
    {
        $jumlahKarakter = mb_strlen($teks);
        $ambang = (int) config('regulasi.ambang_tanpa_teks', 200) * max(1, (int) ceil(($halaman ?? 1) / 10));
        $hashLama = $ekstraksi->hash_sha256;
        $berubah = $hashLama !== null && ! hash_equals($hashLama, $berkas->hashSha256);

        DB::transaction(function () use ($ekstraksi, $berkas, $halaman, $teks, $jumlahKarakter, $ambang, $berubah): void {
            $ekstraksi->forceFill([
                'isi_teks' => $teks === '' ? null : $teks,
                'jumlah_karakter' => $jumlahKarakter,
                'jumlah_halaman' => $halaman === null ? null : min($halaman, 65535),
                'ukuran_unduhan' => $berkas->ukuran,
                'hash_sha256' => $berkas->hashSha256,
                'status' => $jumlahKarakter < $ambang ? StatusEkstraksi::TanpaTeks : StatusEkstraksi::Berhasil,
                'percobaan' => 0,
                'pesan' => null,
                'diekstraksi_pada' => now(),
                'isi_berubah_pada' => $berubah ? now() : $ekstraksi->isi_berubah_pada,
            ])->save();
        });

        if ($berubah) {
            IsiBerkasBerubah::dispatch($tautan, $ekstraksi, (string) $hashLama);
        }

        TeksTautanDiekstraksi::dispatch($tautan, $ekstraksi);
    }
}
