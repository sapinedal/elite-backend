<?php

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Configuracion\Models\Tower;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Ftra\Models\FtraFormat;
use App\Http\Modules\Ftra\Models\FtraRecord;
use App\Http\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['ftra.ver', 'ftra.crear', 'ftra.editar', 'ftra.parametrizar', 'contratacion.ver', 'contratacion.crear', 'proyectos.editar', 'proyectos.crear', 'proyectos.ver'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $this->project = Project::create(['code' => 'vis', 'name' => 'San Miguel', 'is_active' => true]);
    $this->otherProject = Project::create(['code' => 'caona', 'name' => 'Caona', 'is_active' => true]);
    $this->tower = Tower::create(['project_id' => $this->project->id, 'name' => 'TORRE 2', 'floors' => 20, 'apartments_per_floor' => 10]);
    $this->urbanismo = Tower::create(['project_id' => $this->project->id, 'name' => 'URBANISMO']);
    $this->otherTower = Tower::create(['project_id' => $this->otherProject->id, 'name' => 'TORRE 1', 'floors' => 5, 'apartments_per_floor' => 4]);

    $this->contractor = FtraContractor::create(['name' => 'EMELECT GROUP SAS', 'nit' => '900123']);
    $this->format = FtraFormat::create(['name' => 'Recibo de obra', 'code' => 'FT-01', 'version' => '1']);

    $this->residente = User::create(['name' => 'Residente Acabados', 'email' => 'residente@example.com', 'password' => 'secret']);
    $this->residente->givePermissionTo(['ftra.ver', 'ftra.crear', 'ftra.editar', 'ftra.parametrizar']);
    $this->residente->projects()->attach([$this->project->id, $this->otherProject->id]);
});

function ftraRecordPayload(array $overrides = []): array
{
    return [
        'contractor_id' => test()->contractor->id,
        'format_id' => test()->format->id,
        'tower_id' => test()->tower->id,
        'piso' => 12,
        'apartamento' => '1203',
        'resultado_inspeccion' => 'Recibido a satisfacción',
        'orden_aseo' => 'Aprobado',
        ...$overrides,
    ];
}

it('el responsable de la revisión es el usuario logueado, aunque envíen otro', function () {
    $otro = User::create(['name' => 'Otro', 'email' => 'otro@example.com', 'password' => 'secret']);

    $this->actingAs($this->residente)
        ->withHeader('X-Project-Id', (string) $this->project->id)
        ->postJson('/api/v1/ftra/records', ftraRecordPayload(['responsable_user_id' => $otro->id]))
        ->assertCreated()
        ->assertJsonPath('record.responsable.id', $this->residente->id)
        ->assertJsonPath('record.tower.name', 'TORRE 2');

    expect(FtraRecord::first()->responsable_user_id)->toBe($this->residente->id);
});

it('valida que el apartamento exista en la distribución de la torre', function (array $overrides, string $field) {
    $this->actingAs($this->residente)
        ->withHeader('X-Project-Id', (string) $this->project->id)
        ->postJson('/api/v1/ftra/records', ftraRecordPayload($overrides))
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);
})->with([
    'piso fuera de rango' => [['piso' => 21, 'apartamento' => '2101'], 'apartamento'],
    'apartamento 11 en piso de 10' => [['apartamento' => '1211'], 'apartamento'],
    'apartamento de otro piso' => [['apartamento' => '1103'], 'apartamento'],
    'torre sin pisos configurados' => [['tower_id' => null], 'tower_id'],
    'torre de otro proyecto' => [['tower_id' => 'other'], 'tower_id'],
]);

it('rechaza torres sin distribución o de otro proyecto', function () {
    foreach ([$this->urbanismo->id, $this->otherTower->id] as $towerId) {
        $this->actingAs($this->residente)
            ->withHeader('X-Project-Id', (string) $this->project->id)
            ->postJson('/api/v1/ftra/records', ftraRecordPayload(['tower_id' => $towerId, 'piso' => 1, 'apartamento' => '101']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('tower_id');
    }
});

it('lista las torres del proyecto activo y el último FTRA de cada apartamento', function () {
    $base = ['project_id' => $this->project->id, 'tower_id' => $this->tower->id, 'contractor_id' => $this->contractor->id, 'format_id' => $this->format->id, 'registered_by_id' => $this->residente->id, 'piso' => '12', 'apartamento' => '1201'];
    FtraRecord::create([...$base, 'status' => 'Registrada', 'resultado_inspeccion' => 'Rechazado']);
    FtraRecord::create([...$base, 'status' => 'Aprobada', 'resultado_inspeccion' => 'Recibido a satisfacción']);

    $this->actingAs($this->residente)
        ->withHeader('X-Project-Id', (string) $this->project->id)
        ->getJson('/api/v1/ftra/towers')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.floors', 20);

    $this->actingAs($this->residente)
        ->withHeader('X-Project-Id', (string) $this->project->id)
        ->getJson("/api/v1/ftra/towers/{$this->tower->id}/units")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.apartamento', '1201')
        ->assertJsonPath('data.0.records_count', 2)
        ->assertJsonPath('data.0.formats.0.resultado_inspeccion', 'Recibido a satisfacción');
});

it('FTRA ya no crea proveedores ni residentes', function () {
    $this->actingAs($this->residente)->postJson('/api/v1/ftra/contractors', ['name' => 'Nuevo'])->assertStatus(405);
    $this->actingAs($this->residente)->postJson('/api/v1/ftra/residentes', ['name' => 'Nuevo'])->assertNotFound();
});

it('Contratación crea proveedores y evita duplicados por nombre o NIT', function () {
    $user = User::create(['name' => 'Contratos', 'email' => 'contratos@example.com', 'password' => 'secret']);
    $user->givePermissionTo(['contratacion.ver', 'contratacion.crear']);
    $user->projects()->attach($this->project);

    $this->actingAs($user)->withHeader('X-Project-Id', (string) $this->project->id)
        ->postJson('/api/v1/contratacion/contractors', ['name' => 'Ferretería La 30', 'nit' => '800555'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Ferretería La 30');

    $this->actingAs($user)->withHeader('X-Project-Id', (string) $this->project->id)
        ->postJson('/api/v1/contratacion/contractors', ['name' => 'emelect group sas'])
        ->assertStatus(422);

    $this->actingAs($user)->withHeader('X-Project-Id', (string) $this->project->id)
        ->postJson('/api/v1/contratacion/contractors', ['name' => 'Otro nombre', 'nit' => '900123'])
        ->assertStatus(422);
});

it('Administración configura pisos y apartamentos de la torre', function () {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret']);
    $admin->givePermissionTo(['proyectos.ver', 'proyectos.crear', 'proyectos.editar']);

    $this->actingAs($admin)
        ->putJson("/api/v1/configuracion/towers/{$this->urbanismo->id}", ['floors' => 3, 'apartments_per_floor' => 4])
        ->assertOk()
        ->assertJsonPath('data.floors', 3);

    $this->actingAs($admin)
        ->putJson("/api/v1/configuracion/towers/{$this->urbanismo->id}", ['floors' => 3, 'apartments_per_floor' => 120])
        ->assertStatus(422);

    $this->actingAs($admin)
        ->postJson("/api/v1/configuracion/projects/{$this->project->id}/towers", ['name' => 'TORRE 3', 'floors' => 20, 'apartments_per_floor' => 10])
        ->assertCreated();

    $this->actingAs($admin)
        ->postJson("/api/v1/configuracion/projects/{$this->project->id}/towers", ['name' => 'torre 3'])
        ->assertStatus(422);
});
