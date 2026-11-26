<?php

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Cortes\Service\CorteWorkflow;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();

    $this->project = Project::create(['code' => 'vis', 'name' => 'San Miguel', 'is_active' => true]);
    $contractor = FtraContractor::create(['name' => 'EMELECT GROUP SAS']);
    $user = fn (string $email, array $permissions) => tap(
        User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => 'secret']),
        fn (User $u) => $u->givePermissionTo([...$permissions, 'cortes.ver', 'proyectos.ver_todos'])
    );
    $this->residente = $user('residente@example.com', ['cortes.crear']);
    $this->interventor = $user('interventor@example.com', ['cortes.aprobar_interventor']);
    $this->director = $user('director@example.com', ['cortes.aprobar_director']);

    $this->corte = Corte::create([
        'code' => 'CORTE 1', 'project_id' => $this->project->id, 'contractor_id' => $contractor->id,
        'status' => CorteWorkflow::STATUS_PENDIENTE_INTERVENTOR, 'current_stage' => CorteWorkflow::STAGE_INTERVENTOR,
        'file_name' => 'corte.pdf', 'file_path' => 'tecnica/corte.pdf', 'created_by' => $this->residente->id,
    ]);
});

test('el Interventor rechaza y deja continuar: queda Rechazado pero aprobado y sigue al Director de Obra', function () {
    $this->actingAs($this->interventor)->getJson("/api/v1/cortes/{$this->corte->id}")->assertOk()
        ->assertJsonPath('data.can_reject_continue', true);

    // El motivo es obligatorio
    $this->actingAs($this->interventor)->postJson("/api/v1/cortes/{$this->corte->id}/review", ['decision' => 'rechazar_continuar'])
        ->assertStatus(422)->assertJsonValidationErrors('comment');

    $this->actingAs($this->interventor)->postJson("/api/v1/cortes/{$this->corte->id}/review", ['decision' => 'rechazar_continuar', 'comment' => 'Cantidades no verificadas'])
        ->assertOk()
        ->assertJsonPath('data.status', CorteWorkflow::STATUS_PENDIENTE_DIRECTOR)
        ->assertJsonPath('data.interventor_rejected', true)
        ->assertJsonPath('data.progress.0.state', 'rechazado_continua');

    expect($this->corte->history()->pluck('action')->all())->toContain('rechazado_continua');

    // El Director no puede usar esta opción; aprueba normalmente y la marca se conserva
    $this->actingAs($this->director)->getJson("/api/v1/cortes/{$this->corte->id}")->assertOk()->assertJsonPath('data.can_reject_continue', false);
    $this->actingAs($this->director)->postJson("/api/v1/cortes/{$this->corte->id}/review", ['decision' => 'rechazar_continuar', 'comment' => 'x'])
        ->assertStatus(422);
    $this->actingAs($this->director)->postJson("/api/v1/cortes/{$this->corte->id}/review", ['decision' => 'aprobar'])
        ->assertOk()->assertJsonPath('data.interventor_rejected', true);
});

test('el rechazo normal del Interventor sigue devolviendo el corte al residente', function () {
    $this->actingAs($this->interventor)->postJson("/api/v1/cortes/{$this->corte->id}/review", ['decision' => 'devolver', 'comment' => 'Falta soporte'])
        ->assertOk()->assertJsonPath('data.status', CorteWorkflow::STATUS_DEVUELTO)->assertJsonPath('data.interventor_rejected', false);
});
