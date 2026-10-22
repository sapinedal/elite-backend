<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Divide la bitácora de tareas por proyecto:
     * - project_user: qué proyectos puede ver cada usuario (muchos a muchos).
     * - Proyecto "Corporativo" para las tareas que no pertenecen a una obra (áreas administrativas).
     * - tasks.project_id obligatorio; las tareas existentes pasan a Ciudadela San Miguel.
     * - Permiso proyectos.ver_todos para quienes ven todos los proyectos sin estar asignados (admin, gerencia).
     */
    public function up(): void
    {
        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });

        DB::table('projects')->insertOrIgnore([
            'code' => 'corporativo',
            'name' => 'Corporativo',
            'subtitle' => 'Áreas administrativas',
            'description' => 'Tareas transversales de la compañía que no pertenecen a una obra específica.',
            'total_budget' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // restrictOnDelete: no se puede borrar un proyecto que todavía tiene tareas
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('id')->constrained('projects')->restrictOnDelete();
        });

        // Todas las tareas registradas hasta hoy son de Ciudadela San Miguel (code: vis)
        $sanMiguel = DB::table('projects')
            ->where('code', 'vis')
            ->orWhere('name', 'Ciudadela San Miguel')
            ->orderBy('id')
            ->first();

        $hasTasks = DB::table('tasks')->exists();
        throw_if(
            $hasTasks && !$sanMiguel,
            Exception::class,
            'No existe el proyecto Ciudadela San Miguel (code: vis) para asignar las tareas existentes. Ejecuta ProjectSeeder primero.'
        );

        if ($sanMiguel) {
            DB::table('tasks')->whereNull('project_id')->update(['project_id' => $sanMiguel->id]);

            // Los usuarios actuales trabajaban sobre esas tareas: se asignan a San Miguel para que sigan viéndolas
            $now = now();
            $rows = DB::table('users')->pluck('id')->map(fn ($userId) => [
                'project_id' => $sanMiguel->id,
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            DB::table('project_user')->insertOrIgnore($rows);
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable(false)->change();
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $permission = Permission::firstOrCreate(['name' => 'proyectos.ver_todos', 'guard_name' => 'web']);
        Role::whereIn('name', ['admin', 'gerencia'])->where('guard_name', 'web')->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::where('name', 'proyectos.ver_todos')->where('guard_name', 'web')->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });

        Schema::dropIfExists('project_user');

        // El proyecto Corporativo solo se elimina si nadie lo está usando
        $corporativo = DB::table('projects')->where('code', 'corporativo')->first();
        $inUse = $corporativo && (
            (Schema::hasTable('contracts') && DB::table('contracts')->where('project_id', $corporativo->id)->exists()) ||
            (Schema::hasTable('towers') && DB::table('towers')->where('project_id', $corporativo->id)->exists())
        );
        if ($corporativo && !$inUse) {
            DB::table('projects')->where('id', $corporativo->id)->delete();
        }
    }
};
