<?php

namespace Tests;

use App\Services\Berkas\PemecahDns;

/**
 * Resolusi DNS tiruan untuk uji PengunduhAman: peta host → daftar IP, kunci "*" sebagai bawaan.
 */
class PemecahDnsPalsu extends PemecahDns
{
    /**
     * @param  array<string, list<string>>  $peta
     */
    public function __construct(private readonly array $peta) {}

    public function alamat(string $host): array
    {
        return $this->peta[$host] ?? $this->peta['*'] ?? [];
    }
}
