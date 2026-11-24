<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturas del mes: nuevos tipos de documento (reembolso y cotización). La cotización no va a un corte ni a una
 * orden: al asociarla, el residente la radica como anticipo de un contrato (target_type = anticipo).
 *
 * Tipo indirecto: no se asocia a nada en obra; al cargarlo pasa de una vez a cuentas por pagar
 * (target_type = pagos, pagos_cuentas con source_type = indirecto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturacion_invoices', function (Blueprint $table) {
            $table->foreignId('anticipo_id')->nullable()->after('orden_document_id')->constrained('anticipos')->nullOnDelete();
            $table->foreignId('pago_cuenta_id')->nullable()->after('anticipo_id')->constrained('pagos_cuentas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('facturacion_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pago_cuenta_id');
            $table->dropConstrainedForeignId('anticipo_id');
        });
    }
};
