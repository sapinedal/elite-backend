<?php

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Configuracion\Models\Tower;
use App\Http\Modules\Ftra\Mail\FtraNotificationMail;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Ftra\Models\FtraFormat;
use App\Http\Modules\Ftra\Models\FtraRecord;
use App\Http\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

const FTRA_FIRMA = 'data:image/png;base64,iVBORw0KGgo=';

beforeEach(function () {
    Mail::fake();
    config([
        'ftra.stage_emails' => ['supervisor' => ['supervisor.externo@example.com'], 'director_obra' => []],
        'ftra.monitor' => [],
    ]);
    foreach (['ftra.ver', 'ftra.crear', 'ftra.editar', 'ftra.revisar', 'ftra.aprobar'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $this->project = Project::create(['code' => 'vis', 'name' => 'San Miguel', 'is_active' => true]);
    $this->tower = Tower::create(['project_id' => $this->project->id, 'name' => 'TORRE 2', 'floors' => 20, 'apartments_per_floor' => 10]);
    $this->contractor = FtraContractor::create(['name' => 'EMELECT GROUP SAS']);
    $this->format = FtraFormat::create(['name' => 'Recibo de acabados', 'code' => 'FT-01', 'version' => '1']);

    $user = function (string $email, array $permissions) {
        $u = User::create(['name' => $email, 'email' => $email, 'password' => 'secret']);
        $u->givePermissionTo($permissions);
        $u->projects()->attach($this->project);

        return $u;
    };
    $this->residente = $user('residente@inverconstruccion.com', ['ftra.ver', 'ftra.crear']);
    $this->supervisor = $user('supervisor@inverconstruccion.com', ['ftra.ver', 'ftra.revisar']);
    $this->director = $user('director@inverconstruccion.com', ['ftra.ver', 'ftra.aprobar']);

    // Técnica tiene ftra.revisar y ftra.aprobar por rol: puede actuar, pero no recibe los avisos
    $this->tecnica = User::create(['name' => 'Técnica', 'email' => 'tecnica@inverconstruccion.com', 'password' => 'secret']);
    $this->tecnica->assignRole(Role::findOrCreate('tecnica-test', 'web')->givePermissionTo(['ftra.ver', 'ftra.revisar', 'ftra.aprobar']));
    $this->tecnica->projects()->attach($this->project);
});

function registrarFtra(): FtraRecord
{
    test()->actingAs(test()->residente)
        ->withHeader('X-Project-Id', (string) test()->project->id)
        ->postJson('/api/v1/ftra/records', [
            'contractor_id' => test()->contractor->id,
            'format_id' => test()->format->id,
            'tower_id' => test()->tower->id,
            'piso' => 12,
            'apartamento' => '1203',
            'resultado_inspeccion' => 'Recibido a satisfacción',
            'orden_aseo' => 'Aprobado',
        ])
        ->assertCreated()
        ->assertJsonPath('record.status', 'Registrada');

    return FtraRecord::latest('id')->first();
}

function ftraMailsTo(string $event): array
{
    return Mail::sent(FtraNotificationMail::class, fn ($m) => $m->event === $event)
        ->flatMap(fn ($m) => collect($m->to)->pluck('address'))
        ->sort()->values()->all();
}

it('registrar avisa al supervisor (configurado y con permiso directo), no a quien lo tiene por rol', function () {
    registrarFtra();

    expect(ftraMailsTo(FtraNotificationMail::EVENT_SUBMITTED))
        ->toBe(['supervisor.externo@example.com', 'supervisor@inverconstruccion.com']);
});

it('recorre el flujo completo: Supervisor y Director lo reciben a satisfacción', function () {
    $record = registrarFtra();

    // El director no puede saltarse al supervisor
    $this->actingAs($this->director)->postJson("/api/v1/ftra/records/{$record->id}/approve", ['signature' => FTRA_FIRMA])->assertForbidden();

    $this->actingAs($this->supervisor)
        ->postJson("/api/v1/ftra/records/{$record->id}/approve", ['signature' => FTRA_FIRMA, 'comment' => 'Sin pendientes'])
        ->assertOk()
        ->assertJsonPath('record.status', 'Seguimiento')
        ->assertJsonPath('record.current_stage', 'director_obra');

    expect(ftraMailsTo(FtraNotificationMail::EVENT_PENDING_REVIEW))->toBe(['director@inverconstruccion.com'])
        ->and(ftraMailsTo(FtraNotificationMail::EVENT_ADVANCED))->toBe(['residente@inverconstruccion.com']);

    $this->actingAs($this->director)
        ->postJson("/api/v1/ftra/records/{$record->id}/approve", ['signature' => FTRA_FIRMA])
        ->assertOk()
        ->assertJsonPath('record.status', 'Aprobada');

    expect(ftraMailsTo(FtraNotificationMail::EVENT_APPROVED))
        ->toBe(['residente@inverconstruccion.com', 'supervisor.externo@example.com', 'supervisor@inverconstruccion.com']);

    $record->refresh();
    expect($record->supervisor_user_id)->toBe($this->supervisor->id)
        ->and($record->director_user_id)->toBe($this->director->id)
        ->and($record->history()->pluck('action')->all())->toBe(['registrado', 'aprobado', 'aprobado']);

    // Terminado: ya no se puede revisar ni editar
    $this->actingAs($this->director)->postJson("/api/v1/ftra/records/{$record->id}/approve", ['signature' => FTRA_FIRMA])->assertStatus(422);
});

it('devolución del director: avisa al residente y al supervisor; el reenvío reinicia la revisión', function () {
    $record = registrarFtra();
    $this->actingAs($this->supervisor)->postJson("/api/v1/ftra/records/{$record->id}/approve", ['signature' => FTRA_FIRMA])->assertOk();

    $this->actingAs($this->director)->postJson("/api/v1/ftra/records/{$record->id}/return", [])->assertStatus(422);
    $this->actingAs($this->director)
        ->postJson("/api/v1/ftra/records/{$record->id}/return", ['comment' => 'Falta foto del baño auxiliar'])
        ->assertOk()
        ->assertJsonPath('record.status', 'Devuelta')
        ->assertJsonPath('record.can.review', false);

    expect(ftraMailsTo(FtraNotificationMail::EVENT_RETURNED))
        ->toBe(['residente@inverconstruccion.com', 'supervisor.externo@example.com', 'supervisor@inverconstruccion.com']);

    // Otro usuario sin ftra.editar no puede reenviarlo
    $this->actingAs($this->supervisor)->postJson("/api/v1/ftra/records/{$record->id}/resubmit", [])->assertForbidden();

    $this->actingAs($this->residente)
        ->postJson("/api/v1/ftra/records/{$record->id}/resubmit", ['comment' => 'Se agregó la foto', 'observations' => 'Corregido'])
        ->assertOk()
        ->assertJsonPath('record.status', 'Registrada');

    $record->refresh();
    expect($record->supervisor_signature)->toBeNull()
        ->and($record->supervisor_user_id)->toBeNull()
        ->and($record->observations)->toBe('Corregido')
        ->and(ftraMailsTo(FtraNotificationMail::EVENT_RESUBMITTED))->toBe(['supervisor.externo@example.com', 'supervisor@inverconstruccion.com']);
});

it('el semáforo devuelve el último FTRA de cada formato por apartamento', function () {
    $otro = FtraFormat::create(['name' => 'Recibo de carpintería', 'code' => 'FT-02', 'version' => '1']);
    $base = ['project_id' => $this->project->id, 'tower_id' => $this->tower->id, 'contractor_id' => $this->contractor->id, 'registered_by_id' => $this->residente->id, 'piso' => '12', 'apartamento' => '1203'];
    FtraRecord::create([...$base, 'format_id' => $this->format->id, 'status' => 'Devuelta']);
    FtraRecord::create([...$base, 'format_id' => $this->format->id, 'status' => 'Aprobada']);
    FtraRecord::create([...$base, 'format_id' => $otro->id, 'status' => 'Registrada']);

    $this->actingAs($this->residente)
        ->withHeader('X-Project-Id', (string) $this->project->id)
        ->getJson("/api/v1/ftra/towers/{$this->tower->id}/units")
        ->assertOk()
        ->assertJsonPath('data.0.records_count', 3)
        ->assertJsonCount(2, 'data.0.formats')
        ->assertJsonPath('data.0.formats.0.status', 'Aprobada')
        ->assertJsonPath('data.0.formats.1.status', 'Registrada');
});

it('el correo se puede renderizar para cada evento', function () {
    $record = registrarFtra();
    foreach ([FtraNotificationMail::EVENT_SUBMITTED, FtraNotificationMail::EVENT_RESUBMITTED, FtraNotificationMail::EVENT_PENDING_REVIEW, FtraNotificationMail::EVENT_ADVANCED, FtraNotificationMail::EVENT_RETURNED, FtraNotificationMail::EVENT_APPROVED] as $event) {
        $stage = in_array($event, [FtraNotificationMail::EVENT_ADVANCED, FtraNotificationMail::EVENT_RETURNED], true) ? 'supervisor' : 'director_obra';
        expect((new FtraNotificationMail($record, $event, $stage, 'Comentario'))->render())->toContain('Apto 1203');
    }
});

it('el listado trae las acciones de cada fila', function () {
    $record = registrarFtra();

    $this->actingAs($this->supervisor)
        ->withHeader('X-Project-Id', (string) $this->project->id)
        ->getJson('/api/v1/ftra/records')
        ->assertOk()
        ->assertJsonPath('data.0.id', $record->id)
        ->assertJsonPath('data.0.can.review', true);

    $this->actingAs($this->director)
        ->withHeader('X-Project-Id', (string) $this->project->id)
        ->getJson('/api/v1/ftra/records')
        ->assertJsonPath('data.0.can.review', false);
});

it('descarga el informe de Seguimiento en Excel con registros y matriz', function () {
    $record = registrarFtra();

    $response = $this->actingAs($this->residente)
        ->withHeader('X-Project-Id', (string) $this->project->id)
        ->get('/api/v1/ftra/records/export?status=Registrada');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');

    $path = tempnam(sys_get_temp_dir(), 'ftra') . '.xlsx';
    file_put_contents($path, $response->streamedContent());
    $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);

    expect($book->getSheetNames())->toBe(['Registros', 'Matriz'])
        ->and($book->getSheet(0)->getCell('D5')->getValue())->toBe('1203')
        ->and($book->getSheet(0)->getCell('J5')->getValue())->toBe('Pendiente del Supervisor');

    // Matriz: piso 12, apto 1203 contra el formato FT-01
    $matrix = $book->getSheet(1);
    $found = false;
    foreach ($matrix->getRowIterator() as $row) {
        $r = $row->getRowIndex();
        if ($matrix->getCell("B{$r}")->getValue() === '1203') {
            expect($matrix->getCell("C{$r}")->getValue())->toBe('Pend. Supervisor');
            $found = true;
        }
    }
    expect($found)->toBeTrue();
    @unlink($path);
});
