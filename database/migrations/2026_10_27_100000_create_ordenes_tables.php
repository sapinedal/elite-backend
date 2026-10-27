<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Módulo Registro de Orden (dentro de Contratación). El residente registra la orden (descripción, presupuesto,
 * capítulo y actividad) y pasa por dos aprobaciones: Director de Obra -> Gerencia. Luego se cargan tres
 * documentos en orden, cada uno con su archivo en S3:
 *
 *   Orden de compra del ERP (Almacén) -> Factura del proveedor (Facturación) -> Entrada de almacén (Almacén)
 *
 * `ordenes_history` guarda la trazabilidad y `ordenes_documents` los documentos de cada paso.
 */
return new class extends Migration
{
    protected array $permissions = [
        'ordenes.ver',
        'ordenes.crear',
        'ordenes.ver_todos',
        'ordenes.aprobar_director',
        'ordenes.aprobar_gerencia',
        'ordenes.gestionar_almacen', // Orden de compra del ERP y entrada de almacén (además del cargo configurado)
        'ordenes.facturar',          // Factura del proveedor
    ];

    public function up(): void
    {
        Schema::create('ordenes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->nullable()->unique(); // ORD-0001, se asigna al crear
            $table->text('description');
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete(); // Presupuesto
            $table->foreignId('tower_id')->nullable()->constrained('towers')->nullOnDelete();
            $table->string('chapter', 255);  // Capítulo del presupuesto
            $table->string('activity', 255); // Actividad
            $table->string('status', 50);
            $table->string('current_stage', 50)->nullable();  // Aprobación pendiente (director_obra / gerencia)
            $table->string('current_step', 50)->nullable();   // Documento pendiente (orden_erp / factura / entrada)
            $table->string('returned_stage', 50)->nullable(); // Etapa que la devolvió por última vez
            $table->string('storage_path', 1000)->nullable(); // Carpeta S3 de la orden
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['created_by', 'created_at']);
            $table->index(['status', 'project_id']);
        });

        Schema::create('ordenes_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_id')->constrained('ordenes')->cascadeOnDelete();
            $table->string('step', 30); // orden_erp | factura | entrada
            $table->string('number', 100); // N.º de orden de compra / factura / entrada
            $table->text('observation')->nullable();
            $table->string('file_name');
            $table->string('file_path', 1000);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_mime')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['orden_id', 'step']);
        });

        Schema::create('ordenes_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_id')->constrained('ordenes')->cascadeOnDelete();
            $table->string('stage', 50)->nullable(); // Etapa o paso de la acción
            $table->string('action', 30);
            $table->text('comment')->nullable();
            $table->string('file_name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['orden_id', 'id']);
        });

        // Permisos: admin y gerencia todo; técnica registra órdenes; contable carga la factura del proveedor.
        // Almacén (orden de compra y entrada) se resuelve por el cargo configurado en config/ordenes.php.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (['admin', 'gerencia'] as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($this->permissions);
        }
        Role::where('name', 'tecnica')->where('guard_name', 'web')->first()?->givePermissionTo(['ordenes.ver', 'ordenes.crear']);
        Role::where('name', 'contable')->where('guard_name', 'web')->first()?->givePermissionTo(['ordenes.ver', 'ordenes.facturar']);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_history');
        Schema::dropIfExists('ordenes_documents');
        Schema::dropIfExists('ordenes');

        Permission::whereIn('name', $this->permissions)->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
