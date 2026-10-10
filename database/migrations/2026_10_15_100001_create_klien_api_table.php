<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Klien API (sistem FKIP lain) pemilik token Sanctum read-only (03-SKEMA §3.14, BR-18).
     */
    public function up(): void
    {
        Schema::create('klien_api', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama', 150);
            $table->foreignUuid('kategori_sistem_id')->nullable()->constrained('kategori_sistem')->nullOnDelete();
            $table->string('kontak_nama', 150)->nullable();
            $table->string('kontak_email', 255)->nullable();
            $table->boolean('aktif')->default(true);
            $table->dateTime('terakhir_dipakai_pada')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('klien_api');
    }
};
