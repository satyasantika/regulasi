<?php

namespace App\Actions\Api;

use App\Models\KlienApi;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

/**
 * Membuat token Sanctum read-only untuk klien API (US-API-02, BR-18): ability `dokumen:baca` selalu, ditambah
 * `dokumen:baca-internal` bila dicentang; masa berlaku 90/180/365 hari. Token polos hanya tersedia di nilai
 * kembalian ini (yang tersimpan hanya hash SHA-256). Dicatat ke log aktivitas tanpa token polos.
 */
class BuatTokenKlienApi
{
    public function handle(KlienApi $klien, string $nama, bool $bacaInternal, int $hariBerlaku, User $oleh): NewAccessToken
    {
        Gate::forUser($oleh)->authorize('kelolaToken', $klien);

        $nama = trim($nama);

        if ($nama === '' || mb_strlen($nama) > 100) {
            throw ValidationException::withMessages(['nama' => 'Nama token wajib diisi (maksimal 100 karakter).']);
        }

        if (! in_array($hariBerlaku, KlienApi::MASA_BERLAKU_HARI, true)) {
            throw ValidationException::withMessages(['masa_berlaku' => 'Masa berlaku token harus 90, 180, atau 365 hari.']);
        }

        $abilities = $bacaInternal
            ? [KlienApi::ABILITY_BACA, KlienApi::ABILITY_BACA_INTERNAL]
            : [KlienApi::ABILITY_BACA];

        $token = $klien->createToken($nama, $abilities, now()->addDays($hariBerlaku));

        activity('klien-api')
            ->performedOn($klien)
            ->causedBy($oleh)
            ->event('token-dibuat')
            ->withProperties([
                'token_id' => $token->accessToken->getKey(),
                'nama_token' => $nama,
                'abilities' => $abilities,
                'kedaluwarsa_pada' => $token->accessToken->expires_at?->toIso8601String(),
            ])
            ->log('Token API dibuat');

        return $token;
    }
}
