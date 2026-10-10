<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Menyapu sisa berkas sementara (ekstraksi, ekspor) di storage/app/tmp yang berumur > 24 jam
 * (02-ARSITEKTUR §4.3 langkah 7, §11). Berkas tersembunyi (.gitignore) dibiarkan.
 */
#[Signature('berkas:bersihkan-tmp {--jam=24 : Umur minimal berkas yang dihapus}')]
#[Description('Hapus berkas sementara di storage/app/tmp yang berumur lebih dari 24 jam')]
class BersihkanTmpCommand extends Command
{
    public function handle(): int
    {
        $akar = storage_path('app/tmp');
        $batas = now()->subHours(max(1, (int) $this->option('jam')))->getTimestamp();
        $dihapus = 0;

        if (File::isDirectory($akar)) {
            foreach (File::allFiles($akar) as $berkas) {
                if (str_starts_with($berkas->getFilename(), '.')) {
                    continue;
                }

                // Berkas bisa sudah dihapus proses lain (ekspor/ekstraksi selesai) antara pemindaian dan stat.
                try {
                    $diubah = $berkas->getMTime();
                } catch (RuntimeException) {
                    continue;
                }

                if ($diubah < $batas && File::delete($berkas->getPathname())) {
                    $dihapus++;
                }
            }
        }

        $this->info("{$dihapus} berkas sementara dihapus.");

        return self::SUCCESS;
    }
}
