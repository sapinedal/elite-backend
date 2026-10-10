<?php

namespace App\Http\Modules\Users\Controller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    /**
     * Listado de todos los roles con sus permisos asignados y conteo de usuarios.
     */
    public function index()
    {
        $roles = Role::with('permissions:id,name')
            ->withCount('users')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json($roles);
    }

    /**
     * Crear un nuevo rol con permisos.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name'
        ]);

        $role = Role::create([
            'name' => strtolower(trim($validated['name'])),
            'guard_name' => 'web'
        ]);

        if (!empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json($role->load('permissions:id,name'), 201);
    }

    /**
     * Mostrar un rol específico.
     */
    public function show(Role $role)
    {
        return response()->json($role->load(['permissions:id,name', 'users:id,name,email']));
    }

    /**
     * Actualizar un rol y sincronizar sus permisos.
     */
    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100|unique:roles,name,' . $role->id,
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name'
        ]);

        if (isset($validated['name']) && $role->name !== 'admin') {
            $role->name = strtolower(trim($validated['name']));
            $role->save();
        }

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json($role->load('permissions:id,name'));
    }

    /**
     * Eliminar un rol (protege el rol admin).
     */
    public function destroy(Role $role)
    {
        if ($role->name === 'admin') {
            return response()->json(['message' => 'No es posible eliminar el rol Administrador del sistema.'], 403);
        }

        if ($role->users()->count() > 0) {
            return response()->json([
                'message' => 'No puedes eliminar este rol porque tiene ' . $role->users()->count() . ' usuarios asociados.'
            ], 422);
        }

        $role->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json(['message' => 'Rol eliminado exitosamente.']);
    }

    /**
     * Obtener el catálogo completo de permisos categorizados por módulo.
     */
    public function permissionsCatalog()
    {
        $permissions = Permission::all();

        // Categorización descriptiva por módulo
        $modules = [
            'obra' => [
                'title' => 'Control de Obra / Interventoría',
                'description' => 'Módulo de supervisión de frentes, avances de obra e informes técnicos',
                'permissions' => []
            ],
            'bitacora' => [
                'title' => 'Bitácora & Tareas',
                'description' => 'Compromisos de equipo, dailys standup y seguimiento operativo',
                'permissions' => []
            ],
            'kpi' => [
                'title' => 'KPIs & Evaluaciones',
                'description' => 'Tableros de rendimiento, metas por cargo y evaluaciones de desempeño',
                'permissions' => []
            ],
            'ftra' => [
                'title' => 'FTRA (Fichas Técnicas & Calidad)',
                'description' => 'Formatos de inspección, registros en campo, firmas y aprobaciones',
                'permissions' => []
            ],
            'juridica' => [
                'title' => 'Área Jurídica',
                'description' => 'Contratos legales, minutas, pólizas de seguros y carpetas Drive',
                'permissions' => []
            ],
            'usuarios' => [
                'title' => 'Gestión de Usuarios',
                'description' => 'Creación de colaboradores, asignación de cargos y credenciales',
                'permissions' => []
            ],
            'proyectos' => [
                'title' => 'Proyectos & Torres',
                'description' => 'Gestión y parametrización de desarrollos inmobiliarios',
                'permissions' => []
            ],
            'contratos' => [
                'title' => 'Tipos de Contrato',
                'description' => 'Parametrización contractual y plantillas',
                'permissions' => []
            ],
            'configuracion' => [
                'title' => 'Áreas & Cargos',
                'description' => 'Organigrama y estructura organizacional',
                'permissions' => []
            ],
            'roles' => [
                'title' => 'Roles del Sistema',
                'description' => 'Administración de perfiles y paquetes de acceso',
                'permissions' => []
            ],
            'permisos' => [
                'title' => 'Permisos del Sistema',
                'description' => 'Visualización de privilegios atómicos',
                'permissions' => []
            ]
        ];

        foreach ($permissions as $perm) {
            $prefix = explode('.', $perm->name)[0];
            if (isset($modules[$prefix])) {
                $modules[$prefix]['permissions'][] = $perm->name;
            }
        }

        return response()->json([
            'all' => $permissions->pluck('name'),
            'modules' => $modules
        ]);
    }
}
