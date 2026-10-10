<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom tambahan users (03-SKEMA §3.1).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nip', 30)->nullable()->index()->after('password');
            // Tanpa FK: master prodi milik sistem pusat (STANDAR-TEKNIS §4a butir 7).
            $table->uuid('prodi_id')->nullable()->index()->after('nip');
            $table->string('kode_eksternal', 50)->nullable()->index()->after('prodi_id');
            $table->boolean('aktif')->default(true)->after('kode_eksternal');
            $table->dateTime('terakhir_masuk_pada')->nullable()->after('aktif');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['nip']);
            $table->dropIndex(['prodi_id']);
            $table->dropIndex(['kode_eksternal']);
            $table->dropColumn(['nip', 'prodi_id', 'kode_eksternal', 'aktif', 'terakhir_masuk_pada']);
        });
    }
};
