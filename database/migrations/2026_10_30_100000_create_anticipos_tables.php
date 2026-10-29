<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Módulo Anticipos: el residente radica la cuenta de cobro del anticipo de un contrato, la aprueban el
 * Director de Obra y Gerencia, Contabilidad genera el anticipo en Multifox y carga el soporte, y el
 * anticipo pasa al cuadro de pagos (pagos_cuentas con source_type = anticipo).
 *
 * `anticipos_history` guarda la trazabilidad (radicado, aprobado, devuelto, reenviado, multifox).
 */
return new class extends Migration
{
    protected array $permissions = [
        'anticipos.ver',
        'anticipos.crear',
        'anticipos.ver_todos',
        'anticipos.aprobar_director',
        'anticipos.aprobar_gerencia',
        'anticipos.contabilizar', // Contabilidad: genera el anticipo en Multifox y carga el soporte
    ];

    public function up(): void
    {
        Schema::create('anticipos', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->nullable()->unique(); // ANT-0001, se asigna al crear
            $table->foreignId('contract_id')->constrained('contratacion_contracts');
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete(); // Del contrato
            $table->foreignId('tower_id')->nullable()->constrained('towers')->nullOnDelete(); // Del contrato
            $table->foreignId('contractor_id')->nullable()->constrained('ftra_contractors')->nullOnDelete();
            $table->string('document_number', 100); // N.º de la cuenta de cobro
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->string('status', 50);
            $table->string('current_stage', 50)->nullable(); // Aprobación pendiente (director_obra / gerencia)
            $table->string('returned_stage', 50)->nullable();
            $table->string('storage_path', 1000)->nullable(); // <carpeta del contrato>/08 ANTICIPOS/<código>

            // Cuenta de cobro que radica el residente
            $table->string('file_name');
            $table->string('file_path', 1000);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_mime')->nullable();

            // Anticipo generado en Multifox por Contabilidad
            $table->string('multifox_number', 100)->nullable();
            $table->string('multifox_file_name')->nullable();
            $table->string('multifox_file_path', 1000)->nullable();
            $table->unsignedBigInteger('multifox_file_size')->nullable();
            $table->string('multifox_file_mime')->nullable();
            $table->foreignId('multifox_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('multifox_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['created_by', 'created_at']);
            $table->index(['status', 'project_id']);
            $table->index('contract_id');
        });

        Schema::create('anticipos_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anticipo_id')->constrained('anticipos')->cascadeOnDelete();
            $table->string('stage', 50)->nullable();
            $table->string('action', 30);
            $table->text('comment')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_path', 1000)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['anticipo_id', 'id']);
        });

        // Cuadro de pagos: la cuenta que genera el anticipo
        Schema::table('pagos_cuentas', function (Blueprint $table) {
            $table->foreignId('anticipo_id')->nullable()->after('orden_id')->constrained('anticipos')->nullOnDelete();
        });

        // Permisos: admin y gerencia todo; técnica radica (residentes); contable genera el anticipo en Multifox.
        // La aprobación del Director de Obra se asigna desde Roles.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (['admin', 'gerencia'] as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($this->permissions);
        }
        Role::where('name', 'tecnica')->where('guard_name', 'web')->first()?->givePermissionTo(['anticipos.ver', 'anticipos.crear']);
        Role::where('name', 'contable')->where('guard_name', 'web')->first()?->givePermissionTo(['anticipos.ver', 'anticipos.contabilizar']);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('pagos_cuentas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anticipo_id');
        });
        Schema::dropIfExists('anticipos_history');
        Schema::dropIfExists('anticipos');

        Permission::whereIn('name', $this->permissions)->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
