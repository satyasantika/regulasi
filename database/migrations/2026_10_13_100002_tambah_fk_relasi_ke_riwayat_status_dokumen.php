<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FK riwayat_status_dokumen.dokumen_relasi_id → dokumen_relasi.id SET NULL (03-SKEMA §3.12), ditunda dari F5.1
     * sampai tabel dokumen_relasi ada.
     */
    public function up(): void
    {
        Schema::table('riwayat_status_dokumen', function (Blueprint $table) {
            $table->foreign('dokumen_relasi_id', 'fk_riwayat_dokumen_relasi')
                ->references('id')->on('dokumen_relasi')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('riwayat_status_dokumen', function (Blueprint $table) {
            $table->dropForeign('fk_riwayat_dokumen_relasi');
        });
    }
};
