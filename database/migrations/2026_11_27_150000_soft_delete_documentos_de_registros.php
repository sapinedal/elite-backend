<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nada de los registros de contratación se borra definitivamente: las facturas de los cortes, los documentos de
 * órdenes y cuentas de cobro y las facturas del mes (con sus soportes) también quedan con soft delete, igual que los
 * cortes, órdenes, anticipos y cuentas de cobro. Se pueden recuperar en base de datos.
 *
 * Los índices únicos pasan a aplicar solo a los vigentes (deleted_at nulo), así se puede volver a cargar el mismo
 * número de factura o el mismo paso de una orden después de eliminarlo.
 */
return new class extends Migration
{
    protected array $tables = ['cortes_invoices', 'ordenes_documents', 'cuentas_cobro_documents', 'facturacion_invoices', 'facturacion_invoice_supports'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        Schema::table('cortes_invoices', function (Blueprint $table) {
            $table->dropUnique(['document_type', 'invoice_number']);
        });
        Schema::table('ordenes_documents', function (Blueprint $table) {
            $table->dropUnique(['orden_id', 'step']);
        });

        // Únicos solo entre los vigentes (PostgreSQL y SQLite admiten índices parciales)
        DB::statement('CREATE UNIQUE INDEX cortes_invoices_numero_vigente_unique ON cortes_invoices (document_type, invoice_number) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX ordenes_documents_paso_vigente_unique ON ordenes_documents (orden_id, step) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS cortes_invoices_numero_vigente_unique');
        DB::statement('DROP INDEX IF EXISTS ordenes_documents_paso_vigente_unique');

        // Los eliminados se borran para poder restaurar los índices únicos originales
        foreach ($this->tables as $name) {
            DB::table($name)->whereNotNull('deleted_at')->delete();
            Schema::table($name, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        Schema::table('cortes_invoices', function (Blueprint $table) {
            $table->unique(['document_type', 'invoice_number']);
        });
        Schema::table('ordenes_documents', function (Blueprint $table) {
            $table->unique(['orden_id', 'step']);
        });
    }
};
