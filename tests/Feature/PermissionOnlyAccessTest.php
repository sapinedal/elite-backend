<?php

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Cortes\Service\CorteNotificationService;
use App\Http\Modules\Cortes\Service\CorteWorkflow;
use App\Http\Modules\Ordenes\Models\Orden;
use App\Http\Modules\Ordenes\Service\OrdenNotificationService;
use App\Http\Modules\Ordenes\Service\OrdenWorkflow;
use App\Http\Modules\Pagos\Service\PagoService;
use App\Http\Modules\Configuracion\Models\Area;
use App\Http\Modules\Configuracion\Models\Position;
use App\Http\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cortes.multifox_emails' => [], 'cortes.multifox_allowed_domains' => [], 'ordenes.almacen_emails' => []]);
    foreach (['cortes.multifox', 'pagos.priorizar', 'ordenes.gestionar_almacen'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $area = Area::create(['name' => 'Técnica']);
    $user = fn (string $email, ?string $cargo = null) => User::create([
        'name' => $email, 'email' => $email, 'password' => 'secret',
        'position_id' => $cargo ? Position::create(['name' => $cargo, 'area_id' => $area->id])->id : null,
    ]);

    // Mismo cargo que antes daba acceso, pero sin el permiso
    $this->colega = $user('monica@inverconstruccion.com', 'Profesional de Planeación, Control y Contratos');
    $this->almacenista = $user('otro@inverconstruccion.com', 'Almacenista de Obra');

    $this->paula = $user('paula@inverconstruccion.com');
    $this->paula->givePermissionTo(['cortes.multifox', 'pagos.priorizar', 'proyectos.ver_todos']);

    $this->juanCarlos = $user('juan@inverconstruccion.com');
    $this->juanCarlos->givePermissionTo(['ordenes.gestionar_almacen', 'proyectos.ver_todos']);

    // Gerencia tiene todos los permisos por rol: puede actuar, pero no recibe los avisos de responsable
    $this->gerente = $user('gerente@inverconstruccion.com');
    $this->gerente->assignRole(Role::firstOrCreate(['name' => 'gerencia-test', 'guard_name' => 'web'])
        ->givePermissionTo(['cortes.multifox', 'ordenes.gestionar_almacen', 'proyectos.ver_todos']));
});

test('el cargo ya no da acceso: solo el permiso', function () {
    expect(CorteWorkflow::canConfirmMultifox($this->colega))->toBeFalse()
        ->and(app(PagoService::class)->canPrioritize($this->colega))->toBeFalse()
        ->and(OrdenWorkflow::stepsFor($this->almacenista))->toBe([])
        ->and(CorteWorkflow::canConfirmMultifox($this->paula))->toBeTrue()
        ->and(app(PagoService::class)->canPrioritize($this->paula))->toBeTrue()
        ->and(OrdenWorkflow::stepsFor($this->juanCarlos))->toBe([OrdenWorkflow::STEP_ORDEN_ERP, OrdenWorkflow::STEP_ENTRADA])
        ->and(CorteWorkflow::canConfirmMultifox($this->gerente))->toBeTrue();
});

test('los avisos de Multifox y Almacén llegan solo a quien tiene el permiso asignado directamente', function () {
    $project = Project::create(['code' => 'sm', 'name' => 'San Miguel', 'is_active' => true]);
    $orden = new Orden(['project_id' => $project->id]);

    expect(app(CorteNotificationService::class)->multifoxRecipients()->all())->toBe(['paula@inverconstruccion.com'])
        ->and(app(OrdenNotificationService::class)->stepRecipients($orden, OrdenWorkflow::STEP_ENTRADA)->values()->all())
        ->toBe(['juan@inverconstruccion.com']);
});
