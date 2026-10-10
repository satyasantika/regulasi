<?php

namespace App\Actions\Berkas;

use App\Contracts\PenyimpananBerkas;
use App\Models\TautanBerkas;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Menghapus (soft delete) tautan berkas; berkas aslinya di Drive/sumber resmi tidak tersentuh.
 */
class HapusTautanBerkas
{
    public function __construct(private readonly PenyimpananBerkas $penyimpanan) {}

    public function handle(TautanBerkas $tautan, User $oleh): void
    {
        Gate::forUser($oleh)->authorize('delete', $tautan);

        $this->penyimpanan->hapus($tautan, $oleh);
    }
}
