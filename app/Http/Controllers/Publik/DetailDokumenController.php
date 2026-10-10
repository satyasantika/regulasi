<?php

namespace App\Http\Controllers\Publik;

use App\Enums\JenisRelasi;
use App\Enums\JenisTautan;
use App\Enums\StatusPublikasi;
use App\Http\Controllers\Controller;
use App\Models\Dokumen;
use App\Models\DokumenRelasi;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Support\RingkasanStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * GET /dokumen/{dokumen}/{slug?} (US-PUB-02, BR-01, BR-02, BR-16, BR-19): halaman detail dokumen terbit.
 * Tidak terbit/terhapus → 404; internal untuk tamu → masuk dulu; slug kosong/lama → 301 ke slug terkini.
 * URL mentah tautan tidak pernah dicetak; berkas dibuka lewat /d/{id}/buka (BR-17).
 */
class DetailDokumenController extends Controller
{
    /** Urutan kelompok relasi di halaman. */
    public const array URUTAN_RELASI = [
        JenisRelasi::Mencabut,
        JenisRelasi::DicabutOleh,
        JenisRelasi::Mengubah,
        JenisRelasi::DiubahOleh,
        JenisRelasi::Melaksanakan,
        JenisRelasi::DilaksanakanOleh,
    ];

    public function show(Request $request, string $dokumen, ?string $slug = null): View|RedirectResponse
    {
        /** @var User|null $pengguna */
        $pengguna = $request->user();

        $model = Dokumen::query()
            ->with([
                'jenisDokumen',
                'badanPenetap',
                'kategoriSistem',
                'tag',
                'tautanBerkas.ekstraksi',
                'relasi.dokumenTerkait.jenisDokumen',
            ])
            ->find($dokumen);

        abort_if($model === null || $model->status_publikasi !== StatusPublikasi::Terbit, 404);

        if (Gate::forUser($pengguna)->denies('lihatDiPortal', $model)) {
            // Terbit tetapi internal dan pengunjung tamu (BR-02).
            return redirect()->guest(route('filament.akun.auth.login'));
        }

        if ($slug !== $model->slug) {
            return redirect()->route('dokumen.show', ['dokumen' => $model->id, 'slug' => $model->slug], 301);
        }

        // Pasang pemilik agar Policy tautan tidak memuat ulang dokumen (N+1).
        $model->tautanBerkas->each(fn (TautanBerkas $t) => $t->setRelation('pemilik', $model));

        $relasi = $model->relasi
            ->sortBy(fn (DokumenRelasi $r): string => ($r->efektif_pada?->format('Y-m-d') ?? '9999').$r->created_at?->format('YmdHis'))
            ->values();

        return view('publik.dokumen.show', [
            'dokumen' => $model,
            'kalimatStatus' => RingkasanStatus::kalimat($model, $relasi),
            'tertunda' => RingkasanStatus::tertunda($model, $relasi),
            'kelompokRelasi' => $this->kelompokRelasi($relasi, $pengguna),
            'naskahUtama' => $model->tautanBerkas->first(fn (TautanBerkas $t): bool => $t->jenis === JenisTautan::NaskahUtama),
            'halamanSumber' => $model->tautanBerkas->first(fn (TautanBerkas $t): bool => $t->jenis === JenisTautan::HalamanSumber),
        ]);
    }

    /**
     * Relasi dikelompokkan per jenis. Dokumen terkait yang belum terbit tidak ditampilkan (BR-01); dokumen
     * internal ditampilkan tanpa tautan bagi tamu (BR-02).
     *
     * @param  Collection<int, DokumenRelasi>  $relasi
     * @return list<array{jenis: JenisRelasi, butir: list<array{relasi: DokumenRelasi, dokumen: Dokumen, bolehDibuka: bool}>}>
     */
    private function kelompokRelasi($relasi, ?User $pengguna): array
    {
        $kelompok = [];

        foreach (self::URUTAN_RELASI as $jenis) {
            $butir = $relasi
                ->filter(fn (DokumenRelasi $r): bool => $r->jenis_relasi === $jenis
                    && $r->dokumenTerkait->status_publikasi === StatusPublikasi::Terbit
                    && ! $r->dokumenTerkait->trashed())
                ->map(fn (DokumenRelasi $r): array => [
                    'relasi' => $r,
                    'dokumen' => $r->dokumenTerkait,
                    'bolehDibuka' => Gate::forUser($pengguna)->allows('lihatDiPortal', $r->dokumenTerkait),
                ])
                ->values()
                ->all();

            if ($butir !== []) {
                $kelompok[] = ['jenis' => $jenis, 'butir' => $butir];
            }
        }

        return $kelompok;
    }
}
