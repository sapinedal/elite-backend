<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Este seeder declara los permisos del nuevo módulo de Bitácora de Tareas.
     * Como actualmente no tienes Spatie Laravel-Permission integrado, dejamos 
     * declarada la estructura y los comentarios educativos para que puedas 
     * descomentarlo y ejecutarlo sin problemas una vez que agregues el paquete.
     */
    public function run(): void
    {
        // 1. Definimos los permisos específicos del dominio de tareas
        $permissions = [
            'bitacora.crear',   // Permitirá a todo el personal añadir nuevas tareas
            'bitacora.editar',  // Reservado para roles que coordinan/administran tareas
            'bitacora.eliminar', // Reservado para administradores
            'bitacora.ver',     // Para visualización general
        ];

        $this->command->info('--- Sembrando permisos provisionales de Bitácora ---');

        // =========================================================================
        // 💡 GUÍA DE INTEGRACIÓN DE SPATIE (Cuando lo agregues al composer.json):
        // =========================================================================
        //
        // 1. Instala el paquete: composer require spatie/laravel-permission
        // 2. Publica la migración y ejecútala: php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" && php artisan migrate
        // 3. Agrega el trait "use HasRoles;" en tu modelo App\Http\Modules\Users\Models\User.php
        // 4. Descomenta el siguiente código y corre: php artisan db:seed --class=PermissionSeeder
        //
        // =========================================================================
        /*
        use Spatie\Permission\Models\Permission;
        use Spatie\Permission\Models\Role;

        // Crear los permisos si no existen
        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName);
        }

        // Crear roles principales
        $adminRole = Role::findOrCreate('admin');
        $empleadoRole = Role::findOrCreate('empleado');

        // Asignar todos los permisos al Administrador
        $adminRole->givePermissionTo(Permission::all());

        // Asignar solo el permiso de creación al Empleado general
        $empleadoRole->givePermissionTo('bitacora.crear');
        */

        $this->command->info('Permisos definidos en el Seeder: ' . implode(', ', $permissions));
        $this->command->info('¡Listo! El seeder de permisos quedó preparado para Spatie.');
    }
}
