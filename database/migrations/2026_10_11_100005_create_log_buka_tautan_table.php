<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log pembukaan tautan berkas (03-SKEMA §3.13, BR-17): ip_hash, bukan IP mentah; dipangkas > 24 bulan.
     * FK klien_api_id ditambahkan saat tabel klien_api dibuat (F9.1).
     */
    public function up(): void
    {
        Schema::create('log_buka_tautan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dokumen_id')->constrained('dokumen')->cascadeOnDelete();
            $table->foreignUuid('tautan_berkas_id')->constrained('tautan_berkas')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('klien_api_id')->nullable()->index();
            $table->string('saluran', 10)->default('web');
            $table->char('ip_hash', 64);
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('created_at')->useCurrent()->index();

            $table->index(['dokumen_id', 'created_at'], 'idx_log_buka_dokumen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_buka_tautan');
    }
};
