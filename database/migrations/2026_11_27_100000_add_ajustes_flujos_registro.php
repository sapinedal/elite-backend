<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ajustes a los flujos de registro:
 *
 *   Cortes: el Interventor puede rechazar y dejar que el corte continúe (Rechazado pero aprobado).
 *   Cuentas de cobro con frente (directas): Cargue ERP -> Factura (Facturas del mes) -> Liquidación ERP ->
 *     Aprobación de Contabilidad -> cuadro de pagos. Las de General / Indirectos siguen con Multifox.
 *   Anticipos: después de Gerencia el residente adjunta el contrato u orden de compra; luego Contabilidad (Multifox).
 *   Facturas del mes: el indirecto se carga sin valor y el analista contable lo registra al aprobarlo
 *     (pasa a cuentas por pagar).
 *
 * Permisos nuevos: cuentas_cobro.cargar_erp (además de quien radicó la cuenta) y facturacion.aprobar_indirecto
 * (rol contable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cortes', function (Blueprint $table) {
            $table->boolean('interventor_rejected')->default(false)->after('returned_stage');
        });

        Schema::table('cuentas_cobro', function (Blueprint $table) {
            // Paso documental de las cuentas directas: erp · factura · liquidacion_erp
            $table->string('current_step', 30)->nullable()->after('current_stage');
        });

        Schema::create('cuentas_cobro_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_cobro_id')->constrained('cuentas_cobro')->cascadeOnDelete();
            $table->string('step', 30);
            $table->string('number', 100);
            $table->decimal('amount', 15, 2)->nullable();
            $table->text('observation')->nullable();
            $table->string('file_name');
            $table->string('file_path', 1000);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_mime', 150)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['cuenta_cobro_id', 'step']);
        });

        Schema::table('anticipos', function (Blueprint $table) {
            // Contrato u orden de compra al que se asocia el anticipo (lo adjunta el residente después de Gerencia)
            $table->string('support_file_name')->nullable()->after('file_mime');
            $table->string('support_file_path', 1000)->nullable()->after('support_file_name');
            $table->unsignedBigInteger('support_file_size')->nullable()->after('support_file_path');
            $table->string('support_file_mime', 150)->nullable()->after('support_file_size');
            $table->foreignId('support_by')->nullable()->after('support_file_mime')->constrained('users')->nullOnDelete();
            $table->timestamp('support_at')->nullable()->after('support_by');
        });

        Schema::table('facturacion_invoices', function (Blueprint $table) {
            $table->foreignId('cuenta_cobro_id')->nullable()->after('anticipo_id')->constrained('cuentas_cobro')->nullOnDelete();
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'cuentas_cobro.cargar_erp', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'facturacion.aprobar_indirecto', 'guard_name' => 'web']);

        Role::where('name', 'admin')->where('guard_name', 'web')->get()
            ->each(fn (Role $role) => $role->givePermissionTo(['cuentas_cobro.cargar_erp', 'facturacion.aprobar_indirecto']));
        Role::where('name', 'contable')->where('guard_name', 'web')->get()
            ->each(fn (Role $role) => $role->givePermissionTo('facturacion.aprobar_indirecto'));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('facturacion_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cuenta_cobro_id');
        });
        Schema::table('anticipos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('support_by');
            $table->dropColumn(['support_file_name', 'support_file_path', 'support_file_size', 'support_file_mime', 'support_at']);
        });
        Schema::dropIfExists('cuentas_cobro_documents');
        Schema::table('cuentas_cobro', function (Blueprint $table) {
            $table->dropColumn('current_step');
        });
        Schema::table('cortes', function (Blueprint $table) {
            $table->dropColumn('interventor_rejected');
        });

        Permission::whereIn('name', ['cuentas_cobro.cargar_erp', 'facturacion.aprobar_indirecto'])->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
