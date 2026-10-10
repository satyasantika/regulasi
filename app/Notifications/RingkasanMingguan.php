<?php

namespace App\Notifications;

use Carbon\CarbonImmutable;

/**
 * Ringkasan mingguan (US-NOT-03) → admin-regulasi, hanya surel. Data dari App\Support\RingkasanMingguan::hitung().
 */
class RingkasanMingguan extends NotifikasiRegulasi
{
    /**
     * @param  array<string, mixed>  $ringkasan
     */
    public function __construct(public readonly array $ringkasan)
    {
        $this->siapkanAntrean();
    }

    public function kanal(): array
    {
        return ['mail'];
    }

    public function judul(): string
    {
        return 'Ringkasan mingguan Regulasi FKIP';
    }

    public function isi(): string
    {
        $sejak = CarbonImmutable::parse((string) $this->ringkasan['sejak'])->translatedFormat('d F Y');

        return "Ringkasan sejak {$sejak}:";
    }

    public function barisTambahan(): array
    {
        return self::baris($this->ringkasan);
    }

    public function url(): ?string
    {
        return route('filament.admin.pages.dashboard');
    }

    public function labelTombol(): string
    {
        return 'Buka panel admin';
    }

    /**
     * Baris teks ringkasan (juga dicetak perintah regulasi:ringkasan-mingguan).
     *
     * @param  array<string, mixed>  $r
     * @return list<string>
     */
    public static function baris(array $r): array
    {
        $tertua = $r['antrean']['tertua'] !== null
            ? ', tertua diajukan '.CarbonImmutable::parse((string) $r['antrean']['tertua'])->translatedFormat('d F Y')
            : '';

        $baris = [
            "Dokumen baru terbit (7 hari): {$r['terbit_baru']['jumlah']}".self::daftar($r['terbit_baru']['daftar']),
            "Antrean verifikasi: {$r['antrean']['jumlah']}{$tertua}",
            "Dokumen terbit tanpa abstrak: {$r['kelengkapan']['tanpa_abstrak']}; tanpa naskah utama: {$r['kelengkapan']['tanpa_naskah_utama']}; naskah tanpa teks: {$r['kelengkapan']['tanpa_teks']}",
            "Tautan tidak dapat diakses: {$r['tautan_tidak_dapat_diakses']}",
            "Ekstraksi gagal: {$r['ekstraksi']['gagal']}; tidak dapat diunduh: {$r['ekstraksi']['tidak_dapat_diunduh']}",
            "Isi berkas berubah (7 hari): {$r['isi_berubah']}",
            "Relasi tertunda yang efektif 30 hari ke depan: {$r['relasi_tertunda']['jumlah']}".self::daftar($r['relasi_tertunda']['daftar']),
        ];

        return $baris;
    }

    /**
     * @param  list<string>  $daftar
     */
    private static function daftar(array $daftar): string
    {
        return $daftar === [] ? '' : ' ('.implode('; ', $daftar).')';
    }
}
