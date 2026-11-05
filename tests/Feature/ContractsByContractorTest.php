<?php

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Users\Models\User;
use App\Services\DirectUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();

    $this->mock(DirectUploadService::class, function ($mock) {
        $mock->shouldReceive('claimUpload')->andReturnUsing(fn ($token, $user, $directory) => [
            'file_name' => 'corte.pdf', 'file_path' => "{$directory}/corte.pdf", 'file_size' => 1000, 'file_mime' => 'application/pdf',
        ]);
        $mock->shouldReceive('cleanSegment')->andReturnUsing(fn ($value) => trim($value));
    });

    $this->sanMiguel = Project::create(['code' => 'sm', 'name' => 'San Miguel', 'is_active' => true]);
    $this->otro = Project::create(['code' => 'ot', 'name' => 'Otro', 'is_active' => true]);
    $this->emelect = FtraContractor::create(['name' => 'Emelect Group']);
    $this->ihc = FtraContractor::create(['name' => 'IHC']);

    $contract = fn (Project $project, ?FtraContractor $contractor, string $code) => ContratacionContract::create([
        'project_id' => $project->id, 'contractor_id' => $contractor?->id, 'contract_code' => $code, 'consecutive' => '1',
        'contractor_name' => $contractor?->name ?? 'Sin proveedor', 'object' => 'Obra', 'status' => 'En ejecución',
        's3_path' => "tecnica/Contratos/{$code}",
    ]);

    $this->fundacion = $contract($this->sanMiguel, $this->emelect, 'T2-MO-01');
    $this->estructura = $contract($this->sanMiguel, $this->emelect, 'T2-MO-03');
    $this->emelectOtro = $contract($this->otro, $this->emelect, 'OT-01');
    $this->redesIhc = $contract($this->sanMiguel, $this->ihc, 'T2-SI-01');
    $this->sinProveedor = $contract($this->sanMiguel, null, 'T2-ODS-01');

    $this->residente = User::create(['name' => 'Residente', 'email' => 'residente@example.com', 'password' => 'secret']);
    $this->residente->givePermissionTo(['cortes.ver', 'cortes.crear', 'proyectos.ver_todos']);
});

test('lista solo los contratos del proveedor elegido', function () {
    $this->actingAs($this->residente)
        ->getJson("/api/v1/contratacion/contractors/{$this->emelect->id}/contracts")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.contract_code', 'OT-01');
});

test('filtra por el proyecto del formulario', function () {
    $codes = $this->actingAs($this->residente)
        ->getJson("/api/v1/contratacion/contractors/{$this->emelect->id}/contracts?project_id={$this->sanMiguel->id}")
        ->assertOk()
        ->json('data.*.contract_code');

    expect($codes)->toBe(['T2-MO-01', 'T2-MO-03']);
});

test('sin permiso de contratación ni de registro no puede consultar', function () {
    $user = User::create(['name' => 'Otro', 'email' => 'otro@example.com', 'password' => 'secret']);

    $this->actingAs($user)
        ->getJson("/api/v1/contratacion/contractors/{$this->emelect->id}/contracts")
        ->assertForbidden();
});

test('el corte rechaza un contrato de otro proveedor', function () {
    $this->actingAs($this->residente)->postJson('/api/v1/cortes', [
        'code' => 'CORTE 1',
        'project_id' => $this->sanMiguel->id,
        'contractor_id' => $this->emelect->id,
        'contract_id' => $this->redesIhc->id,
        'upload_token' => (string) Str::uuid(),
    ])->assertStatus(422)->assertJsonValidationErrors('contract_id');
});

test('un contrato sin proveedor asignado se acepta con cualquier proveedor', function () {
    $this->actingAs($this->residente)->postJson('/api/v1/cortes', [
        'code' => 'CORTE 1',
        'project_id' => $this->sanMiguel->id,
        'contractor_id' => $this->ihc->id,
        'contract_id' => $this->sinProveedor->id,
        'upload_token' => (string) Str::uuid(),
    ])->assertCreated();
});
