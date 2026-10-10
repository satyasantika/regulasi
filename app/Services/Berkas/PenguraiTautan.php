<?php

namespace App\Services\Berkas;

use App\Enums\PenyediaTautan;

/**
 * Mengenali penyedia dan mengekstrak drive_file_id dari URL tanpa permintaan jaringan (02-ARSITEKTUR §4.1).
 */
class PenguraiTautan
{
    private const string POLA_FOLDER = '#/drive/(?:u/\d+/)?folders/#';

    private const string POLA_ID = '[A-Za-z0-9_-]{10,100}';

    /** @var list<string> */
    private const array POLA_BERKAS = [
        '#/file/(?:u/\d+/)?d/('.self::POLA_ID.')#',
        '#/(?:document|spreadsheets|presentation|forms|drawings)/(?:u/\d+/)?d/('.self::POLA_ID.')#',
    ];

    /**
     * @return array{penyedia: PenyediaTautan, drive_file_id: string|null, folder: bool}
     */
    public function urai(string $url): array
    {
        $penyedia = $this->penyedia($url);
        $folder = $this->adalahFolder($url);

        return [
            'penyedia' => $penyedia,
            'drive_file_id' => $folder ? null : $this->driveFileId($url),
            'folder' => $folder,
        ];
    }

    public function penyedia(string $url): PenyediaTautan
    {
        $host = $this->host($url);

        return match (true) {
            $host === 'drive.google.com' => PenyediaTautan::GoogleDrive,
            $host === 'docs.google.com' => PenyediaTautan::GoogleDocs,
            $host === 'unsil.ac.id' || str_ends_with($host, '.unsil.ac.id') => PenyediaTautan::Unsil,
            $host === 'onedrive.live.com' || $host === '1drv.ms' || str_ends_with($host, '.sharepoint.com') => PenyediaTautan::Onedrive,
            $host === 'go.id' || str_ends_with($host, '.go.id') => PenyediaTautan::SumberResmi,
            default => PenyediaTautan::Lainnya,
        };
    }

    /**
     * ID berkas Google Drive/Docs dari pola /file/d/{id}/, /document/d/{id}/, open?id=, uc?id=.
     */
    public function driveFileId(string $url): ?string
    {
        if (! in_array($this->penyedia($url), [PenyediaTautan::GoogleDrive, PenyediaTautan::GoogleDocs], true)) {
            return null;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);

        foreach (self::POLA_BERKAS as $pola) {
            if (preg_match($pola, $path, $cocok) === 1) {
                return $cocok[1];
            }
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $kueri);
        $id = $kueri['id'] ?? null;

        if (in_array(rtrim($path, '/'), ['/open', '/uc', '/file/open'], true) && is_string($id) && preg_match('/^'.self::POLA_ID.'$/', $id) === 1) {
            return $id;
        }

        return null;
    }

    public function adalahFolder(string $url): bool
    {
        return $this->penyedia($url) === PenyediaTautan::GoogleDrive
            && preg_match(self::POLA_FOLDER, (string) parse_url($url, PHP_URL_PATH)) === 1;
    }

    public function urlPratinjau(?string $driveFileId): ?string
    {
        return $driveFileId === null ? null : "https://drive.google.com/file/d/{$driveFileId}/preview";
    }

    /**
     * URL unduhan langsung untuk ekstraksi (F7.1): Drive → uc?export=download, Google Docs → export PDF,
     * lainnya → URL apa adanya.
     */
    public function urlUnduh(string $url): string
    {
        $id = $this->driveFileId($url);

        if ($id === null) {
            return $url;
        }

        if ($this->penyedia($url) === PenyediaTautan::GoogleDocs && preg_match('#/(document|spreadsheets|presentation)/#', (string) parse_url($url, PHP_URL_PATH), $cocok) === 1) {
            return "https://docs.google.com/{$cocok[1]}/d/{$id}/export?format=pdf";
        }

        return "https://drive.google.com/uc?export=download&id={$id}";
    }

    private function host(string $url): string
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST));
    }
}
