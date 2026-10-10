<?php

namespace App\View\Components;

use App\Contracts\PenyimpananBerkas;
use App\Enums\PenyediaTautan;
use App\Enums\StatusEkstraksi;
use App\Models\TautanBerkas as ModelTautanBerkas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Component;

/**
 * Tombol "Buka berkas" (lewat /d/{id}/buka) beserta label, penyedia, dan jumlah halaman terekstraksi; untuk
 * Google Drive/Docs bagi pengguna berhak dapat menampilkan pratinjau sematan yang dimuat langsung dari Google
 * (tidak tercatat, BR-17). URL mentah tidak pernah dicetak; otorisasi diputuskan di sini (Policy), bukan di Blade.
 */
class TautanBerkas extends Component
{
    public string $urlBuka;

    public ?string $urlPratinjau = null;

    public bool $berhak;

    public string $judul;

    public string $penyedia;

    public ?int $jumlahHalaman = null;

    public function __construct(public ModelTautanBerkas $tautan, public bool $pratinjau = true)
    {
        $penyimpanan = app(PenyimpananBerkas::class);

        $this->berhak = Gate::allows('buka', $tautan);
        $this->urlBuka = $penyimpanan->urlBuka($tautan);
        $this->urlPratinjau = $this->berhak && $pratinjau ? $penyimpanan->urlPratinjau($tautan) : null;
        $this->judul = $tautan->label ?: $tautan->jenis->getLabel();
        $this->penyedia = self::namaPenyedia($tautan->penyedia);

        if ($tautan->relationLoaded('ekstraksi') && $tautan->ekstraksi?->status === StatusEkstraksi::Berhasil) {
            $this->jumlahHalaman = $tautan->ekstraksi->jumlah_halaman;
        }
    }

    /**
     * Nama penyedia untuk teks tombol, mis. "Buka berkas (Google Drive)".
     */
    public static function namaPenyedia(PenyediaTautan $penyedia): string
    {
        return match ($penyedia) {
            PenyediaTautan::SumberResmi => 'situs sumber',
            PenyediaTautan::Lainnya => 'situs eksternal',
            default => $penyedia->getLabel(),
        };
    }

    public function render(): View
    {
        return view('components.tautan-berkas');
    }
}
