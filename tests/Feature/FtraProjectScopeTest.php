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
    foreach (['ftra.ver', 'ftra.crear', 'ftra.editar', 'ftra.revisar', 'ftra.aprobar', 'ftra.eliminar'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $this->sanMiguel = Project::create(['code' => 'vis', 'name' => 'San Miguel', 'is_active' => true]);
    $this->caona = Project::create(['code' => 'caona', 'name' => 'Caona', 'is_active' => true]);

    $this->contractor = FtraContractor::create(['name' => 'EMELECT GROUP SAS']);
    $this->format = FtraFormat::create(['name' => 'Recibo de obra', 'code' => 'FT-01', 'version' => '1']);
    $this->tower = Tower::create(['project_id' => $this->sanMiguel->id, 'name' => 'TORRE 2', 'floors' => 20, 'apartments_per_floor' => 10]);

    // Solo asignado a San Miguel
    $this->user = User::create(['name' => 'Inspector', 'email' => 'inspector@example.com', 'password' => 'secret']);
    $this->user->givePermissionTo(['ftra.ver', 'ftra.crear', 'ftra.revisar', 'ftra.eliminar']);
    $this->user->projects()->attach($this->sanMiguel);

    $record = fn (Project $project, string $apto) => FtraRecord::create([
        'project_id' => $project->id,
        'contractor_id' => $this->contractor->id,
        'format_id' => $this->format->id,
        'registered_by_id' => $this->user->id,
        'piso' => '1',
        'apartamento' => $apto,
        'status' => 'Registrada',
    ]);
    $this->recordSanMiguel = $record($this->sanMiguel, '101');
    $this->recordCaona = $record($this->caona, '202');
});

function ftraPayload(): array
{
    return [
        'contractor_id' => test()->contractor->id,
        'format_id' => test()->format->id,
        'tower_id' => test()->tower->id,
        'piso' => '3',
        'apartamento' => '303',
        'resultado_inspeccion' => 'Recibido a satisfacción',
        'orden_aseo' => 'Aprobado',
    ];
}

it('lista solo los registros del proyecto activo', function () {
    $this->actingAs($this->user)
        ->withHeader('X-Project-Id', (string) $this->sanMiguel->id)
        ->getJson('/api/v1/ftra/records')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->recordSanMiguel->id);
});

it('sin proyecto activo lista solo los proyectos accesibles del usuario', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/ftra/records')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->recordSanMiguel->id);
});

it('rechaza un proyecto activo al que el usuario no tiene acceso', function () {
    $this->actingAs($this->user)
        ->withHeader('X-Project-Id', (string) $this->caona->id)
        ->getJson('/api/v1/ftra/records')
        ->assertForbidden();
});

it('no permite ver, cambiar estado ni eliminar registros de otro proyecto', function () {
    $this->actingAs($this->user)->getJson("/api/v1/ftra/records/{$this->recordCaona->id}")->assertForbidden();
    $this->actingAs($this->user)->postJson("/api/v1/ftra/records/{$this->recordCaona->id}/approve", ['signature' => 'data:image/png;base64,AAA'])->assertForbidden();
    $this->actingAs($this->user)->deleteJson("/api/v1/ftra/records/{$this->recordCaona->id}")->assertForbidden();

    expect($this->recordCaona->fresh()->status)->toBe('Registrada');
});

it('registra el FTRA en el proyecto activo', function () {
    $this->actingAs($this->user)
        ->withHeader('X-Project-Id', (string) $this->sanMiguel->id)
        ->postJson('/api/v1/ftra/records', ftraPayload())
        ->assertCreated()
        ->assertJsonPath('record.project_id', $this->sanMiguel->id);
});

it('exige un proyecto activo para registrar', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/ftra/records', ftraPayload())
        ->assertStatus(422);
});
