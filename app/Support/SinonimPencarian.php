<?php

namespace App\Support;

/**
 * Sinonim pencarian dua arah dari config/regulasi.php → 'sinonim' (02-ARSITEKTUR §5.1):
 * ['uu' => ['undang-undang']] menjadi ['uu' => ['undang-undang'], 'undang-undang' => ['uu']].
 */
class SinonimPencarian
{
    /**
     * @param  array<mixed>  $sinonim
     * @return array<string, list<string>>
     */
    public static function duaArah(array $sinonim): array
    {
        $hasil = [];

        foreach ($sinonim as $kata => $padanan) {
            $kata = mb_strtolower(trim((string) $kata));

            foreach ((array) $padanan as $frasa) {
                $frasa = mb_strtolower(trim((string) $frasa));

                if ($kata === '' || $frasa === '' || $kata === $frasa) {
                    continue;
                }

                $hasil[$kata][] = $frasa;
                $hasil[$frasa][] = $kata;
            }
        }

        return array_map(fn (array $daftar): array => array_values(array_unique($daftar)), $hasil);
    }
}
