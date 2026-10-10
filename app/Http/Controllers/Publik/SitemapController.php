<?php

namespace App\Http\Controllers\Publik;

use App\Enums\StatusPublikasi;
use App\Enums\Visibilitas;
use App\Http\Controllers\Controller;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Support\CacheRegulasi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * GET /sitemap.xml (F8.4, BR-01, BR-02): beranda, daftar dokumen, jenis & kategori yang punya dokumen, dan setiap
 * dokumen terbit & publik. Di-cache 1 jam (`sitemap`), dibersihkan oleh CacheRegulasi::lupakanPublik().
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap', CacheRegulasi::TTL_PUBLIK_DETIK, fn (): string => $this->susun());

        return response((string) $xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function susun(): string
    {
        /** @param Builder<Dokumen> $q */
        $terlihat = fn (Builder $q): Builder => $q->where('status_publikasi', StatusPublikasi::Terbit->value)
            ->where('visibilitas', Visibilitas::Publik->value);

        /** @var list<array{loc: string, lastmod: string|null}> $url */
        $url = [
            ['loc' => rtrim(route('beranda'), '/').'/', 'lastmod' => null],
            ['loc' => route('dokumen.index'), 'lastmod' => null],
        ];

        JenisDokumen::query()->whereHas('dokumen', $terlihat)->orderBy('urutan')->get(['slug'])
            ->each(function (JenisDokumen $jenis) use (&$url): void {
                $url[] = ['loc' => route('dokumen.jenis', $jenis->slug), 'lastmod' => null];
            });

        KategoriSistem::query()->whereHas('dokumen', $terlihat)->orderBy('urutan')->get(['id', 'slug'])
            ->each(function (KategoriSistem $kategori) use (&$url): void {
                $url[] = ['loc' => route('dokumen.kategori', $kategori->slug), 'lastmod' => null];
            });

        Dokumen::query()->terbit()->publik()->orderBy('id')->select(['id', 'slug', 'updated_at'])
            ->chunk(500, function ($kumpulan) use (&$url): void {
                foreach ($kumpulan as $dokumen) {
                    /** @var Dokumen $dokumen */
                    $url[] = [
                        'loc' => route('dokumen.show', ['dokumen' => $dokumen->id, 'slug' => $dokumen->slug]),
                        'lastmod' => $dokumen->updated_at?->toAtomString(),
                    ];
                }
            });

        $baris = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

        foreach ($url as $u) {
            $baris[] = '  <url><loc>'.e($u['loc']).'</loc>'.($u['lastmod'] !== null ? '<lastmod>'.$u['lastmod'].'</lastmod>' : '').'</url>';
        }

        $baris[] = '</urlset>';

        return implode("\n", $baris)."\n";
    }
}
