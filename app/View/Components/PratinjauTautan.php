<?php

namespace App\View\Components;

use App\Contracts\PenyimpananBerkas;
use App\Enums\PenyediaTautan;
use App\Models\TautanBerkas as ModelTautanBerkas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Component;

/**
 * Pratinjau naskah tanpa PDF.js dan tanpa berkas lokal (F8.3): Google Drive/Docs → iframe /preview yang baru
 * dimuat setelah tombol ditekan (hemat data); sumber resmi pemerintah → tombol "Buka di situs sumber". URL
 * pratinjau hanya dicetak bagi yang berhak membuka tautan (Policy `buka`, BR-02/BR-17).
 */
class PratinjauTautan extends Component
{
    public bool $berhak = false;

    public ?string $urlPratinjau = null;

    public ?string $urlBuka = null;

    public bool $sumberResmi = false;

    public string $penyedia = '';

    public function __construct(public ?ModelTautanBerkas $tautan = null)
    {
        if ($tautan === null) {
            return;
        }

        $penyimpanan = app(PenyimpananBerkas::class);

        $this->berhak = Gate::allows('buka', $tautan);
        $this->urlBuka = $penyimpanan->urlBuka($tautan);
        $this->urlPratinjau = $this->berhak ? $penyimpanan->urlPratinjau($tautan) : null;
        $this->sumberResmi = $tautan->penyedia === PenyediaTautan::SumberResmi;
        $this->penyedia = TautanBerkas::namaPenyedia($tautan->penyedia);
    }

    public function shouldRender(): bool
    {
        return $this->tautan !== null && ($this->urlPratinjau !== null || $this->sumberResmi);
    }

    public function render(): View
    {
        return view('components.pratinjau-tautan');
    }
}
