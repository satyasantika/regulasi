<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatusPublikasi;
use App\Enums\Visibilitas;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DokumenResource;
use App\Http\Responses\GalatJsonApi;
use App\Models\Dokumen;
use App\Models\KlienApi;
use App\Services\Pencarian\KriteriaPencarian;
use App\Services\Pencarian\PencarianDokumen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * API baca v1 dokumen (US-API-01, BR-01, BR-02, BR-18). Hanya dokumen terbit; dokumen internal hanya untuk token
 * berability dokumen:baca-internal. Daftar memakai layanan PencarianDokumen (MariaDB FULLTEXT + kamus istilah).
 */
class DokumenController extends Controller
{
    /** Filter yang didukung; alias sederhana ?kategori=…&status=… dipetakan ke filter[…]. */
    public const array FILTER = ['kategori', 'status', 'jenis', 'tahun', 'badan'];

    /** Nilai `sort` API → urutan KriteriaPencarian. */
    public const array SORT = [
        'tahun' => 'tahun_asc',
        '-tahun' => 'tahun_desc',
        'tanggal_penetapan' => 'penetapan_asc',
        '-dipublikasikan_pada' => 'terbaru',
    ];

    public function index(Request $request, PencarianDokumen $pencarian): AnonymousResourceCollection
    {
        $filter = $this->filter($request);
        $parameter = $this->validasi($request, $filter);
        $sertakanInternal = KlienApi::bolehBacaInternal($request);

        $q = is_scalar($request->query('q')) ? (string) $request->query('q') : null;
        $sort = $parameter['sort'] ?? null;
        $urut = $sort !== null ? self::SORT[$sort] : (filled($q) ? 'relevan' : self::SORT['-tahun']);

        $kriteria = KriteriaPencarian::dari([
            'q' => $q,
            'jenis' => $filter['jenis'] ?? [],
            'tahun' => $filter['tahun'] ?? [],
            'status' => $filter['status'] ?? [],
            'kategori' => $filter['kategori'] ?? [],
            'badan' => $filter['badan'] ?? [],
            'urut' => $urut,
            'halaman' => (int) ($parameter['page']['number'] ?? 1),
            'per_halaman' => (int) ($parameter['page']['size'] ?? 20),
        ], $sertakanInternal, KriteriaPencarian::PER_HALAMAN_MAKS_API);

        $hasil = $pencarian->cari($kriteria);
        $halaman = $hasil->dokumen;

        $dokumen = new Collection($halaman->items());
        $this->muatInclude($dokumen, $this->include($request), $sertakanInternal);

        $tautanHalaman = fn (int $nomor): string => $request->fullUrlWithQuery(['page' => ['number' => $nomor, 'size' => $halaman->perPage()]]);

        return DokumenResource::collection($dokumen)->additional([
            'meta' => [
                'total' => $halaman->total(),
                'page' => [
                    'number' => $halaman->currentPage(),
                    'size' => $halaman->perPage(),
                    'last' => $halaman->lastPage(),
                ],
                'faset' => $hasil->faset,
            ],
            'links' => array_filter([
                'self' => $tautanHalaman($halaman->currentPage()),
                'first' => $tautanHalaman(1),
                'prev' => $halaman->currentPage() > 1 ? $tautanHalaman($halaman->currentPage() - 1) : null,
                'next' => $halaman->hasMorePages() ? $tautanHalaman($halaman->currentPage() + 1) : null,
                'last' => $tautanHalaman($halaman->lastPage()),
            ]),
        ]);
    }

    /**
     * Detail dokumen dengan ETag lemah & Last-Modified (berdasarkan updated_at, include, dan hak internal);
     * If-None-Match/If-Modified-Since yang cocok → 304 tanpa isi.
     */
    public function show(Request $request, string $dokumen): JsonResponse
    {
        $this->validasi($request, []);
        $sertakanInternal = KlienApi::bolehBacaInternal($request);
        $include = $this->include($request);

        $model = Dokumen::query()
            ->whereKey($dokumen)
            ->terbit()
            ->when(! $sertakanInternal, fn (Builder $q) => $q->publik())
            ->with(['jenisDokumen', 'badanPenetap'])
            ->first() ?? throw new NotFoundHttpException('Dokumen tidak ditemukan.');

        $respons = new JsonResponse(null, 200, ['Content-Type' => GalatJsonApi::TIPE_KONTEN]);
        $respons->setEtag(hash('sha256', implode('|', [
            $model->id,
            $model->updated_at?->format('Y-m-d\TH:i:s.uP'),
            implode(',', $include),
            $sertakanInternal ? 'internal' : 'publik',
        ])), weak: true);
        $respons->setLastModified($model->updated_at);
        $respons->headers->addCacheControlDirective('private');
        $respons->headers->addCacheControlDirective('no-cache');

        if ($respons->isNotModified($request)) {
            return $respons;
        }

        $this->muatInclude(new Collection([$model]), $include, $sertakanInternal);

        $isi = (new DokumenResource($model))->response($request);
        $respons->setData($isi->getData());

        return $respons;
    }

    /**
     * filter[…] (kode, koma = OR) dengan alias tingkat atas; nilai string dipecah koma.
     *
     * @return array<string, list<string>>
     */
    private function filter(Request $request): array
    {
        $mentah = $request->query('filter');
        $mentah = is_array($mentah) ? $mentah : [];
        $filter = [];

        foreach (self::FILTER as $kunci) {
            $nilai = $mentah[$kunci] ?? $request->query($kunci);

            if ($nilai === null || $nilai === '' || $nilai === []) {
                continue;
            }

            $butir = is_array($nilai) ? $nilai : explode(',', is_scalar($nilai) ? (string) $nilai : '');
            $filter[$kunci] = array_values(array_filter(
                array_map(fn (mixed $b): string => trim(is_scalar($b) ? (string) $b : ''), $butir),
                fn (string $b): bool => $b !== '',
            ));
        }

        return $filter;
    }

    /**
     * @param  array<string, list<string>>  $filter
     * @return array{sort?: string, page?: array{number?: int|string, size?: int|string}}
     */
    private function validasi(Request $request, array $filter): array
    {
        /** @var array{sort?: string, page?: array{number?: int|string, size?: int|string}} $data */
        $data = Validator::make(
            [...$request->query(), 'filter' => $filter, 'include' => $this->include($request)],
            [
                'q' => ['nullable', 'string', 'max:200'],
                'sort' => ['nullable', 'string', Rule::in(array_keys(self::SORT))],
                'include' => ['array'],
                'include.*' => ['string', Rule::in(DokumenResource::INCLUDE)],
                'page' => ['nullable', 'array'],
                'page.number' => ['nullable', 'integer', 'min:1'],
                'page.size' => ['nullable', 'integer', 'min:1'],
                'filter.tahun.*' => ['integer', 'between:1900,2100'],
                'filter.status.*' => ['string', 'max:20'],
            ],
            [
                'sort.in' => 'sort harus salah satu dari: '.implode(', ', array_keys(self::SORT)).'.',
                'include.*.in' => 'include hanya mendukung: '.implode(', ', DokumenResource::INCLUDE).'.',
            ],
        )->validate();

        return $data;
    }

    /**
     * @return list<string>
     */
    private function include(Request $request): array
    {
        $include = $request->query('include');

        if (! is_string($include) || trim($include) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $include)))));
    }

    /**
     * Memuat relasi yang diminta dengan batasan BR-01/BR-02: relasi hanya ke dokumen terkait yang terbit (dan
     * publik bila token tidak berhak internal); tautan beserta hasil ekstraksi (jumlah halaman).
     *
     * @param  Collection<int, Dokumen>  $dokumen
     * @param  list<string>  $include
     */
    private function muatInclude(Collection $dokumen, array $include, bool $sertakanInternal): void
    {
        $muat = [];

        if (in_array('kategori', $include, true)) {
            $muat['kategori'] = fn ($q) => $q->orderBy('urutan')->orderBy('nama');
        }

        if (in_array('relasi', $include, true)) {
            $muat['relasi'] = fn (HasMany $q) => $q
                ->whereHas('dokumenTerkait', fn (Builder $d) => $d->where('status_publikasi', StatusPublikasi::Terbit->value)->when(! $sertakanInternal, fn (Builder $p) => $p->where('visibilitas', Visibilitas::Publik->value)))
                ->with('dokumenTerkait')
                ->orderBy('jenis_relasi')
                ->orderBy('created_at');
        }

        if (in_array('tautan', $include, true)) {
            $muat[] = 'tautan.ekstraksi';
        }

        if ($muat !== []) {
            $dokumen->load($muat);
        }
    }
}
