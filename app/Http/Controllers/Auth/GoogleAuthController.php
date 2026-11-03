<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\MasukDenganGoogle;
use App\Exceptions\MasukGoogleDitolak;
use App\Http\Controllers\Controller;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): SymfonyRedirectResponse
    {
        abort_unless(config('services.google.aktif'), 404);

        $user = Auth::user();

        if ($user instanceof User) {
            return redirect()->to($this->tujuan($user) ?? route('filament.admin.auth.login'));
        }

        session(['google_asal' => $request->query('asal')]);

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver('google');

        return $driver->scopes(['openid', 'email', 'profile'])->redirect();
    }

    public function callback(Request $request, MasukDenganGoogle $masuk): RedirectResponse
    {
        abort_unless(config('services.google.aktif'), 404);

        $asal = $request->session()->pull('google_asal');

        if ($request->query('error')) {
            return $this->gagal('Masuk dengan Google dibatalkan.', $asal);
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->gagal('Sesi masuk Google kedaluwarsa. Silakan coba lagi.', $asal);
        } catch (Throwable $e) {
            report($e);

            return $this->gagal('Tidak dapat terhubung dengan Google. Silakan coba lagi.', $asal);
        }

        try {
            $user = $masuk->jalankan($google);
        } catch (MasukGoogleDitolak $e) {
            return $this->gagal($e->getMessage(), $asal);
        }

        $tujuan = $this->tujuan($user);

        if ($tujuan === null) {
            return $this->gagal('Akun Google Anda sudah tercatat, tetapi belum diberi peran. Hubungi admin.', $asal);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->to($tujuan);
    }

    private function tujuan(User $user): ?string
    {
        foreach (['admin', 'akun'] as $id) {
            $panel = Filament::getPanel($id);

            if ($user->canAccessPanel($panel)) {
                return $panel->getUrl();
            }
        }

        return null;
    }

    private function gagal(string $pesan, ?string $asal): RedirectResponse
    {
        $ke = ($asal !== null && str_starts_with($asal, url('/'))) ? $asal : route('filament.admin.auth.login');

        return redirect()->to($ke)->with('galat_google', $pesan);
    }
}
