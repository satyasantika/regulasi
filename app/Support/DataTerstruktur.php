<?php

namespace App\Support;

use App\Enums\KelompokJenisDokumen;
use App\Enums\StatusBerlaku;
use App\Models\Dokumen;

/**
 * JSON-LD schema.org untuk halaman detail dokumen (F8.4). Peraturan & keputusan memakai tipe `Legislation`
 * (https://schema.org/Legislation: legislationIdentifier, legislationDate, legislationPassedBy, legislationType,
 * legislationLegalForce); dokumen lain memakai `CreativeWork`.
 */
class DataTerstruktur
{
    /**
     * @return array<string, mixed>
     */
    public static function untuk(Dokumen $dokumen, string $url): array
    {
        $dokumen->loadMissing(['jenisDokumen', 'badanPenetap', 'tag']);

        $penerbit = ['@type' => 'Organization', 'name' => $dokumen->badanPenetap->nama];
        $dasar = [
            '@context' => 'https://schema.org',
            'name' => $dokumen->sebutan.' tentang '.$dokumen->judul,
            'alternateName' => $dokumen->sebutan_singkat,
            'inLanguage' => $dokumen->bahasa,
            'url' => $url,
        ];

        if (filled($dokumen->abstrak)) {
            $dasar['abstract'] = (string) $dokumen->abstrak;
        }

        if ($dokumen->tag->isNotEmpty()) {
            $dasar['keywords'] = $dokumen->tag->pluck('nama')->implode(', ');
        }

        $kelompok = $dokumen->jenisDokumen->kelompok;

        if (in_array($kelompok, [KelompokJenisDokumen::Peraturan, KelompokJenisDokumen::Keputusan], true)) {
            return array_filter([
                '@type' => 'Legislation',
                ...$dasar,
                'legislationIdentifier' => $dokumen->sebutan_singkat,
                'legislationType' => $dokumen->jenisDokumen->nama,
                'legislationDate' => $dokumen->tanggal_penetapan?->toDateString(),
                'legislationPassedBy' => $penerbit,
                'legislationLegalForce' => self::kekuatanHukum($dokumen->status_berlaku),
            ], fn (mixed $nilai): bool => $nilai !== null);
        }

        return array_filter([
            '@type' => 'CreativeWork',
            ...$dasar,
            'genre' => $dokumen->jenisDokumen->nama,
            'dateCreated' => $dokumen->tanggal_penetapan?->toDateString(),
            'datePublished' => (string) $dokumen->tahun,
            'publisher' => $penerbit,
        ], fn (mixed $nilai): bool => $nilai !== null);
    }

    /**
     * Enumerasi schema.org LegalForceStatus.
     */
    public static function kekuatanHukum(StatusBerlaku $status): string
    {
        return match ($status) {
            StatusBerlaku::Berlaku, StatusBerlaku::Diubah => 'https://schema.org/InForce',
            StatusBerlaku::Dicabut, StatusBerlaku::TidakBerlaku => 'https://schema.org/NotInForce',
        };
    }
}
