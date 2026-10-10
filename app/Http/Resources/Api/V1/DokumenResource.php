<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Dokumen;
use Illuminate\Http\Request;

/**
 * Dokumen (type `dokumen`, id = UUIDv7) untuk API baca v1 (US-API-01, 02-ARSITEKTUR §8). Relationships kategori,
 * relasi, tautan hanya bila diminta lewat `include`; pemanggil wajib memuat relasi itu lebih dulu dengan
 * batasan BR-01/BR-02 (lihat Api\V1\DokumenController).
 *
 * @mixin Dokumen
 *
 * @property Dokumen $resource
 */
class DokumenResource extends SumberDayaJsonApi
{
    /** Nilai `include` yang didukung. */
    public const array INCLUDE = ['kategori', 'relasi', 'tautan'];

    public function toType(Request $request): string
    {
        return 'dokumen';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        $dokumen = $this->resource;

        return [
            'sebutan' => $dokumen->sebutan,
            'sebutan_singkat' => $dokumen->sebutan_singkat,
            'jenis' => ['kode' => $dokumen->jenisDokumen->kode, 'nama' => $dokumen->jenisDokumen->nama],
            'nomor' => $dokumen->nomor,
            'tahun' => $dokumen->tahun,
            'judul' => $dokumen->judul,
            'badan_penetap' => ['nama' => $dokumen->badanPenetap->nama, 'teu' => $dokumen->badanPenetap->teu],
            'tempat_penetapan' => $dokumen->tempat_penetapan,
            'tanggal_penetapan' => $dokumen->tanggal_penetapan?->format('Y-m-d'),
            'tanggal_pengundangan' => $dokumen->tanggal_pengundangan?->format('Y-m-d'),
            'tanggal_berlaku' => $dokumen->tanggal_berlaku?->format('Y-m-d'),
            'status_berlaku' => $dokumen->status_berlaku->value,
            'visibilitas' => $dokumen->visibilitas->value,
            'abstrak' => $dokumen->abstrak,
            'tanpa_berkas' => $dokumen->tanpa_berkas,
            'dipublikasikan_pada' => $dokumen->dipublikasikan_pada?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, class-string<SumberDayaJsonApi>>
     */
    public function toRelationships(Request $request): array
    {
        return [
            'kategori' => KategoriSistemResource::class,
            'relasi' => DokumenRelasiResource::class,
            'tautan' => TautanBerkasResource::class,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toLinks(Request $request): array
    {
        return [
            'self' => route('api.v1.dokumen.show', $this->resource->id),
            'html' => route('dokumen.show', ['dokumen' => $this->resource->id, 'slug' => $this->resource->slug]),
        ];
    }
}
