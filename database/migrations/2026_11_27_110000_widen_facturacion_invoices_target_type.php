<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * target_type se creó con 10 caracteres (corte | orden) y no cabe "cuenta_cobro" (12): al asociar la factura a una
 * cuenta de cobro directa fallaba con "value too long for type character varying(10)".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturacion_invoices', function (Blueprint $table) {
            $table->string('target_type', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('facturacion_invoices', function (Blueprint $table) {
            $table->string('target_type', 10)->nullable()->change();
        });
    }
};
