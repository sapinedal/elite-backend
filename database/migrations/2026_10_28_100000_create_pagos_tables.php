<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cuentas por pagar: reemplaza el Excel semanal "Pagos autorizados". Cada cuenta nace de:
 *   - un corte, cuando se confirma que su factura quedó cargada en Multifox,
 *   - una orden, cuando Almacén carga la entrada (orden completada),
 *   - o un cargue manual de Contabilidad (nómina, aportes, fiduciaria, cuentas de cobro...).
 *
 * Paula (cargo de Multifox) prioriza qué se paga y cuándo; Contabilidad registra los pagos (admite abonos).
 * `pagos_movimientos` es la trazabilidad: quién hizo qué y cuándo.
 */
return new class extends Migration
{
    protected array $permissions = [
        'pagos.ver',
        'pagos.priorizar',  // Prioriza y programa los pagos (además del cargo configurado en config/pagos.php)
        'pagos.pagar',      // Registra pagos y abonos
        'pagos.registrar',  // Cargue manual de cuentas
        'cortes.multifox',  // Confirma la carga de facturas de cortes en Multifox (además del cargo)
    ];

    public function up(): void
    {
        // Valor de las facturas y confirmación de Multifox en cortes
        Schema::table('cortes_invoices', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->nullable()->after('related_document');
            $table->timestamp('multifox_confirmed_at')->nullable();
            $table->foreignId('multifox_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // Valor y proveedor de la factura del proveedor en órdenes
        Schema::table('ordenes_documents', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->nullable()->after('number');
            $table->foreignId('contractor_id')->nullable()->after('amount')->constrained('ftra_contractors')->nullOnDelete();
        });

        Schema::create('pagos_cuentas', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 20); // corte | orden | manual
            $table->unsignedBigInteger('source_id')->nullable(); // cortes_invoices.id / ordenes.id
            $table->foreignId('corte_id')->nullable()->constrained('cortes')->nullOnDelete();
            $table->foreignId('orden_id')->nullable()->constrained('ordenes')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('frente', 100); // Sección del cuadro: INDIRECTOS, TORRE 1, TORRE 2, URBANISMO...
            $table->foreignId('contractor_id')->nullable()->constrained('ftra_contractors')->nullOnDelete();
            $table->string('supplier_nit', 30)->nullable();
            $table->string('supplier_name');
            $table->string('document_number', 100); // FE 1602, C-C 49, Nóm#111...
            $table->text('concept')->nullable();     // Notas
            $table->decimal('amount', 15, 2);                    // Valor del documento
            $table->decimal('adjustments', 15, 2)->default(0);   // Notas crédito
            $table->decimal('paid_amount', 15, 2)->default(0);   // Pagos y abonos
            $table->string('status', 20); // pendiente | priorizada | parcial | pagada | anulada
            $table->string('priority', 10)->nullable(); // alta | media | baja
            $table->date('scheduled_for')->nullable();  // Fecha programada de pago
            $table->foreignId('prioritized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prioritized_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['source_type', 'source_id']);
            $table->index(['status', 'scheduled_for']);
            $table->index(['project_id', 'frente']);
        });

        Schema::create('pagos_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('pagos_cuentas')->cascadeOnDelete();
            $table->string('action', 30); // creada | priorizada | desprogramada | pago | nota_credito | reemplazo | anulada
            $table->decimal('amount', 15, 2)->nullable();
            $table->date('payment_date')->nullable();
            $table->string('reference', 150)->nullable(); // Comprobante / referencia bancaria
            $table->text('comment')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_path', 1000)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['cuenta_id', 'id']);
        });

        // Permisos: admin y gerencia todo; contable ve, paga y registra cuentas manuales.
        // Priorizar y confirmar Multifox se habilitan también por cargo (config/pagos.php y config/cortes.php).
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (['admin', 'gerencia'] as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($this->permissions);
        }
        Role::where('name', 'contable')->where('guard_name', 'web')->first()?->givePermissionTo(['pagos.ver', 'pagos.pagar', 'pagos.registrar']);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_movimientos');
        Schema::dropIfExists('pagos_cuentas');

        Schema::table('ordenes_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contractor_id');
            $table->dropColumn('amount');
        });
        Schema::table('cortes_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('multifox_confirmed_by');
            $table->dropColumn(['amount', 'multifox_confirmed_at']);
        });

        Permission::whereIn('name', $this->permissions)->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
