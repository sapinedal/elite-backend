<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adjuntos opcionales que el residente carga al registrar (o reenviar) una orden: cotizaciones, fichas técnicas,
 * fotos... Quedan en la carpeta de la orden (00 Adjuntos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_id')->constrained('ordenes')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path', 1000);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_mime')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_adjuntos');
    }
};
