<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Informes semanales de interventoría ya leídos desde sus PDF (uno por número de semana).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interventoria_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('numero')->unique();
            $table->date('fecha')->nullable();
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedBigInteger('file_last_modified')->nullable();
            $table->json('data');
            $table->unsignedSmallInteger('parser_version')->default(1);
            $table->timestamp('imported_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interventoria_reports');
    }
};
