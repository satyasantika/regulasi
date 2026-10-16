<?php

namespace App\Services\Pencarian;

/**
 * Koreksi salah ketik dengan kamus istilah (02-ARSITEKTUR §5.1): token yang tidak ada di kamus diganti istilah
 * terdekat menurut levenshtein() ≤ 2 (≤ 1 untuk token ≤ 5 huruf); seri dipecah dengan frekuensi tertinggi.
 * Token bernomor/berangka tidak dikoreksi. Kamus kosong atau tidak ada yang berubah → null.
 */
class KoreksiIstilah
{
    public function __construct(private readonly KamusPencarian $kamus) {}

    public function koreksi(string $q): ?string
    {
        $kamus = $this->kamus->istilah();

        if ($kamus === []) {
            return null;
        }

        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($q), $cocok);
        $token = $cocok[0];
        $berubah = false;

        foreach ($token as $i => $kata) {
            if (isset($kamus[$kata]) || preg_match('/\d/u', $kata) === 1 || mb_strlen($kata) < 3) {
                continue;
            }

            $terdekat = $this->terdekat($kata, $kamus);

            if ($terdekat !== null) {
                $token[$i] = $terdekat;
                $berubah = true;
            }
        }

        return $berubah ? implode(' ', $token) : null;
    }

    /**
     * @param  array<string, int>  $kamus
     */
    private function terdekat(string $kata, array $kamus): ?string
    {
        $maks = mb_strlen($kata) <= 5 ? 1 : 2;
        $terbaik = null;
        $jarakTerbaik = $maks + 1;
        $frekuensiTerbaik = -1;

        foreach ($kamus as $istilah => $frekuensi) {
            $istilah = (string) $istilah;

            if (abs(strlen($istilah) - strlen($kata)) > $maks) {
                continue;
            }

            $jarak = levenshtein($kata, $istilah);

            if ($jarak < $jarakTerbaik || ($jarak === $jarakTerbaik && $frekuensi > $frekuensiTerbaik)) {
                $terbaik = $istilah;
                $jarakTerbaik = $jarak;
                $frekuensiTerbaik = $frekuensi;
            }
        }

        return $jarakTerbaik <= $maks ? $terbaik : null;
    }
}
