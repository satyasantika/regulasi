<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relasi antardokumen simetris (03-SKEMA §3.11, BR-07): dua baris saling menunjuk lewat pasangan_id.
     */
    public function up(): void
    {
        Schema::create('dokumen_relasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dokumen_id')->constrained('dokumen')->cascadeOnDelete();
            $table->foreignUuid('dokumen_terkait_id')->index()->constrained('dokumen')->cascadeOnDelete();
            $table->string('jenis_relasi', 30);
            $table->uuid('pasangan_id')->nullable()->unique();
            $table->boolean('efektif')->default(false);
            $table->date('efektif_pada')->nullable();
            $table->string('keterangan', 500)->nullable();
            $table->foreignUuid('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['dokumen_id', 'dokumen_terkait_id', 'jenis_relasi'], 'uk_relasi');
            $table->index(['efektif', 'efektif_pada'], 'idx_relasi_efektif');
        });

        Schema::table('dokumen_relasi', function (Blueprint $table) {
            $table->foreign('pasangan_id')->references('id')->on('dokumen_relasi')->nullOnDelete();
        });

        // BR-10: relasi tidak boleh ke dirinya sendiri (juga divalidasi di TambahRelasiDokumen).
        DB::statement('ALTER TABLE dokumen_relasi ADD CONSTRAINT ck_relasi_bukan_diri CHECK (dokumen_id <> dokumen_terkait_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_relasi');
    }
};
