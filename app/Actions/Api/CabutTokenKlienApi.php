<?php

namespace App\Actions\Api;

use App\Models\KlienApi;
use App\Models\TokenAkses;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Mencabut (menghapus) satu token klien API (US-API-02); permintaan berikutnya dengan token itu → 401.
 */
class CabutTokenKlienApi
{
    public function handle(KlienApi $klien, TokenAkses $token, User $oleh): void
    {
        Gate::forUser($oleh)->authorize('kelolaToken', $klien);

        abort_unless($token->tokenable_type === $klien->getMorphClass() && $token->tokenable_id === $klien->getKey(), 404);

        $token->delete();

        activity('klien-api')
            ->performedOn($klien)
            ->causedBy($oleh)
            ->event('token-dicabut')
            ->withProperties(['token_id' => $token->getKey(), 'nama_token' => $token->name])
            ->log('Token API dicabut');
    }
}
