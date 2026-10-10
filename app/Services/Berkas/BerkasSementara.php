<?php

namespace App\Services\Berkas;

/**
 * PDF yang singgah di storage/app/tmp/ekstraksi selama ekstraksi (STANDAR-TEKNIS §1a). Pemakai WAJIB memanggil
 * hapus() di blok finally; destruktor hanya jaring pengaman.
 */
final class BerkasSementara
{
    public function __construct(
        public readonly string $path,
        public readonly int $ukuran,
        public readonly string $hashSha256,
        public readonly string $urlAkhir,
    ) {}

    public function hapus(): void
    {
        if (is_file($this->path)) {
            @unlink($this->path);
        }
    }

    public function __destruct()
    {
        $this->hapus();
    }
}
