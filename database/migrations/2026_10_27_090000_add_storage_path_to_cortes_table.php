<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carpeta S3 de cada corte (<carpeta del contrato>/Cortes/<código>), donde quedan el archivo del corte y sus facturas.
 * Los cortes existentes se reubican con: php artisan cortes:reorganizar-archivos
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cortes', function (Blueprint $table) {
            $table->string('storage_path', 1000)->nullable()->after('returned_stage');
        });
    }

    public function down(): void
    {
        Schema::table('cortes', function (Blueprint $table) {
            $table->dropColumn('storage_path');
        });
    }
};
