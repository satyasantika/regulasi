<?php

namespace App\Actions\Pengguna;

use App\Models\User;
use App\Support\Impersonasi;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Super-admin masuk sebagai pengguna lain untuk menelusuri masalah (UserPolicy::impersonate: bukan diri sendiri,
 * bukan super-admin lain, akun aktif). Mulai dan akhir tercatat di log aktivitas, dan setiap entri aktivitas selama
 * impersonasi memuat id super-admin asal (Aktivitas). Tantangan MFA dan wajib ganti sandi akun sasaran tidak
 * berlaku selama impersonasi; super-admin sudah lolos MFA-nya sendiri sebelum dapat memulai.
 */
class MulaiImpersonasi
{
    public function handle(User $pelaku, User $sasaran): void
    {
        if (Impersonasi::aktif()) {
            throw new AuthorizationException('Akhiri impersonasi yang sedang berjalan terlebih dahulu.');
        }

        Gate::forUser($pelaku)->authorize('impersonate', $sasaran);

        activity('pengguna')
            ->performedOn($sasaran)
            ->causedBy($pelaku)
            ->event('impersonasi-mulai')
            ->log("Mulai masuk sebagai {$sasaran->email}");

        // Kunci dipasang sebelum login agar pencatat "terakhir masuk" tidak menganggapnya login sasaran.
        $sesi = session()->driver();
        $sesi->put(Impersonasi::KUNCI, $pelaku->getKey());

        Auth::guard('web')->login($sasaran);

        // Hash sandi sesi diselaraskan ke akun sasaran agar AuthenticateSession tidak mengeluarkan sesi.
        $sesi->put('password_hash_web', $sasaran->getAuthPassword());
    }
}
