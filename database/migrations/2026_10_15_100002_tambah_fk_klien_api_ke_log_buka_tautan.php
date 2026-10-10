<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FK log_buka_tautan.klien_api_id → klien_api.id SET NULL (03-SKEMA §3.13), ditunda dari F4 sampai tabel
     * klien_api ada. Kolom sudah ada (UUID nullable berindeks).
     */
    public function up(): void
    {
        Schema::table('log_buka_tautan', function (Blueprint $table) {
            $table->foreign('klien_api_id', 'fk_log_buka_klien_api')
                ->references('id')->on('klien_api')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('log_buka_tautan', function (Blueprint $table) {
            $table->dropForeign('fk_log_buka_klien_api');
        });
    }
};
