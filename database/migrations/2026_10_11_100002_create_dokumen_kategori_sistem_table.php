<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot dokumen ↔ kategori sistem (03-SKEMA §3.8).
     */
    public function up(): void
    {
        Schema::create('dokumen_kategori_sistem', function (Blueprint $table) {
            $table->foreignUuid('dokumen_id')->constrained('dokumen')->cascadeOnDelete();
            $table->foreignUuid('kategori_sistem_id')->index()->constrained('kategori_sistem')->restrictOnDelete();

            $table->primary(['dokumen_id', 'kategori_sistem_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_kategori_sistem');
    }
};
