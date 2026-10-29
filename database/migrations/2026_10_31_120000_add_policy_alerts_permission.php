<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permiso para recibir los avisos de pólizas próximas a vencer (40, 30 y 20 días), además de los correos de
 * contratacion.alert_emails. Se asigna desde Roles o a cada usuario (ej. María José Ospina).
 */
return new class extends Migration
{
    protected string $permission = 'contratacion.alertas_polizas';

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => $this->permission, 'guard_name' => 'web']);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', $this->permission)->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
