<?php

namespace Database\Seeders;

use App\Enums\Peran;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Peran dan permission sesuai PRD §3.3 dan 03-SKEMA §4.1. Idempoten.
 */
class PeranDanIzinSeeder extends Seeder
{
    /**
     * Semua permission (PRD §3.3).
     *
     * @var list<string>
     */
    public const IZIN = [
        'pengguna.kelola',
        'master.lihat',
        'master.kelola',
        'penugasan.kelola',
        'tag.buat',
        'dokumen.lihat-semua',
        'dokumen.lihat-kategori',
        'dokumen.buat',
        'dokumen.ubah',
        'dokumen.hapus',
        'dokumen.ajukan',
        'dokumen.verifikasi',
        'dokumen.arsipkan',
        'dokumen.lihat-internal',
        'tautan.kelola',
        'relasi.kelola',
        'status-berlaku.ubah',
        'laporan.lihat',
        'laporan.ekspor',
        'klien-api.kelola',
        'audit.lihat',
    ];

    /**
     * Pemetaan peran → permission (03-SKEMA §4.1). Super-admin lolos semua lewat Gate::before.
     *
     * @return array<string, list<string>>
     */
    public static function pemetaan(): array
    {
        return [
            Peran::SuperAdmin->value => [],
            Peran::AdminRegulasi->value => [
                'master.lihat', 'master.kelola', 'penugasan.kelola', 'tag.buat',
                'dokumen.lihat-semua', 'dokumen.buat', 'dokumen.ubah', 'dokumen.hapus', 'dokumen.ajukan',
                'dokumen.verifikasi', 'dokumen.arsipkan', 'dokumen.lihat-internal',
                'tautan.kelola', 'relasi.kelola', 'status-berlaku.ubah',
                'laporan.lihat', 'laporan.ekspor', 'audit.lihat',
            ],
            Peran::Kontributor->value => [
                'master.lihat', 'tag.buat', 'dokumen.lihat-kategori', 'dokumen.buat', 'dokumen.ubah',
                'dokumen.hapus', 'dokumen.ajukan', 'dokumen.lihat-internal', 'tautan.kelola', 'laporan.lihat',
            ],
            Peran::Pengguna->value => ['dokumen.lihat-internal'],
        ];
    }

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (self::IZIN as $izin) {
            Permission::findOrCreate($izin, 'web');
        }

        foreach (self::pemetaan() as $peran => $izin) {
            Role::findOrCreate($peran, 'web')->syncPermissions($izin);
        }

        $registrar->forgetCachedPermissions();
    }
}
