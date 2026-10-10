<?php

namespace App\Services\Berkas;

/**
 * Resolusi DNS sendiri untuk PengunduhAman (02-ARSITEKTUR §4.3 langkah 2): semua alamat A/AAAA sebuah host.
 * Dipisah agar uji dapat menggantinya tanpa jaringan.
 */
class PemecahDns
{
    /**
     * @return list<string> Alamat IPv4/IPv6; kosong bila host tidak dapat diresolusi.
     */
    public function alamat(string $host): array
    {
        $rekaman = @dns_get_record($host, DNS_A | DNS_AAAA);
        $alamat = [];

        foreach (is_array($rekaman) ? $rekaman : [] as $baris) {
            $ip = $baris['ip'] ?? $baris['ipv6'] ?? null;

            if (is_string($ip) && $ip !== '') {
                $alamat[] = $ip;
            }
        }

        if ($alamat === []) {
            $alamat = gethostbynamel($host) ?: [];
        }

        return array_values(array_unique($alamat));
    }
}
