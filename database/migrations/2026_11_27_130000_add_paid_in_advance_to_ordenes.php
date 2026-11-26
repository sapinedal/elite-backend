<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orden de compra parcial: al cargar la orden ERP se puede enviar de una vez a cuentas por pagar (con el valor y el
 * proveedor de la orden). La entrada de almacén y la aprobación de Contabilidad quedan después solo para legalizarla,
 * sin crear otra cuenta por pagar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes', function (Blueprint $table) {
            $table->boolean('paid_in_advance')->default(false)->after('current_step');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes', function (Blueprint $table) {
            $table->dropColumn('paid_in_advance');
        });
    }
};
