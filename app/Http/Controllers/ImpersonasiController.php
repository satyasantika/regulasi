<?php

namespace App\Http\Controllers;

use App\Actions\Pengguna\AkhiriImpersonasi;
use App\Filament\Admin\Resources\UserResource;
use Illuminate\Http\RedirectResponse;

/**
 * Mengakhiri impersonasi dari spanduk (POST + CSRF), kembali ke daftar pengguna panel admin.
 */
class ImpersonasiController extends Controller
{
    public function berhenti(AkhiriImpersonasi $aksi): RedirectResponse
    {
        $aksi->handle();

        return redirect()->to(UserResource::getUrl('index', panel: 'admin'));
    }
}
