<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Módulo Contratación: contratos de obra por proyecto / frente, con sus adjuntos en S3
 * (tecnica/<carpeta del proyecto>/<FRENTE>/<TIPO>/<N. Contratista>) y la vigencia de sus pólizas.
 *
 * Usa tablas propias (contratacion_*) para no mezclarse con la tabla `contracts` del módulo Jurídica.
 */
return new class extends Migration
{
    protected array $permissions = [
        'contratacion.ver',
        'contratacion.crear',
        'contratacion.editar',
        'contratacion.eliminar',
    ];

    public function up(): void
    {
        // Carpeta de contratos del proyecto dentro de tecnica/ (ej. "Contratos San Miguel")
        if (!Schema::hasColumn('projects', 'contracts_folder')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->string('contracts_folder')->nullable()->after('description');
            });
        }
        DB::table('projects')->where('code', 'vis')->whereNull('contracts_folder')->update(['contracts_folder' => 'Contratos San Miguel']);

        Schema::create('contratacion_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('tower_id')->nullable()->constrained('towers')->nullOnDelete(); // Frente de obra
            $table->foreignId('contract_type_id')->nullable()->constrained('contract_types')->nullOnDelete();
            $table->string('contract_code', 100); // ID_Contrato
            $table->string('consecutive', 50);
            $table->string('contractor_name');
            $table->text('object');
            $table->date('signed_at')->nullable();
            $table->date('start_act_date')->nullable();
            $table->date('estimated_end_date')->nullable();
            $table->string('status', 50);
            $table->string('s3_path', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'contract_code']);
            $table->index(['project_id', 'tower_id']);
        });

        Schema::create('contratacion_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contratacion_contracts')->cascadeOnDelete();
            $table->string('policy_type', 100);
            $table->string('policy_number', 100)->nullable();
            $table->string('insurer')->nullable();
            $table->date('expires_at');
            // Se limpia cuando cambia la fecha de vigencia, para volver a notificar
            $table->timestamp('alert_sent_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
        });

        Schema::create('contratacion_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contratacion_contracts')->cascadeOnDelete();
            $table->string('folder', 50);        // clave de ContractDocumentCatalog::FOLDERS
            $table->string('document_type', 50)->nullable(); // cc, rut, camara_comercio (documentación legal)
            $table->string('name');
            $table->string('path', 1000);
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime_type')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['contract_id', 'folder']);
        });

        // Permisos del módulo: admin y gerencia tienen todo, técnica gestiona los contratos
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (['admin', 'gerencia', 'tecnica'] as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($this->permissions);
        }
        Role::where('name', 'juridica')->where('guard_name', 'web')->first()?->givePermissionTo('contratacion.ver');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('contratacion_documents');
        Schema::dropIfExists('contratacion_policies');
        Schema::dropIfExists('contratacion_contracts');

        if (Schema::hasColumn('projects', 'contracts_folder')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('contracts_folder');
            });
        }

        Permission::whereIn('name', $this->permissions)->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
