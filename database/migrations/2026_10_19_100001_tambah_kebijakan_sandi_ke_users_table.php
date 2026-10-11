<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Wajib ganti sandi per akun (diatur super-admin; dicabut otomatis saat sandi diganti).
            $table->boolean('wajib_ganti_sandi')->default(false)->after('aktif');
            $table->timestamp('sandi_diubah_pada')->nullable()->after('wajib_ganti_sandi');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['wajib_ganti_sandi', 'sandi_diubah_pada']);
        });
    }
};
