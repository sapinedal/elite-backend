<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Módulo Cuentas de cobro:
 *
 *   Radicada (con adjuntos si el tipo los exige, ej. SST o Gestión ambiental) -> Director de Obra -> Gerencia
 *   -> Paula decide si requiere Multifox: si no, pasa al cuadro de pagos; si sí, pasa cuando se carga en Multifox.
 *
 * Los tipos de cuenta de cobro se parametrizan (CRUD) y definen si exigen adjunto.
 */
return new class extends Migration
{
    protected array $permissions = [
        'cuentas_cobro.ver',
        'cuentas_cobro.crear',
        'cuentas_cobro.ver_todos',
        'cuentas_cobro.aprobar_director',
        'cuentas_cobro.aprobar_gerencia',
        'cuentas_cobro.parametrizar', // CRUD de tipos de cuenta de cobro
    ];

    public function up(): void
    {
        Schema::create('cuentas_cobro_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->text('description')->nullable();
            $table->boolean('requires_attachment')->default(false); // Exige adjuntos además de la cuenta de cobro
            $table->string('attachment_hint', 255)->nullable();     // Qué adjuntar, ej. "Informe SST y planilla de seguridad social"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cuentas_cobro', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->nullable()->unique(); // CC-0001, se asigna al crear
            $table->foreignId('tipo_id')->constrained('cuentas_cobro_tipos');
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('tower_id')->nullable()->constrained('towers')->nullOnDelete(); // Frente del cuadro de pagos
            $table->foreignId('contract_id')->nullable()->constrained('contratacion_contracts')->nullOnDelete();
            $table->foreignId('contractor_id')->nullable()->constrained('ftra_contractors')->nullOnDelete();
            $table->string('document_number', 100); // N.º de la cuenta de cobro
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->string('status', 50);
            $table->string('current_stage', 50)->nullable();
            $table->string('returned_stage', 50)->nullable();
            $table->string('storage_path', 1000)->nullable();

            // Cuenta de cobro
            $table->string('file_name');
            $table->string('file_path', 1000);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_mime')->nullable();

            // Decisión de Paula y cargue en Multifox (opcional)
            $table->boolean('requires_multifox')->nullable(); // null mientras Paula no decide
            $table->foreignId('multifox_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('multifox_decided_at')->nullable();
            $table->string('multifox_number', 100)->nullable();
            $table->string('multifox_file_name')->nullable();
            $table->string('multifox_file_path', 1000)->nullable();
            $table->unsignedBigInteger('multifox_file_size')->nullable();
            $table->string('multifox_file_mime')->nullable();
            $table->foreignId('multifox_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('multifox_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable(); // Pasó al cuadro de pagos
            $table->timestamps();
            $table->softDeletes();

            $table->index(['created_by', 'created_at']);
            $table->index(['status', 'project_id']);
        });

        // Adjuntos de la cuenta de cobro (obligatorios si el tipo los exige)
        Schema::create('cuentas_cobro_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_cobro_id')->constrained('cuentas_cobro')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path', 1000);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_mime')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('cuentas_cobro_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_cobro_id')->constrained('cuentas_cobro')->cascadeOnDelete();
            $table->string('stage', 50)->nullable();
            $table->string('action', 30);
            $table->text('comment')->nullable();
            $table->string('file_name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['cuenta_cobro_id', 'id']);
        });

        // Cuadro de pagos: la cuenta que genera la cuenta de cobro
        Schema::table('pagos_cuentas', function (Blueprint $table) {
            $table->foreignId('cuenta_cobro_id')->nullable()->after('anticipo_id')->constrained('cuentas_cobro')->nullOnDelete();
        });

        // Tipos iniciales: SST y Gestión ambiental exigen adjunto
        $now = now();
        DB::table('cuentas_cobro_tipos')->insert([
            ['name' => 'SST', 'description' => 'Seguridad y salud en el trabajo', 'requires_attachment' => true,
                'attachment_hint' => 'Informe SST y soportes de seguridad social', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Gestión ambiental', 'description' => null, 'requires_attachment' => true,
                'attachment_hint' => 'Informe de gestión ambiental y soportes', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Servicios generales', 'description' => null, 'requires_attachment' => false,
                'attachment_hint' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Permisos: admin y gerencia todo; técnica radica; contable consulta. Paula (Multifox) se identifica por su
        // cargo (cortes.multifox_positions) o el permiso cortes.multifox, igual que en Cortes.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (['admin', 'gerencia'] as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($this->permissions);
        }
        Role::where('name', 'tecnica')->where('guard_name', 'web')->first()?->givePermissionTo(['cuentas_cobro.ver', 'cuentas_cobro.crear']);
        Role::where('name', 'contable')->where('guard_name', 'web')->first()?->givePermissionTo(['cuentas_cobro.ver']);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('pagos_cuentas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cuenta_cobro_id');
        });
        Schema::dropIfExists('cuentas_cobro_history');
        Schema::dropIfExists('cuentas_cobro_adjuntos');
        Schema::dropIfExists('cuentas_cobro');
        Schema::dropIfExists('cuentas_cobro_tipos');

        Permission::whereIn('name', $this->permissions)->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
