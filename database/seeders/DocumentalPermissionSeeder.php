<?php

namespace Database\Seeders;

use App\Http\Modules\Documental\Service\DocumentalService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Registra los permisos del módulo documental y los asigna a los roles existentes de forma aditiva
 * (no quita permisos que ya se hayan configurado desde la pantalla de Roles).
 */
class DocumentalPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (DocumentalService::PERMISSIONS as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        $roleDefaults = [
            'admin' => DocumentalService::PERMISSIONS,
            'gerencia' => DocumentalService::PERMISSIONS,
            'transversal' => ['documental.ver', 'documental.subir', 'documental.crear_carpeta', 'documental.renombrar'],
            'tecnica' => ['documental.ver', 'documental.subir', 'documental.crear_carpeta'],
            'juridica' => ['documental.ver', 'documental.subir', 'documental.crear_carpeta'],
            'comercial' => ['documental.ver', 'documental.subir', 'documental.crear_carpeta'],
            'mercadeo' => ['documental.ver', 'documental.subir', 'documental.crear_carpeta'],
            'contable' => ['documental.ver', 'documental.subir', 'documental.crear_carpeta'],
            'cartera_tramites' => ['documental.ver', 'documental.subir', 'documental.crear_carpeta'],
        ];

        foreach ($roleDefaults as $roleName => $permissions) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($permissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
