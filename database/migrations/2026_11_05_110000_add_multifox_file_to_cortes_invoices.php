<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soporte de Multifox de cada factura del corte: lo carga Paula al confirmar la carga en Multifox
 * y queda en la carpeta del corte (<contrato>/05 CORTES/CORTE N/), junto a la factura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cortes_invoices', function (Blueprint $table) {
            $table->string('multifox_file_name')->nullable()->after('multifox_confirmed_by');
            $table->string('multifox_file_path', 1000)->nullable()->after('multifox_file_name');
            $table->unsignedBigInteger('multifox_file_size')->nullable()->after('multifox_file_path');
            $table->string('multifox_file_mime')->nullable()->after('multifox_file_size');
        });
    }

    public function down(): void
    {
        Schema::table('cortes_invoices', function (Blueprint $table) {
            $table->dropColumn(['multifox_file_name', 'multifox_file_path', 'multifox_file_size', 'multifox_file_mime']);
        });
    }
};
