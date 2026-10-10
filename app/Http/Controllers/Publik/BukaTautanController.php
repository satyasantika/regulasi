<?php

namespace App\Http\Controllers\Publik;

use App\Enums\StatusPublikasi;
use App\Enums\Visibilitas;
use App\Http\Controllers\Controller;
use App\Jobs\CatatBukaTautan;
use App\Models\TautanBerkas;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /d/{tautanBerkas}/buka (US-TAU-01, US-TAU-02, BR-17): Policy → log → redirect 302 ke URL TERSIMPAN.
 * Rute tidak menerima parameter tujuan apa pun (bebas open redirect).
 */
class BukaTautanController extends Controller
{
    public function __invoke(Request $request, TautanBerkas $tautanBerkas): RedirectResponse
    {
        /** @var User|null $pengguna */
        $pengguna = $request->user();

        if (Gate::forUser($pengguna)->denies('buka', $tautanBerkas)) {
            return $this->tolak($pengguna, $tautanBerkas);
        }

        $dokumen = $tautanBerkas->dokumen();
        abort_if($dokumen === null, 404);

        CatatBukaTautan::dispatch(
            $tautanBerkas->getKey(),
            $dokumen->getKey(),
            $pengguna?->getKey(),
            CatatBukaTautan::hashIp($request->ip()),
            $request->userAgent(),
        )->afterResponse();

        return $this->tanpaCache(redirect()->away($tautanBerkas->url, 302));
    }

    /**
     * Tamu ke dokumen terbit internal → masuk dulu (kembali ke URL ini); selain itu 404 agar keberadaan
     * dokumen tidak bocor.
     */
    private function tolak(?User $pengguna, TautanBerkas $tautan): RedirectResponse
    {
        $dokumen = $tautan->dokumen();

        if ($pengguna === null
            && $dokumen !== null
            && ! $dokumen->trashed()
            && $dokumen->status_publikasi === StatusPublikasi::Terbit
            && $dokumen->visibilitas === Visibilitas::Internal) {
            return $this->tanpaCache(redirect()->guest(route('filament.akun.auth.login')));
        }

        throw new NotFoundHttpException;
    }

    private function tanpaCache(RedirectResponse $respons): RedirectResponse
    {
        return $respons->withHeaders([
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
