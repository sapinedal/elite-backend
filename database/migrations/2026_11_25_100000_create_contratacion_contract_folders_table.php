<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carpeta de S3 vinculada a un tipo de documento del contrato (una por tipo). Ej.: "01 DOCUMENTACIÓN LEGAL"
 * de un contrato puede apuntar a una carpeta existente del gestor documental en lugar de su subcarpeta propia.
 * Sin vínculo, el tipo usa la subcarpeta dentro de la carpeta del contrato (s3_path).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratacion_contract_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contratacion_contracts')->cascadeOnDelete();
            $table->string('folder', 30);
            $table->string('path', 1024);
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['contract_id', 'folder']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratacion_contract_folders');
    }
};
