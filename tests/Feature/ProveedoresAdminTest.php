<?php

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['configuracion.ver', 'configuracion.editar'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret']);
    $this->admin->givePermissionTo(['configuracion.ver', 'configuracion.editar']);

    $this->libre = FtraContractor::create(['name' => 'Proveedor Libre', 'nit' => '900']);
    $this->enUso = FtraContractor::create(['name' => 'Emelect Group', 'nit' => '901']);

    $project = Project::create(['code' => 'sm', 'name' => 'San Miguel', 'is_active' => true]);
    ContratacionContract::create([
        'project_id' => $project->id, 'contractor_id' => $this->enUso->id, 'contract_code' => 'T2-MO-01', 'consecutive' => '1',
        'contractor_name' => 'Emelect Group', 'object' => 'Obra', 'status' => 'En ejecución', 's3_path' => 'tecnica/Contratos/T2-MO-01',
    ]);
});

test('lista los proveedores con su uso', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/v1/configuracion/proveedores')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Emelect Group')
        ->assertJsonPath('data.0.usage_count', 1)
        ->assertJsonPath('data.1.usage_count', 0);
});

test('elimina un proveedor sin registros asociados', function () {
    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/configuracion/proveedores/{$this->libre->id}")
        ->assertOk();

    expect(FtraContractor::find($this->libre->id))->toBeNull();
});

test('no elimina un proveedor con registros asociados', function () {
    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/configuracion/proveedores/{$this->enUso->id}")
        ->assertStatus(409)
        ->assertJsonPath('status', 'error');

    expect(FtraContractor::find($this->enUso->id))->not->toBeNull();
});

test('desactiva un proveedor y evita nombres duplicados', function () {
    $this->actingAs($this->admin)
        ->putJson("/api/v1/configuracion/proveedores/{$this->enUso->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/configuracion/proveedores/{$this->libre->id}", ['name' => 'emelect group'])
        ->assertStatus(422);
});

test('requiere permiso de configuración para eliminar', function () {
    $user = User::create(['name' => 'Otro', 'email' => 'otro@example.com', 'password' => 'secret']);
    $user->givePermissionTo('configuracion.ver');

    $this->actingAs($user)
        ->deleteJson("/api/v1/configuracion/proveedores/{$this->libre->id}")
        ->assertForbidden();
});
