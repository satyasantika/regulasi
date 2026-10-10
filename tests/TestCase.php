<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Uri;

abstract class TestCase extends BaseTestCase
{
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
