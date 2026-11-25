<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos de la administración de proveedores (Configuración → Proveedores). Admin y Gerencia los reciben
 * de una vez, igual que en PermissionSeeder; a los demás roles se asignan desde Roles.
 */
return new class extends Migration
{
    protected array $permissions = ['proveedores.ver', 'proveedores.editar', 'proveedores.eliminar'];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::whereIn('name', ['admin', 'gerencia'])->where('guard_name', 'web')->get()
            ->each(fn (Role $role) => $role->givePermissionTo($this->permissions));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permissions)->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
