<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat status dokumen append-only (03-SKEMA §3.12). FK dokumen_relasi_id ditambahkan di F6.1
     * (migrasi baru) setelah tabel dokumen_relasi ada.
     */
    public function up(): void
    {
        Schema::create('riwayat_status_dokumen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dokumen_id')->constrained('dokumen')->cascadeOnDelete();
            $table->string('jenis', 20);
            $table->string('dari', 30)->nullable();
            $table->string('ke', 30);
            $table->text('catatan')->nullable();
            $table->uuid('dokumen_relasi_id')->nullable();
            $table->foreignUuid('oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['dokumen_id', 'created_at'], 'idx_riwayat_dokumen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_status_dokumen');
    }
};
