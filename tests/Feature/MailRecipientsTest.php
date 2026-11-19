<?php

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Users\Models\User;
use App\Http\Modules\Users\Service\MailRecipients;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::create(['code' => 'sm', 'name' => 'San Miguel', 'is_active' => true]);
    $this->otro = Project::create(['code' => 'ot', 'name' => 'Otro', 'is_active' => true]);
    $this->user = fn (string $email) => User::create(['name' => $email, 'email' => $email, 'password' => 'secret']);
});

test('existe un permiso de correo por cada destinatario y aparece en el catálogo con su descripción', function () {
    expect(Permission::where('name', 'like', 'correos.%')->pluck('name')->sort()->values()->all())
        ->toBe(collect(array_keys(MailRecipients::PERMISSIONS))->sort()->values()->all())
        ->and(Permission::where('name', 'contratacion.alertas_polizas')->exists())->toBeFalse();

    $admin = ($this->user)('admin@inverconstruccion.com');
    $admin->givePermissionTo(Permission::findOrCreate('usuarios.editar', 'web'));

    $catalog = $this->actingAs($admin)->getJson('/api/v1/permissions')->assertOk()->json();

    expect($catalog['modules']['correos']['permissions'])->toContain('correos.cortes.interventor')
        ->and($catalog['labels']['correos.cortes.interventor'])->toBe(MailRecipients::PERMISSIONS['correos.cortes.interventor']);

    // Todo permiso existente pertenece a un módulo del catálogo (si no, no se puede asignar desde la pantalla)
    Permission::findOrCreate('anticipos.aprobar_director', 'web');
    Permission::findOrCreate('cuentas_cobro.aprobar_gerencia', 'web');
    $catalog = $this->getJson('/api/v1/permissions')->assertOk()->json();

    expect(collect($catalog['all'])->diff(collect($catalog['modules'])->flatMap(fn ($m) => $m['permissions']))->values()->all())->toBe([]);
});

test('reciben solo los usuarios con el permiso asignado directamente y acceso al proyecto, más los externos', function () {
    $supervisor = ($this->user)('supervisor@inverconstruccion.com');
    $supervisor->givePermissionTo('correos.cortes.interventor');
    $supervisor->projects()->attach($this->project);

    $deOtroProyecto = ($this->user)('otro@inverconstruccion.com');
    $deOtroProyecto->givePermissionTo('correos.cortes.interventor');
    $deOtroProyecto->projects()->attach($this->otro);

    $porRol = ($this->user)('gerente@inverconstruccion.com');
    $porRol->assignRole(Role::firstOrCreate(['name' => 'gerencia-test', 'guard_name' => 'web'])->givePermissionTo(['correos.cortes.interventor', 'proyectos.ver_todos']));

    expect(MailRecipients::for('cortes', 'interventor', $this->project->id, ['externo@example.com'])->all())
        ->toBe(['externo@example.com', 'supervisor@inverconstruccion.com']);
});

test('la migración asigna los permisos a quienes hoy reciben los correos', function () {
    $migration = require database_path('migrations/2026_11_19_100000_create_mail_notification_permissions.php');

    // Correo de los valores por defecto (en Gmail los puntos no cuentan), permiso anterior y externo configurado
    $druiz = ($this->user)('DRUIZ.EQP@gmail.com');
    $paula = ($this->user)('paula@inverconstruccion.com');
    $paula->givePermissionTo('cortes.multifox');
    $director = ($this->user)('dirobrasanmiguel@inverconstruccion.com');
    config(['ftra.stage_emails.supervisor' => ['nuevo.supervisor@example.com']]);
    $nuevo = ($this->user)('nuevo.supervisor@example.com');

    $migration->up();

    expect($druiz->fresh()->hasDirectPermission('correos.cortes.interventor'))->toBeTrue()
        ->and($druiz->fresh()->hasDirectPermission('correos.ftra.supervisor'))->toBeTrue()
        ->and($paula->fresh()->hasDirectPermission('correos.cortes.multifox'))->toBeTrue()
        ->and($paula->fresh()->hasDirectPermission('correos.cuentas_cobro.multifox'))->toBeTrue()
        ->and($director->fresh()->getDirectPermissions()->pluck('name')->sort()->values()->all())->toBe([
            'correos.anticipos.director_obra', 'correos.contratacion.polizas', 'correos.cortes.director_obra',
            'correos.cuentas_cobro.director_obra', 'correos.ftra.director_obra', 'correos.ordenes.director_obra',
        ])
        ->and($nuevo->fresh()->hasDirectPermission('correos.ftra.supervisor'))->toBeTrue();
});
