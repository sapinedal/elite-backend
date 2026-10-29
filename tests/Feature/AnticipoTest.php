<?php

use App\Http\Modules\Anticipos\Mail\AnticipoNotificationMail;
use App\Http\Modules\Anticipos\Models\Anticipo;
use App\Http\Modules\Anticipos\Service\AnticipoWorkflow;
use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Configuracion\Models\Tower;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Pagos\Models\PagoCuenta;
use App\Http\Modules\Users\Models\User;
use App\Services\DirectUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

const CONTRATO_FOLDER = 'tecnica/Contratos San Miguel/TORRE 2/7. CONTRATOS MANO DE OBRA/8. EMELECT - M.O Fundación';

beforeEach(function () {
    Mail::fake();
    config([
        'anticipos.stage_emails' => ['director_obra' => ['director@example.com'], 'gerencia' => ['gerencia@example.com']],
        'anticipos.contabilidad' => ['contabilidad@example.com'],
        'anticipos.monitor' => [],
    ]);

    // S3 simulado: el archivo queda en la carpeta que se pide
    $this->mock(DirectUploadService::class, function ($mock) {
        $mock->shouldReceive('claimUpload')->andReturnUsing(fn ($token, $user, $directory) => [
            'file_name' => "{$token}.pdf",
            'file_path' => "{$directory}/{$token}.pdf",
            'file_size' => 1000,
            'file_mime' => 'application/pdf',
        ]);
        $mock->shouldReceive('moveTo')->andReturnUsing(fn ($path, $directory) => "{$directory}/" . basename($path));
        $mock->shouldReceive('cleanSegment')->andReturnUsing(fn ($value) => trim($value));
    });

    $this->project = Project::create(['code' => 'vis', 'name' => 'San Miguel', 'is_active' => true]);
    $tower = Tower::create(['project_id' => $this->project->id, 'name' => 'Torre 2']);
    $this->contractor = FtraContractor::create(['name' => 'EMELECT GROUP SAS', 'nit' => '901']);
    $this->contract = ContratacionContract::create([
        'project_id' => $this->project->id, 'tower_id' => $tower->id, 'contract_code' => '2759', 'consecutive' => '8',
        'contractor_name' => 'EMELECT - M.O Fundación', 'object' => 'Fundación', 'status' => 'En ejecución', 's3_path' => CONTRATO_FOLDER,
    ]);

    $user = fn (string $email, array $permissions) => tap(
        User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => 'secret']),
        fn (User $u) => $u->givePermissionTo([...$permissions, 'proyectos.ver_todos'])
    );
    $this->residente = $user('residente@example.com', ['anticipos.ver', 'anticipos.crear']);
    $this->director = $user('director@example.com', ['anticipos.ver', 'anticipos.aprobar_director']);
    $this->gerencia = $user('gerencia@example.com', ['anticipos.ver', 'anticipos.aprobar_gerencia']);
    $this->contabilidad = $user('contabilidad@example.com', ['anticipos.ver', 'anticipos.contabilizar']);
});

function radicarAnticipo(array $overrides = [])
{
    return test()->actingAs(test()->residente)->postJson('/api/v1/anticipos', [
        'contract_id' => test()->contract->id,
        'contractor_id' => test()->contractor->id,
        'document_number' => 'C-C 45',
        'amount' => 25000000,
        'description' => 'Anticipo 30% del contrato',
        'upload_token' => (string) Str::uuid(),
        ...$overrides,
    ]);
}

function sentTo(string $event, string $email): bool
{
    return Mail::sent(AnticipoNotificationMail::class, fn ($m) => $m->event === $event && $m->hasTo($email))->isNotEmpty();
}

test('flujo completo: radica, aprueban director y gerencia, contabilidad carga Multifox y pasa al cuadro de pagos', function () {
    radicarAnticipo()->assertCreated()->assertJsonPath('data.code', 'ANT-0001');
    $anticipo = Anticipo::first();

    // Carpeta del contrato y datos tomados del contrato
    expect($anticipo->storage_path)->toBe(CONTRATO_FOLDER . '/08 ANTICIPOS/ANT-0001')
        ->and($anticipo->file_path)->toStartWith(CONTRATO_FOLDER . '/08 ANTICIPOS/ANT-0001/')
        ->and($anticipo->project_id)->toBe($this->project->id)
        ->and($anticipo->tower_id)->toBe($this->contract->tower_id)
        ->and($anticipo->status)->toBe(AnticipoWorkflow::STATUS_PENDIENTE_DIRECTOR)
        ->and(sentTo(AnticipoNotificationMail::EVENT_SUBMITTED, 'director@example.com'))->toBeTrue()
        ->and(sentTo(AnticipoNotificationMail::EVENT_CONFIRMATION, 'residente@example.com'))->toBeTrue();

    // Gerencia no puede aprobar antes que el Director
    $this->actingAs($this->gerencia)->postJson("/api/v1/anticipos/{$anticipo->id}/review", ['decision' => 'aprobar'])->assertStatus(422);

    $this->actingAs($this->director)->postJson("/api/v1/anticipos/{$anticipo->id}/review", ['decision' => 'aprobar'])->assertOk();
    expect($anticipo->fresh()->status)->toBe(AnticipoWorkflow::STATUS_PENDIENTE_GERENCIA)
        ->and(sentTo(AnticipoNotificationMail::EVENT_PENDING_REVIEW, 'gerencia@example.com'))->toBeTrue();

    $this->actingAs($this->gerencia)->postJson("/api/v1/anticipos/{$anticipo->id}/review", ['decision' => 'aprobar', 'comment' => 'OK'])->assertOk();
    expect($anticipo->fresh()->status)->toBe(AnticipoWorkflow::STATUS_PENDIENTE_CONTABILIDAD)
        ->and(sentTo(AnticipoNotificationMail::EVENT_ACCOUNTING, 'contabilidad@example.com'))->toBeTrue()
        ->and(PagoCuenta::count())->toBe(0);

    // El residente no puede cargar el anticipo de Multifox
    $this->actingAs($this->residente)->postJson("/api/v1/anticipos/{$anticipo->id}/multifox", ['multifox_number' => 'AN-77', 'upload_token' => (string) Str::uuid()])
        ->assertForbidden();

    $this->actingAs($this->contabilidad)->postJson("/api/v1/anticipos/{$anticipo->id}/multifox", ['multifox_number' => 'AN-77', 'upload_token' => (string) Str::uuid()])
        ->assertOk()->assertJsonPath('data.status', AnticipoWorkflow::STATUS_COMPLETADO);

    $anticipo->refresh();
    $cuenta = PagoCuenta::first();

    expect($anticipo->multifox_number)->toBe('AN-77')
        ->and($anticipo->multifox_file_path)->toStartWith($anticipo->storage_path . '/')
        ->and($cuenta->source_type)->toBe(PagoCuenta::SOURCE_ANTICIPO)
        ->and($cuenta->anticipo_id)->toBe($anticipo->id)
        ->and((float) $cuenta->amount)->toBe(25000000.0)
        ->and($cuenta->supplier_name)->toBe('EMELECT GROUP SAS')
        ->and($cuenta->document_number)->toBe('C-C 45')
        ->and($cuenta->frente)->toBe('TORRE 2')
        ->and($cuenta->concept)->toContain('ANT-0001 · Contrato 2759')
        ->and(sentTo(AnticipoNotificationMail::EVENT_COMPLETED, 'residente@example.com'))->toBeTrue();

    // En el cuadro de pagos se ve como anticipo
    $this->contabilidad->givePermissionTo('pagos.ver');
    $this->actingAs($this->contabilidad)->getJson("/api/v1/pagos/{$cuenta->id}")
        ->assertOk()->assertJsonPath('data.source_label', 'Anticipo')->assertJsonPath('data.source_code', 'ANT-0001');
});

test('devuelto por gerencia vuelve al residente, que lo corrige y empieza de nuevo con el director', function () {
    radicarAnticipo()->assertCreated();
    $anticipo = Anticipo::first();

    $this->actingAs($this->director)->postJson("/api/v1/anticipos/{$anticipo->id}/review", ['decision' => 'aprobar'])->assertOk();
    $this->actingAs($this->gerencia)->postJson("/api/v1/anticipos/{$anticipo->id}/review", ['decision' => 'devolver'])
        ->assertStatus(422)->assertJsonValidationErrors('comment');
    $this->actingAs($this->gerencia)->postJson("/api/v1/anticipos/{$anticipo->id}/review", ['decision' => 'devolver', 'comment' => 'El valor supera el 30%'])
        ->assertOk();

    expect($anticipo->fresh()->status)->toBe(AnticipoWorkflow::STATUS_DEVUELTO)
        ->and(sentTo(AnticipoNotificationMail::EVENT_RETURNED, 'residente@example.com'))->toBeTrue();

    $this->actingAs($this->residente)->postJson("/api/v1/anticipos/{$anticipo->id}/resubmit", [
        'contract_id' => $this->contract->id, 'contractor_id' => $this->contractor->id, 'document_number' => 'C-C 45', 'amount' => 20000000,
    ])->assertOk();

    $anticipo->refresh();
    expect($anticipo->status)->toBe(AnticipoWorkflow::STATUS_PENDIENTE_DIRECTOR)
        ->and((float) $anticipo->amount)->toBe(20000000.0)
        ->and(sentTo(AnticipoNotificationMail::EVENT_RESUBMITTED, 'director@example.com'))->toBeTrue();
});

test('el residente sin acceso al proyecto del contrato no puede radicar', function () {
    $this->residente->revokePermissionTo('proyectos.ver_todos');

    radicarAnticipo()->assertStatus(422)->assertJsonPath('message', 'No tienes acceso al contrato seleccionado.');
    expect(Anticipo::count())->toBe(0);
});

test('el correo se renderiza con los datos del anticipo', function () {
    radicarAnticipo()->assertCreated();

    $html = (new AnticipoNotificationMail(Anticipo::first(), AnticipoNotificationMail::EVENT_SUBMITTED, AnticipoWorkflow::STAGE_DIRECTOR))->render();

    expect($html)->toContain('ANT-0001 · Contrato 2759')
        ->toContain('EMELECT GROUP SAS')
        ->toContain('$25.000.000')
        ->toContain('Anticipo Multifox');
});
