<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Facturas de los cortes aprobados. Un corte puede tener varias: la factura inicial y luego notas crédito
 * o facturas de reemplazo que afectan a una factura anterior (related_document).
 * Al cargar una factura se notifica al cargo que la registra en Multifox (ERP externo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cortes_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corte_id')->constrained('cortes')->cascadeOnDelete();
            $table->string('invoice_number', 100);
            $table->string('document_type', 30); // factura_inicial | nota_credito | factura_reemplazo
            $table->string('related_document', 100)->nullable(); // Factura que afecta o reemplaza
            $table->text('observation')->nullable(); // Motivo / observación del cambio
            $table->string('file_name');
            $table->string('file_path', 1000);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_mime')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['corte_id', 'id']);
            $table->unique(['document_type', 'invoice_number']);
        });

        // Cargar facturas: admin y gerencia, y el rol contable (que además necesita ver los cortes)
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'cortes.facturar', 'guard_name' => 'web']);
        foreach (['admin', 'gerencia'] as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo('cortes.facturar');
        }
        Role::where('name', 'contable')->where('guard_name', 'web')->first()?->givePermissionTo(['cortes.ver', 'cortes.facturar']);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('cortes_invoices');

        Permission::where('name', 'cortes.facturar')->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
