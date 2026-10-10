<?php

namespace App\View\Components;

use App\Contracts\PenyimpananBerkas;
use App\Models\TautanBerkas as ModelTautanBerkas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Component;

/**
 * Tombol "Buka berkas" (lewat /d/{id}/buka) dan, untuk Google Drive/Docs bagi pengguna berhak, pratinjau
 * sematan yang dimuat langsung dari Google (tidak tercatat, BR-17). URL mentah tidak pernah dicetak;
 * otorisasi diputuskan di sini (Policy), bukan di Blade.
 */
class TautanBerkas extends Component
{
    public string $urlBuka;

    public ?string $urlPratinjau = null;

    public bool $berhak;

    public string $judul;

    public function __construct(public ModelTautanBerkas $tautan)
    {
        $penyimpanan = app(PenyimpananBerkas::class);

        $this->berhak = Gate::allows('buka', $tautan);
        $this->urlBuka = $penyimpanan->urlBuka($tautan);
        $this->urlPratinjau = $this->berhak ? $penyimpanan->urlPratinjau($tautan) : null;
        $this->judul = $tautan->label ?: $tautan->jenis->getLabel();
    }

    public function render(): View
    {
        return view('components.tautan-berkas');
    }
}
