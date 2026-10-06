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

        $this->command->info('Configurando Permisos y Roles con Spatie...');

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

            // KPIs y Evaluaciones
            'kpi.ver',
            'kpi.evaluar',
            'kpi.historial',
            'kpi.parametrizar',

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

        $this->command->info('Permisos creados exitosamente: ' . count($permissions));

        // 2. Definición de Roles Base
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $directorRole = Role::firstOrCreate(['name' => 'director', 'guard_name' => 'web']);
        $residenteRole = Role::firstOrCreate(['name' => 'residente', 'guard_name' => 'web']);
        $empleadoRole = Role::firstOrCreate(['name' => 'empleado', 'guard_name' => 'web']);

        // 3. Asignación de Permisos a Roles
        // Admin: Todos los permisos del sistema
        $adminRole->syncPermissions(Permission::all());

        // Director / Gerente: Operación completa y gestión
        $directorRole->syncPermissions([
            'obra.ver', 'obra.crear', 'obra.editar', 'obra.eliminar',
            'bitacora.ver', 'bitacora.crear', 'bitacora.editar', 'bitacora.eliminar',
            'kpi.ver', 'kpi.evaluar', 'kpi.historial', 'kpi.parametrizar',
            'ftra.ver', 'ftra.crear', 'ftra.editar', 'ftra.eliminar', 'ftra.revisar', 'ftra.aprobar', 'ftra.parametrizar',
            'juridica.ver', 'juridica.crear', 'juridica.editar',
            'usuarios.ver', 'proyectos.ver', 'contratos.ver', 'configuracion.ver',
        ]);

        // Residente / Supervisor de Obra
        $residenteRole->syncPermissions([
            'obra.ver', 'obra.crear',
            'bitacora.ver', 'bitacora.crear',
            'kpi.ver',
            'ftra.ver', 'ftra.crear', 'ftra.editar', 'ftra.revisar',
            'proyectos.ver',
        ]);

        // Empleado / Colaborador General
        $empleadoRole->syncPermissions([
            'obra.ver',
            'bitacora.ver', 'bitacora.crear',
            'kpi.ver',
            'ftra.ver',
        ]);

        $this->command->info('Roles sincronizados con sus permisos correspondientes.');

        // 4. Asignación automática de roles a usuarios existentes
        $users = User::with('position')->get();
        foreach ($users as $user) {
            $posName = strtolower(optional($user->position)->name ?? '');
            $email = strtolower($user->email);
            $userName = strtolower($user->name);

            if (
                $email === 'admin@elite.com' ||
                $email === 'samuel.pineda@elite.com' ||
                str_contains($userName, 'admin') ||
                str_contains($posName, 'administrador') ||
                str_contains($posName, 'tecnología') ||
                str_contains($posName, 'fullstack')
            ) {
                $user->syncRoles(['admin']);
            } elseif (
                str_contains($posName, 'director') ||
                str_contains($posName, 'gerente') ||
                str_contains($posName, 'coordinador')
            ) {
                $user->syncRoles(['director']);
            } elseif (
                str_contains($posName, 'residente') ||
                str_contains($posName, 'supervisor') ||
                str_contains($posName, 'inspector')
            ) {
                $user->syncRoles(['residente']);
            } else {
                if ($user->roles()->count() === 0) {
                    $user->syncRoles(['empleado']);
                }
            }
        }

        $this->command->info('Roles asignados exitosamente a todos los usuarios.');
    }
}
