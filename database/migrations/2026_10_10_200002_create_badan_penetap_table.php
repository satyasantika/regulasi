<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master badan penetap / T.E.U. badan (03-SKEMA §3.3).
     */
    public function up(): void
    {
        Schema::create('badan_penetap', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama', 200);
            $table->string('teu', 255);
            $table->string('singkatan', 50)->nullable();
            $table->string('slug', 220)->unique();
            $table->string('tingkat', 30)->index();
            $table->foreignUuid('induk_id')->nullable()->constrained('badan_penetap')->nullOnDelete();
            $table->string('kode_eksternal', 50)->nullable();
            $table->unsignedSmallInteger('urutan')->default(999);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badan_penetap');
    }
};
