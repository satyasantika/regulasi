<?php

namespace App\Http\Responses;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Galat API v1 berformat JSON:API (`errors[]` dengan status string dan title), Content-Type
 * application/vnd.api+json. Galat lain (500) dibiarkan ke penanganan bawaan.
 */
class GalatJsonApi
{
    public const string TIPE_KONTEN = 'application/vnd.api+json';

    /** Judul per kode status. */
    private const array JUDUL = [
        400 => 'Permintaan tidak valid',
        401 => 'Tidak terautentikasi',
        403 => 'Akses ditolak',
        404 => 'Tidak ditemukan',
        405 => 'Metode tidak diizinkan',
        422 => 'Parameter tidak valid',
        429 => 'Terlalu banyak permintaan',
    ];

    public static function dari(Throwable $galat): ?JsonResponse
    {
        return match (true) {
            $galat instanceof ValidationException => self::validasi($galat),
            $galat instanceof AuthenticationException => self::buat(401),
            $galat instanceof AuthorizationException => self::buat(403),
            $galat instanceof ModelNotFoundException => self::buat(404),
            $galat instanceof HttpExceptionInterface && isset(self::JUDUL[$galat->getStatusCode()]) => self::buat($galat->getStatusCode(), $galat->getHeaders()),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $header
     */
    public static function buat(int $status, array $header = [], ?string $judul = null): JsonResponse
    {
        return response()->json(
            ['errors' => [['status' => (string) $status, 'title' => $judul ?? self::JUDUL[$status] ?? 'Galat']]],
            $status,
            [...$header, 'Content-Type' => self::TIPE_KONTEN],
        );
    }

    private static function validasi(ValidationException $galat): JsonResponse
    {
        $errors = [];

        foreach ($galat->errors() as $parameter => $pesan) {
            foreach ($pesan as $detail) {
                $errors[] = [
                    'status' => '422',
                    'title' => self::JUDUL[422],
                    'detail' => $detail,
                    'source' => ['parameter' => self::namaParameter((string) $parameter)],
                ];
            }
        }

        return response()->json(['errors' => $errors], 422, ['Content-Type' => self::TIPE_KONTEN]);
    }

    /**
     * page.size → page[size]; include.0 → include.
     */
    private static function namaParameter(string $kunci): string
    {
        $bagian = explode('.', $kunci);

        if (in_array($bagian[0], ['include'], true)) {
            return $bagian[0];
        }

        $nama = array_shift($bagian);

        foreach ($bagian as $b) {
            if (ctype_digit($b)) {
                break;
            }
            $nama .= "[{$b}]";
        }

        return $nama;
    }
}
