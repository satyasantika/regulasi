<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sinonim pencarian dua arah (03-SKEMA §3.15b): satu baris per arah; pasangan (istilah, sinonim) unik.
     * Sinonim awal dimuat dari config/regulasi.php → `sinonim` oleh SinonimPencarianSeeder / regulasi:bangun-kamus.
     */
    public function up(): void
    {
        Schema::create('sinonim_pencarian', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('istilah', 100)->index();
            $table->string('sinonim', 255);
            $table->timestamps();

            $table->unique(['istilah', 'sinonim'], 'uq_sinonim_pasangan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sinonim_pencarian');
    }
};
