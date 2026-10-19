<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Horizon dicabut (Profil Ringan, STANDAR-TEKNIS §1b): hapus izin `horizon.akses` yang tersimpan di basis data
     * produksi beserta pemberiannya ke peran/pengguna. Idempoten; seeder tidak lagi membuatnya.
     */
    public function up(): void
    {
        $id = DB::table('permissions')->where('name', 'horizon.akses')->pluck('id')->all();

        if ($id !== []) {
            DB::table('role_has_permissions')->whereIn('permission_id', $id)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $id)->delete();
            DB::table('permissions')->whereIn('id', $id)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Tidak dipulihkan: Horizon sudah tidak ada.
    }
};
