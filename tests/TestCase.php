<?php

namespace Tests;

use App\Services\Berkas\PemecahDns;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Uri;

abstract class TestCase extends BaseTestCase
{
    /**
     * Uji tidak pernah menyentuh jaringan: permintaan HTTP tanpa Http::fake ditolak, dan resolusi DNS
     * PengunduhAman mengembalikan kosong (host tak teresolusi) kecuali uji memasang dnsPalsu().
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->app->instance(PemecahDns::class, new PemecahDnsPalsu([]));
    }

    /**
     * Di produksi reverse proxy memotong awalan subpath sebelum ke aplikasi, sehingga
     * rute aplikasi tidak berawalan /regulasi. Permintaan uji meniru itu: jalur diambil
     * apa adanya, tidak melalui URL::forceRootUrl() milik APP_URL.
     */
    protected function prepareUrlForRequest($uri)
    {
        $uri = $uri instanceof Uri ? $uri->value() : $uri;

        if (str_starts_with($uri, 'http')) {
            return $uri;
        }

        return 'http://localhost/'.ltrim($uri, '/');
    }
}
