<?php

namespace App\Console\Commands;

use App\Enums\StatusCekTautan;
use App\Enums\StatusPublikasi;
use App\Jobs\PeriksaTautanBerkas;
use App\Models\Dokumen;
use App\Models\TautanBerkas;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pemeriksaan keteraksesan mingguan (02-ARSITEKTUR §9): antrekan PeriksaTautanBerkas untuk semua tautan dokumen
 * terbit. Dijeda per host: job ke-n untuk satu host ditunda floor(n / batas per menit) menit sehingga pembatas
 * `unduh-host:{host}` jarang terlampaui (bila tetap terlampaui, job dilepas ulang sendiri).
 */
#[Signature('berkas:periksa-tautan {--dokumen=* : Batasi ke ID dokumen tertentu}')]
#[Description('Antrekan pemeriksaan keteraksesan semua tautan dokumen terbit')]
class PeriksaTautanCommand extends Command
{
    public function handle(): int
    {
        $dokumen = array_values(array_filter((array) $this->option('dokumen'), 'filled'));
        $batas = max(1, (int) config('regulasi.unduh_per_menit_per_host', 30));
        $perHost = [];
        $perStatus = [];

        $query = TautanBerkas::query()
            ->whereHasMorph('pemilik', [Dokumen::class], function (Builder $q) use ($dokumen): void {
                $q->where('status_publikasi', StatusPublikasi::Terbit->value);

                if ($dokumen !== []) {
                    $q->whereKey($dokumen);
                }
            });

        foreach ($query->lazyById(200) as $tautan) {
            $host = strtolower((string) parse_url($tautan->url, PHP_URL_HOST));
            $urutan = $perHost[$host] ?? 0;
            $perHost[$host] = $urutan + 1;
            $perStatus[$tautan->status_cek->value] = ($perStatus[$tautan->status_cek->value] ?? 0) + 1;

            $job = new PeriksaTautanBerkas($tautan);
            $tunda = intdiv($urutan, $batas) * 60;

            if ($tunda > 0) {
                $job->delay(now()->addSeconds($tunda));
            }

            dispatch($job);
        }

        $this->table(
            ['Status cek terakhir', 'Jumlah tautan'],
            collect(StatusCekTautan::cases())->map(fn (StatusCekTautan $s): array => [$s->getLabel(), $perStatus[$s->value] ?? 0])->all(),
        );
        $this->info(array_sum($perStatus).' tautan dari '.count($perHost).' host diantrekan untuk diperiksa.');

        return self::SUCCESS;
    }
}
