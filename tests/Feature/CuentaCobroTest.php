<?php

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\CuentasCobro\Mail\CuentaCobroNotificationMail;
use App\Http\Modules\CuentasCobro\Models\CuentaCobro;
use App\Http\Modules\CuentasCobro\Models\CuentaCobroTipo;
use App\Http\Modules\CuentasCobro\Service\CuentaCobroWorkflow;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Pagos\Models\PagoCuenta;
use App\Http\Modules\Users\Models\User;
use App\Services\DirectUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    config([
        'cuentas_cobro.stage_emails' => ['director_obra' => ['director@example.com'], 'gerencia' => ['gerencia@example.com']],
        'cuentas_cobro.monitor' => [],
        'cortes.multifox_emails' => ['paula@example.com'],
        'cortes.multifox_allowed_domains' => [],
    ]);

    // S3 simulado: el archivo queda en la carpeta que se pide
    $this->mock(DirectUploadService::class, function ($mock) {
        $mock->shouldReceive('claimUpload')->andReturnUsing(fn ($token, $user, $directory) => [
            'file_name' => "{$token}.pdf",
            'file_path' => "{$directory}/{$token}.pdf",
            'file_size' => 1000,
            'file_mime' => 'application/pdf',
        ]);
        $mock->shouldReceive('cleanSegment')->andReturnUsing(fn ($value) => trim($value));
    });

    $this->project = Project::create(['code' => 'vis', 'name' => 'San Miguel', 'is_active' => true]);
    $this->contractor = FtraContractor::create(['name' => 'SEGURIDAD TOTAL SAS', 'nit' => '902']);
    $this->sst = CuentaCobroTipo::where('name', 'SST')->firstOrFail();
    $this->general = CuentaCobroTipo::where('name', 'Servicios generales')->firstOrFail();

    Permission::firstOrCreate(['name' => 'cortes.multifox', 'guard_name' => 'web']);
    $user = fn (string $email, array $permissions) => tap(
        User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => 'secret']),
        fn (User $u) => $u->givePermissionTo([...$permissions, 'cuentas_cobro.ver', 'proyectos.ver_todos'])
    );
    $this->residente = $user('residente@example.com', ['cuentas_cobro.crear']);
    $this->director = $user('director@example.com', ['cuentas_cobro.aprobar_director']);
    $this->gerencia = $user('gerencia@example.com', ['cuentas_cobro.aprobar_gerencia']);
    $this->paula = $user('paula@example.com', ['cortes.multifox']);
    $this->admin = $user('admin@example.com', ['cuentas_cobro.parametrizar']);
});

function radicarCuenta(array $overrides = [])
{
    return test()->actingAs(test()->residente)->postJson('/api/v1/cuentas-cobro', [
        'tipo_id' => test()->sst->id,
        'project_id' => test()->project->id,
        'contractor_id' => test()->contractor->id,
        'document_number' => 'C-C 12',
        'amount' => 3500000,
        'description' => 'SST octubre',
        'upload_token' => (string) Str::uuid(),
        'attachment_tokens' => [(string) Str::uuid()],
        ...$overrides,
    ]);
}

function aprobarCuenta(CuentaCobro $cuenta): void
{
    test()->actingAs(test()->director)->postJson("/api/v1/cuentas-cobro/{$cuenta->id}/review", ['decision' => 'aprobar'])->assertOk();
    test()->actingAs(test()->gerencia)->postJson("/api/v1/cuentas-cobro/{$cuenta->id}/review", ['decision' => 'aprobar'])->assertOk();
}

function cuentaMailTo(string $event, string $email): bool
{
    return Mail::sent(CuentaCobroNotificationMail::class, fn ($m) => $m->event === $event && $m->hasTo($email))->isNotEmpty();
}

test('SST exige adjunto; un tipo sin adjunto obligatorio se radica sin él', function () {
    radicarCuenta(['attachment_tokens' => []])->assertStatus(422)
        ->assertJsonPath('message', 'Las cuentas de cobro de SST requieren adjunto: Informe SST y soportes de seguridad social.');

    radicarCuenta()->assertCreated()->assertJsonPath('data.code', 'CC-0001')->assertJsonCount(1, 'data.adjuntos');
    radicarCuenta(['tipo_id' => $this->general->id, 'attachment_tokens' => []])->assertCreated();

    expect(CuentaCobro::first()->storage_path)->toBe('tecnica/Cuentas de Cobro/San Miguel/SST/CC-0001')
        ->and(cuentaMailTo(CuentaCobroNotificationMail::EVENT_SUBMITTED, 'director@example.com'))->toBeTrue();
});

test('aprobada, Paula decide que no requiere Multifox y pasa de una vez al cuadro de pagos', function () {
    radicarCuenta()->assertCreated();
    $cuenta = CuentaCobro::first();
    aprobarCuenta($cuenta);

    expect($cuenta->fresh()->status)->toBe(CuentaCobroWorkflow::STATUS_PENDIENTE_REVISION)
        ->and(cuentaMailTo(CuentaCobroNotificationMail::EVENT_MULTIFOX_REVIEW, 'paula@example.com'))->toBeTrue()
        ->and(PagoCuenta::count())->toBe(0);

    // Solo Paula decide
    $this->actingAs($this->residente)->postJson("/api/v1/cuentas-cobro/{$cuenta->id}/multifox-decision", ['requires_multifox' => false])->assertStatus(422);

    $this->actingAs($this->paula)->postJson("/api/v1/cuentas-cobro/{$cuenta->id}/multifox-decision", ['requires_multifox' => false])
        ->assertOk()->assertJsonPath('data.status', CuentaCobroWorkflow::STATUS_EN_PAGOS);

    $pago = PagoCuenta::first();
    expect($cuenta->fresh()->requires_multifox)->toBeFalse()
        ->and($pago->source_type)->toBe(PagoCuenta::SOURCE_CUENTA_COBRO)
        ->and($pago->cuenta_cobro_id)->toBe($cuenta->id)
        ->and((float) $pago->amount)->toBe(3500000.0)
        ->and($pago->concept)->toContain('CC-0001 · SST')
        ->and(cuentaMailTo(CuentaCobroNotificationMail::EVENT_IN_PAYMENTS, 'residente@example.com'))->toBeTrue();
});

test('si requiere Multifox no pasa al cuadro de pagos hasta que se carga', function () {
    radicarCuenta()->assertCreated();
    $cuenta = CuentaCobro::first();
    aprobarCuenta($cuenta);

    $this->actingAs($this->paula)->postJson("/api/v1/cuentas-cobro/{$cuenta->id}/multifox-decision", ['requires_multifox' => true])
        ->assertOk()->assertJsonPath('data.status', CuentaCobroWorkflow::STATUS_PENDIENTE_MULTIFOX);
    expect(PagoCuenta::count())->toBe(0);

    $this->actingAs($this->paula)->postJson("/api/v1/cuentas-cobro/{$cuenta->id}/multifox", ['multifox_number' => 'MF-900', 'upload_token' => (string) Str::uuid()])
        ->assertOk()->assertJsonPath('data.status', CuentaCobroWorkflow::STATUS_EN_PAGOS);

    $cuenta->refresh();
    expect($cuenta->multifox_number)->toBe('MF-900')
        ->and($cuenta->multifox_file_path)->toStartWith($cuenta->storage_path . '/')
        ->and(PagoCuenta::count())->toBe(1);

    // No se vuelve a cargar
    $this->actingAs($this->paula)->postJson("/api/v1/cuentas-cobro/{$cuenta->id}/multifox", ['multifox_number' => 'MF-901'])->assertStatus(422);
});

test('Paula puede cargar en Multifox directamente desde la revisión, sin soporte', function () {
    radicarCuenta()->assertCreated();
    $cuenta = CuentaCobro::first();
    aprobarCuenta($cuenta);

    $this->actingAs($this->paula)->postJson("/api/v1/cuentas-cobro/{$cuenta->id}/multifox", ['multifox_number' => 'MF-1'])->assertOk();

    expect($cuenta->fresh()->requires_multifox)->toBeTrue()
        ->and($cuenta->fresh()->multifox_file_path)->toBeNull()
        ->and(PagoCuenta::count())->toBe(1);
});

test('devuelta y reenviada: los adjuntos anteriores cuentan para el tipo que los exige', function () {
    radicarCuenta()->assertCreated();
    $cuenta = CuentaCobro::first();

    $this->actingAs($this->director)->postJson("/api/v1/cuentas-cobro/{$cuenta->id}/review", ['decision' => 'devolver', 'comment' => 'Falta la planilla'])->assertOk();
    expect(cuentaMailTo(CuentaCobroNotificationMail::EVENT_RETURNED, 'residente@example.com'))->toBeTrue();

    $this->actingAs($this->residente)->postJson("/api/v1/cuentas-cobro/{$cuenta->id}/resubmit", [
        'tipo_id' => $this->sst->id, 'project_id' => $this->project->id, 'contractor_id' => $this->contractor->id,
        'document_number' => 'C-C 12', 'amount' => 3500000, 'attachment_tokens' => [(string) Str::uuid()],
    ])->assertOk()->assertJsonCount(2, 'data.adjuntos')->assertJsonPath('data.status', CuentaCobroWorkflow::STATUS_PENDIENTE_DIRECTOR);
});

test('CRUD de tipos: crear, editar y eliminar; con cuentas se desactiva', function () {
    $this->actingAs($this->residente)->getJson('/api/v1/cuentas-cobro/tipos')->assertForbidden();

    $id = $this->actingAs($this->admin)->postJson('/api/v1/cuentas-cobro/tipos', [
        'name' => 'Topografía', 'requires_attachment' => true, 'attachment_hint' => 'Informe topográfico',
    ])->assertCreated()->json('data.id');

    $this->actingAs($this->admin)->putJson("/api/v1/cuentas-cobro/tipos/{$id}", ['name' => 'Topografía', 'requires_attachment' => false])
        ->assertOk()->assertJsonPath('data.requires_attachment', false)->assertJsonPath('data.attachment_hint', null);
    $this->actingAs($this->admin)->postJson('/api/v1/cuentas-cobro/tipos', ['name' => 'topografía ', 'requires_attachment' => false])->assertStatus(422);

    $this->actingAs($this->admin)->deleteJson("/api/v1/cuentas-cobro/tipos/{$id}")->assertOk()->assertJsonPath('deleted', true);

    radicarCuenta()->assertCreated();
    $this->actingAs($this->admin)->deleteJson("/api/v1/cuentas-cobro/tipos/{$this->sst->id}")->assertOk()->assertJsonPath('deleted', false);
    expect($this->sst->fresh()->is_active)->toBeFalse();

    // Un tipo inactivo ya no se puede usar
    radicarCuenta()->assertStatus(422);
});

test('el correo se renderiza con los datos de la cuenta de cobro', function () {
    radicarCuenta()->assertCreated();

    $html = (new CuentaCobroNotificationMail(CuentaCobro::first(), CuentaCobroNotificationMail::EVENT_MULTIFOX_REVIEW, CuentaCobroWorkflow::STEP_MULTIFOX))->render();

    expect($html)->toContain('CC-0001 · SST')->toContain('SEGURIDAD TOTAL SAS')->toContain('$3.500.000')->toContain('¿Requiere Multifox?');
});
