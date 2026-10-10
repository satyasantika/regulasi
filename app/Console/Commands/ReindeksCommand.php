<?php

namespace App\Console\Commands;

use App\Enums\StatusPublikasi;
use App\Models\Dokumen;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Bangun ulang indeks pencarian (F7.3): terapkan pengaturan indeks (sinonim, filter, sort), opsional kosongkan
 * indeks, lalu impor ulang semua dokumen terbit secara sinkron.
 */
#[Signature('regulasi:reindeks {--hapus : Kosongkan indeks sebelum impor ulang}')]
#[Description('Terapkan pengaturan indeks Meilisearch dan impor ulang dokumen terbit')]
class ReindeksCommand extends Command
{
    public function handle(): int
    {
        if (config('scout.driver') === 'meilisearch') {
            $this->call('scout:sync-index-settings');
        }

        if ($this->option('hapus')) {
            Dokumen::removeAllFromSearch();
            $this->info('Indeks '.(new Dokumen)->searchableAs().' dikosongkan.');
        }

        $jumlah = 0;

        Dokumen::query()
            ->where('status_publikasi', StatusPublikasi::Terbit->value)
            ->with(Dokumen::RELASI_INDEKS)
            ->chunkById(200, function (Collection $dokumen) use (&$jumlah): void {
                $dokumen->first()?->syncMakeSearchable($dokumen);
                $jumlah += $dokumen->count();
            });

        $this->info("{$jumlah} dokumen terbit diindeks ulang ke ".(new Dokumen)->searchableAs().'.');

        return self::SUCCESS;
    }
}
