<?php

namespace App\Livewire\Publik;

use App\Enums\StatusBerlaku;
use App\Models\BadanPenetap;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Services\Pencarian\HasilPencarian;
use App\Services\Pencarian\KriteriaPencarian;
use App\Services\Pencarian\PencarianDokumen;
use App\Support\CacheRegulasi;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Daftar dokumen berfaset (US-CARI-01..03, 02-ARSITEKTUR §8) untuk tiga rute: /dokumen, /jenis/{slug} (jenis
 * terkunci), dan /kategori/{slug} (kategori terkunci). Semua filter tersinkron ke URL sehingga dapat dibagikan.
 * Mesin dari PencarianDokumen (MariaDB FULLTEXT + kamus istilah); internal hanya untuk pengguna login (BR-02).
 */
class DaftarDokumen extends Component
{
    use WithPagination;

    public const string HALAMAN = 'halaman';

    /** Properti filter yang mengembalikan paginasi ke halaman pertama saat berubah. */
    private const array PROPERTI_FILTER = ['q', 'jenis', 'tahun', 'status', 'kategori', 'badan', 'urut'];

    /** Awalan label chip filter aktif. */
    private const array AWALAN_CHIP = ['jenis' => 'Jenis', 'tahun' => 'Tahun', 'status' => 'Status', 'kategori' => 'Kategori', 'badan' => 'T.E.U.'];

    #[Url(except: '')]
    public string $q = '';

    /** @var list<string> */
    #[Url(except: [])]
    public array $jenis = [];

    /** @var list<string> */
    #[Url(except: [])]
    public array $tahun = [];

    /** @var list<string> */
    #[Url(except: [])]
    public array $status = [];

    /** @var list<string> */
    #[Url(except: [])]
    public array $kategori = [];

    /** @var list<string> */
    #[Url(except: [])]
    public array $badan = [];

    /** true = cari persis seperti diketik (tanpa koreksi salah ketik kamus istilah). */
    #[Url(except: false)]
    public bool $persis = false;

    #[Url(except: 'relevan')]
    public string $urut = 'relevan';

    /** Kode jenis bila halaman /jenis/{slug}. */
    #[Locked]
    public ?string $jenisTerkunci = null;

    /** Kode kategori bila halaman /kategori/{slug}. */
    #[Locked]
    public ?string $kategoriTerkunci = null;

    public function mount(?JenisDokumen $jenisDokumen = null, ?KategoriSistem $kategoriSistem = null): void
    {
        $this->jenisTerkunci = $jenisDokumen?->kode;
        $this->kategoriTerkunci = $kategoriSistem?->kode;
        $this->rapikanFilter();
    }

    public function updated(string $properti): void
    {
        if (in_array(explode('.', $properti)[0], self::PROPERTI_FILTER, true)) {
            if ($properti === 'q') {
                $this->persis = false;
            }

            $this->rapikanFilter();
            $this->resetPage(self::HALAMAN);
        }
    }

    public function hapusFilter(string $dimensi, string $nilai): void
    {
        if (! in_array($dimensi, ['jenis', 'tahun', 'status', 'kategori', 'badan'], true)) {
            return;
        }

        $this->{$dimensi} = array_values(array_filter($this->{$dimensi}, fn (string $v): bool => $v !== $nilai));
        $this->resetPage(self::HALAMAN);
    }

    public function hapusSemuaFilter(): void
    {
        $this->reset('q', 'persis', 'jenis', 'tahun', 'status', 'kategori', 'badan');
        $this->resetPage(self::HALAMAN);
    }

    public function render(PencarianDokumen $pencarian): View
    {
        $kriteria = $this->kriteria();
        $dibatasi = $this->melebihiBatas();

        $hasil = $dibatasi
            ? new HasilPencarian(new LengthAwarePaginator([], 0, $kriteria->perHalaman, 1, ['pageName' => self::HALAMAN]), [], 'database')
            : $pencarian->cari($kriteria);

        $jenisTerkunci = $this->jenisTerkunci !== null ? CacheRegulasi::master('jenis-dokumen')->firstWhere('kode', $this->jenisTerkunci) : null;
        $kategoriTerkunci = $this->kategoriTerkunci !== null ? CacheRegulasi::master('kategori-sistem')->firstWhere('kode', $this->kategoriTerkunci) : null;

        [$judul, $breadcrumb] = $this->judulDanBreadcrumb($jenisTerkunci, $kategoriTerkunci);

        return view('livewire.publik.daftar-dokumen', [
            'hasil' => $hasil,
            'dibatasi' => $dibatasi,
            'judul' => $judul,
            'breadcrumb' => $breadcrumb,
            'faset' => $this->susunFaset($hasil),
            'filterAktif' => $this->filterAktif(),
            'jumlahFilter' => count($this->jenis) + count($this->tahun) + count($this->status) + count($this->kategori) + count($this->badan),
            'urutan' => [
                'relevan' => 'Paling relevan',
                'terbaru' => 'Terbaru diterbitkan',
                'tahun_desc' => 'Tahun terbaru',
                'tahun_asc' => 'Tahun terlama',
                'dibuka' => 'Paling sering dibuka',
            ],
        ])->layout('components.layouts.publik', [
            'judul' => $judul,
            'deskripsi' => $jenisTerkunci?->deskripsi ?: $kategoriTerkunci?->deskripsi ?: 'Cari dan saring dokumen hukum dan dokumen pendukung FKIP Universitas Siliwangi.',
        ]);
    }

    private function kriteria(): KriteriaPencarian
    {
        return KriteriaPencarian::dari([
            'q' => $this->q,
            'jenis' => $this->jenisTerkunci !== null ? [$this->jenisTerkunci] : $this->jenis,
            'tahun' => $this->tahun,
            'status' => $this->status,
            'kategori' => $this->kategoriTerkunci !== null ? [$this->kategoriTerkunci] : $this->kategori,
            'badan' => $this->badan,
            'urut' => $this->urut,
            'halaman' => (int) $this->getPage(self::HALAMAN),
            'persis' => $this->persis,
        ], sertakanInternal: auth()->check());
    }

    /**
     * Rate limit `pencarian` 60/menit per IP (02-ARSITEKTUR §3.4); bila terlampaui mesin tidak dipanggil.
     */
    private function melebihiBatas(): bool
    {
        $batas = RateLimiter::limiter('pencarian');
        $limit = $batas !== null ? $batas(request()) : null;
        $limit = is_array($limit) ? ($limit[0] ?? null) : $limit;

        if ($limit === null) {
            return false;
        }

        $kunci = 'pencarian:'.$limit->key;

        if (RateLimiter::tooManyAttempts($kunci, $limit->maxAttempts)) {
            return true;
        }

        RateLimiter::hit($kunci, $limit->decaySeconds);

        return false;
    }

    /**
     * Buang nilai kosong/tak sah agar URL tetap bersih.
     */
    private function rapikanFilter(): void
    {
        $this->q = mb_substr($this->q, 0, 200);
        $this->urut = in_array($this->urut, KriteriaPencarian::URUTAN, true) ? $this->urut : 'relevan';

        foreach (['jenis', 'tahun', 'status', 'kategori', 'badan'] as $dimensi) {
            /** @var array<int|string, mixed> $mentah Nilai dari URL bisa berupa apa saja. */
            $mentah = (array) $this->{$dimensi};

            $this->{$dimensi} = array_values(array_unique(array_filter(
                array_map(fn (mixed $v): string => is_scalar($v) ? trim((string) $v) : '', $mentah),
                fn (string $v): bool => $v !== '' && mb_strlen($v) <= 100,
            )));
        }
    }

    /**
     * @return array{0: string, 1: list<array{0: string, 1: string|null}>}
     */
    private function judulDanBreadcrumb(?JenisDokumen $jenis, ?KategoriSistem $kategori): array
    {
        $beranda = ['Beranda', route('beranda')];

        if ($jenis !== null) {
            return [$jenis->nama, [$beranda, [$jenis->nama, null]]];
        }

        if ($kategori !== null) {
            return ['Kategori '.$kategori->nama, [$beranda, ['Kategori', null], [$kategori->nama, null]]];
        }

        return ['Dokumen', [$beranda, ['Dokumen', null]]];
    }

    /**
     * Faset untuk tampilan: dimensi → [legend, properti, opsi [nilai => [label, jumlah]]]. Nilai terpilih yang
     * jumlahnya nol tetap ditampilkan agar dapat dihapus; dimensi tanpa opsi disembunyikan, juga dimensi terkunci.
     *
     * @return array<string, array{legend: string, properti: string, opsi: array<string, array{label: string, jumlah: int}>}>
     */
    private function susunFaset(HasilPencarian $hasil): array
    {
        $definisi = [
            'jenis' => ['Jenis dokumen', 'jenis', $this->labelJenis()],
            'status_berlaku' => ['Status berlaku', 'status', $this->labelStatus()],
            'kategori' => ['Kategori sistem', 'kategori', $this->labelKategori()],
            'tahun' => ['Tahun', 'tahun', []],
            'badan_penetap' => ['T.E.U. badan', 'badan', $this->labelBadan()],
        ];

        $susun = [];

        foreach ($definisi as $dimensi => [$legend, $properti, $label]) {
            if (($properti === 'jenis' && $this->jenisTerkunci !== null) || ($properti === 'kategori' && $this->kategoriTerkunci !== null)) {
                continue;
            }

            $opsi = [];

            foreach ($hasil->faset[$dimensi] ?? [] as $nilai => $jumlah) {
                $opsi[(string) $nilai] = ['label' => $label[(string) $nilai] ?? (string) $nilai, 'jumlah' => (int) $jumlah];
            }

            foreach ($this->{$properti} as $terpilih) {
                $opsi[$terpilih] ??= ['label' => $label[$terpilih] ?? $terpilih, 'jumlah' => 0];
            }

            if ($opsi !== []) {
                $susun[$dimensi] = ['legend' => $legend, 'properti' => $properti, 'opsi' => $opsi];
            }
        }

        return $susun;
    }

    /**
     * @return list<array{properti: string, nilai: string, label: string}>
     */
    private function filterAktif(): array
    {
        $label = [
            'jenis' => $this->labelJenis(),
            'status' => $this->labelStatus(),
            'kategori' => $this->labelKategori(),
            'tahun' => [],
            'badan' => $this->labelBadan(),
        ];

        $aktif = [];

        foreach ($label as $properti => $peta) {
            foreach ($this->{$properti} as $nilai) {
                $aktif[] = ['properti' => $properti, 'nilai' => $nilai, 'label' => self::AWALAN_CHIP[$properti].': '.($peta[$nilai] ?? $nilai)];
            }
        }

        return $aktif;
    }

    /**
     * @return array<string, string>
     */
    private function labelJenis(): array
    {
        return CacheRegulasi::master('jenis-dokumen')->mapWithKeys(fn (JenisDokumen $j): array => [$j->kode => $j->nama])->all();
    }

    /**
     * @return array<string, string>
     */
    private function labelKategori(): array
    {
        return CacheRegulasi::master('kategori-sistem')->mapWithKeys(fn (KategoriSistem $k): array => [$k->kode => $k->nama])->all();
    }

    /**
     * @return array<string, string>
     */
    private function labelBadan(): array
    {
        return CacheRegulasi::master('badan-penetap')->mapWithKeys(fn (BadanPenetap $b): array => [$b->slug => $b->singkatan ?: $b->nama])->all();
    }

    /**
     * @return array<string, string>
     */
    private function labelStatus(): array
    {
        return collect(StatusBerlaku::cases())->mapWithKeys(fn (StatusBerlaku $s): array => [$s->value => $s->label()])->all();
    }
}
