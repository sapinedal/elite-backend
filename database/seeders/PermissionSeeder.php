<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use App\Http\Modules\Users\Models\User;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar la caché de permisos de Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('Configurando Permisos y Roles en el sistema...');

        // 1. Definición completa de permisos agrupados por módulo
        $permissions = [
            // Control de Obra / Interventoría
            'obra.ver',
            'obra.crear',
            'obra.editar',
            'obra.eliminar',

            // Bitácora de Tareas
            'bitacora.ver',
            'bitacora.crear',
            'bitacora.editar',
            'bitacora.eliminar',

            // FTRA (Fichas Técnicas y Calidad)
            'ftra.ver',
            'ftra.crear',
            'ftra.editar',
            'ftra.eliminar',
            'ftra.revisar',
            'ftra.aprobar',
            'ftra.parametrizar',

            // Área Jurídica
            'juridica.ver',
            'juridica.crear',
            'juridica.editar',
            'juridica.eliminar',

            // Gestión Documental (Explorador S3)
            'documental.ver',

            // KPIs y Evaluaciones
            'kpi.ver',
            'kpi.evaluar',
            'kpi.historial',
            'kpi.parametrizar',

            // Configuración y Administración General
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.eliminar',

            'proyectos.ver',
            'proyectos.crear',
            'proyectos.editar',
            'proyectos.eliminar',

            'contratos.ver',
            'contratos.crear',
            'contratos.editar',
            'contratos.eliminar',

            'configuracion.ver',
            'configuracion.editar',

            'roles.ver',
            'roles.editar',

            'permisos.ver',
        ];

        // Crear permisos en BD si no existen
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        $this->command->info('Permisos registrados en el sistema: ' . count($permissions));

        // 2. Definición de los Roles del Sistema
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $gerenciaRole = Role::firstOrCreate(['name' => 'gerencia', 'guard_name' => 'web']);
        $tecnicaRole = Role::firstOrCreate(['name' => 'tecnica', 'guard_name' => 'web']);
        $juridicaRole = Role::firstOrCreate(['name' => 'juridica', 'guard_name' => 'web']);
        $transversalRole = Role::firstOrCreate(['name' => 'transversal', 'guard_name' => 'web']);
        $comercialRole = Role::firstOrCreate(['name' => 'comercial', 'guard_name' => 'web']);
        $mercadeoRole = Role::firstOrCreate(['name' => 'mercadeo', 'guard_name' => 'web']);
        $contableRole = Role::firstOrCreate(['name' => 'contable', 'guard_name' => 'web']);
        $carteraTramitesRole = Role::firstOrCreate(['name' => 'cartera_tramites', 'guard_name' => 'web']);

        // 3. Asignación de Permisos a Roles

        // Tareas para todos los roles
        $tareaPermissions = [
            'bitacora.ver',
            'bitacora.crear',
            'bitacora.editar',
            'bitacora.eliminar',
        ];

        // ADMIN: Acceso total a todo el sistema
        $adminRole->syncPermissions(Permission::all());

        // GERENCIA: Acceso total a todo el sistema
        $gerenciaRole->syncPermissions(Permission::all());

        // TÉCNICA: Tareas + Control de Obra / Interventoría + FTRA + Ver Proyectos
        $tecnicaRole->syncPermissions(array_merge($tareaPermissions, [
            'obra.ver',
            'obra.crear',
            'obra.editar',
            'obra.eliminar',
            'ftra.ver',
            'ftra.crear',
            'ftra.editar',
            'ftra.eliminar',
            'ftra.revisar',
            'ftra.aprobar',
            'ftra.parametrizar',
            'proyectos.ver',
        ]));

        // JURÍDICA: Tareas + Módulo Jurídica + Ver Contratos
        $juridicaRole->syncPermissions(array_merge($tareaPermissions, [
            'juridica.ver',
            'juridica.crear',
            'juridica.editar',
            'juridica.eliminar',
            'contratos.ver',
        ]));

        // TRANSVERSAL: Tareas + Gestión Documental (Explorador S3)
        $transversalRole->syncPermissions(array_merge($tareaPermissions, [
            'documental.ver',
        ]));

        // COMERCIAL: Tareas
        $comercialRole->syncPermissions($tareaPermissions);

        // MERCADEO: Tareas
        $mercadeoRole->syncPermissions($tareaPermissions);

        // CONTABLE: Tareas
        $contableRole->syncPermissions($tareaPermissions);

        // CARTERA Y TRÁMITES: Tareas
        $carteraTramitesRole->syncPermissions($tareaPermissions);

        $this->command->info('Roles sincronizados con sus permisos correspondientes.');

        // 4. Asignación de nuevos roles a los usuarios existentes según su área y cargo
        $users = User::with(['position.area'])->get();
        foreach ($users as $user) {
            $posName = mb_strtolower(optional($user->position)->name ?? '', 'UTF-8');
            $areaName = mb_strtolower(optional($user->position?->area)->name ?? '', 'UTF-8');
            $email = mb_strtolower($user->email ?? '', 'UTF-8');
            $userName = mb_strtolower($user->name ?? '', 'UTF-8');

            // Usuarios Administrativos / Tecnología con acceso de superadministrador
            if (
                $email === 'admin@elite.com' ||
                $email === 'samuel.pineda@elite.com' ||
                str_contains($userName, 'samuel pineda') ||
                str_contains($userName, 'control y gesti') ||
                str_contains($userName, 'control y gestion') ||
                str_contains($posName, 'desarrollador') ||
                str_contains($posName, 'tecnología') ||
                str_contains($posName, 'tecnologia')
            ) {
                $user->syncRoles(['admin']);
            } elseif (
                str_contains($userName, 'laura gomez') ||
                str_contains($userName, 'santiago barón') ||
                ($areaName === 'administrativo' && str_contains($posName, 'gerente general'))
            ) {
                $user->syncRoles(['admin', 'gerencia']);
            } elseif (
                str_contains($userName, 'julian restrepo') ||
                str_contains($areaName, 'gerencia') ||
                $posName === 'gerente'
            ) {
                $user->syncRoles(['gerencia']);
            } elseif (
                str_contains($areaName, 'técnica') ||
                str_contains($areaName, 'tecnica') ||
                str_contains($areaName, 'operaciones')
            ) {
                $user->syncRoles(['tecnica']);
            } elseif (
                str_contains($areaName, 'jurídica') ||
                str_contains($areaName, 'juridica')
            ) {
                $user->syncRoles(['juridica']);
            } elseif (
                str_contains($areaName, 'comercial')
            ) {
                $user->syncRoles(['comercial']);
            } elseif (
                str_contains($areaName, 'contab')
            ) {
                $user->syncRoles(['contable']);
            } elseif (
                str_contains($areaName, 'trámite') ||
                str_contains($areaName, 'tramite') ||
                str_contains($areaName, 'escrituración') ||
                str_contains($areaName, 'escrituracion') ||
                str_contains($areaName, 'cartera')
            ) {
                $user->syncRoles(['cartera_tramites']);
            } elseif (
                str_contains($areaName, 'marketing') ||
                str_contains($areaName, 'mercadeo') ||
                str_contains($areaName, 'publicidad')
            ) {
                $user->syncRoles(['mercadeo']);
            } elseif (
                str_contains($areaName, 'transversal') ||
                str_contains($areaName, 'documental') ||
                str_contains($areaName, 'proceso') ||
                str_contains($areaName, 'archivo')
            ) {
                $user->syncRoles(['transversal']);
            } else {
                // Fallback para usuarios sin área definida
                $user->syncRoles(['transversal']);
            }
        }

        // 5. Limpieza de roles antiguos sin usuarios asignados
        $legacyRoles = ['director', 'residente', 'empleado'];
        foreach ($legacyRoles as $legacyName) {
            $legacyRole = Role::where('name', $legacyName)->first();
            if ($legacyRole && $legacyRole->users()->count() === 0) {
                $legacyRole->delete();
                $this->command->info("Rol legado '{$legacyName}' eliminado.");
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->command->info('Roles y permisos configurados y usuarios asignados exitosamente.');
    }
}
