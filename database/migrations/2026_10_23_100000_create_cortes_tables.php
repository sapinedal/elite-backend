<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Módulo Registro de Corte: el residente radica el corte de obra (archivo en S3) y este pasa por
 * tres revisiones en orden: Interventor -> Director de Obra -> Gerencia. Cualquier revisor puede
 * devolverlo al residente, que lo corrige y lo reenvía (vuelve a empezar en Interventor).
 *
 * `cortes_history` guarda la trazabilidad (radicado, aprobado, devuelto, reenviado) de cada corte.
 */
return new class extends Migration
{
    protected array $permissions = [
        'cortes.ver',
        'cortes.crear',
        'cortes.ver_todos',
        'cortes.aprobar_interventor',
        'cortes.aprobar_director',
        'cortes.aprobar_gerencia',
    ];

    public function up(): void
    {
        Schema::create('cortes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100); // Código del corte
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('tower_id')->nullable()->constrained('towers')->nullOnDelete();
            $table->foreignId('contractor_id')->nullable()->constrained('ftra_contractors')->nullOnDelete(); // Proveedor
            $table->foreignId('contract_id')->nullable()->constrained('contratacion_contracts')->nullOnDelete();
            $table->string('status', 50);
            $table->string('current_stage', 50)->nullable(); // Etapa que debe revisar; null si está devuelto o aprobado
            $table->string('returned_stage', 50)->nullable(); // Etapa que lo devolvió por última vez
            $table->string('file_name');
            $table->string('file_path', 1000);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_mime')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['created_by', 'created_at']);
            $table->index(['status', 'project_id']);
        });

        Schema::create('cortes_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corte_id')->constrained('cortes')->cascadeOnDelete();
            $table->string('stage', 50)->nullable(); // Etapa revisada (null en radicado / reenviado)
            $table->string('action', 30);
            $table->text('comment')->nullable();
            $table->string('file_name')->nullable(); // Archivo vigente al momento de la acción
            $table->string('file_path', 1000)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['corte_id', 'id']);
        });

        // Permisos: admin y gerencia todo; técnica radica cortes (residentes).
        // Las etapas de interventor y director se asignan desde Roles cuando se definan los usuarios.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (['admin', 'gerencia'] as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($this->permissions);
        }
        Role::where('name', 'tecnica')->where('guard_name', 'web')->first()?->givePermissionTo(['cortes.ver', 'cortes.crear']);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('cortes_history');
        Schema::dropIfExists('cortes');

        Permission::whereIn('name', $this->permissions)->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
