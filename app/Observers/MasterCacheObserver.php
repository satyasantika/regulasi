<?php

namespace App\Observers;

use App\Support\CacheRegulasi;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;

/**
 * Membersihkan cache master (02-ARSITEKTUR §3.1) setelah master disimpan, dihapus, atau dipulihkan.
 */
class MasterCacheObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Model $model): void
    {
        $this->lupakan($model);
    }

    public function deleted(Model $model): void
    {
        $this->lupakan($model);
    }

    public function restored(Model $model): void
    {
        $this->lupakan($model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->lupakan($model);
    }

    private function lupakan(Model $model): void
    {
        $nama = CacheRegulasi::namaMasterUntuk($model);

        if ($nama !== null) {
            CacheRegulasi::lupakanMaster($nama);
        }
    }
}
