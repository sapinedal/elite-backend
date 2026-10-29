<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturas del mes: Contabilidad carga las facturas de proveedores en la carpeta del mes
 * (contabilidad/OCTUBRE 2026) y el residente responsable las asocia a su corte aprobado o a su orden
 * de compra pendiente de factura, adjuntando los soportes (aprobaciones, corte o entrada de Multifox).
 *
 * Al asociarla se registra en el corte (cortes_invoices) o en la orden (ordenes_documents) y sigue el
 * flujo normal de cada módulo. El archivo se queda en la carpeta del mes.
 *
 * Reemplaza el cargue directo de facturas dentro del corte. Usa el permiso existente cortes.facturar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturacion_invoices', function (Blueprint $table) {
            $table->id();
            $table->date('period'); // Primer día del mes al que pertenece (carpeta del mes)
            $table->foreignId('contractor_id')->nullable()->constrained('ftra_contractors')->nullOnDelete();
            $table->string('supplier_name'); // Proveedor como lo escribe Contabilidad (aunque no esté en el catálogo)
            $table->string('document_type', 30); // factura | cuenta_cobro | nota_credito | factura_reemplazo
            $table->string('invoice_number', 100);
            $table->string('related_document', 100)->nullable(); // Factura que afecta o reemplaza
            $table->decimal('amount', 15, 2)->nullable();
            $table->text('observation')->nullable();
            $table->string('file_name');
            $table->string('file_path', 1000);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_mime')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            // Asociación que hace el residente
            $table->string('status', 20); // pendiente | procesada
            $table->string('target_type', 10)->nullable(); // corte | orden
            $table->foreignId('corte_id')->nullable()->constrained('cortes')->nullOnDelete();
            $table->foreignId('orden_id')->nullable()->constrained('ordenes')->nullOnDelete();
            $table->foreignId('corte_invoice_id')->nullable()->constrained('cortes_invoices')->nullOnDelete();
            $table->foreignId('orden_document_id')->nullable()->constrained('ordenes_documents')->nullOnDelete();
            $table->text('processed_comment')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['period', 'status']);
            $table->index(['document_type', 'invoice_number']);
        });

        // Soportes que adjunta el residente al asociar (capturas de aprobaciones, corte / entrada de Multifox)
        Schema::create('facturacion_invoice_supports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('facturacion_invoices')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path', 1000);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_mime')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturacion_invoice_supports');
        Schema::dropIfExists('facturacion_invoices');
    }
};
