<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tag subjek (03-SKEMA §3.6).
     */
    public function up(): void
    {
        Schema::create('tag', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama', 100);
            $table->string('slug', 120)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag');
    }
};
