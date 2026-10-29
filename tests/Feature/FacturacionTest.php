<?php

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Cortes\Models\CorteInvoice;
use App\Http\Modules\Cortes\Service\CorteWorkflow;
use App\Http\Modules\Facturacion\Models\FacturacionInvoice;
use App\Http\Modules\Facturacion\Service\FacturacionStorageService;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Ordenes\Models\Orden;
use App\Http\Modules\Ordenes\Service\OrdenWorkflow;
use App\Http\Modules\Users\Models\User;
use App\Services\DirectUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->travelTo(now()->setDate(2026, 10, 29)->setTime(9, 0));

    // S3 simulado: cada token "sube" un archivo a la carpeta que le corresponde; se registran los archivos eliminados
    $this->deleted = [];
    $this->mock(FacturacionStorageService::class, function ($mock) {
        $mock->shouldReceive('claimInvoiceFile')->andReturnUsing(fn ($token, $user, $period) => [
            'file_name' => "{$token}.pdf",
            'file_path' => 'contabilidad/' . FacturacionStorageService::monthLabel($period) . "/{$token}.pdf",
            'file_size' => 1000,
            'file_mime' => 'application/pdf',
        ]);
        $mock->shouldReceive('claimSupportFile')->andReturnUsing(fn ($token) => [
            'file_name' => "soporte-{$token}.png",
            'file_path' => "contabilidad/OCTUBRE 2026/Soportes/x/soporte-{$token}.png",
            'file_size' => 500,
            'file_mime' => 'image/png',
        ]);
        $mock->shouldReceive('delete')->andReturnUsing(function ($path) {
            $this->deleted[] = $path;
        });
        $mock->shouldReceive('monthFolder')->andReturn('contabilidad/OCTUBRE 2026');
    });

    // Copia de la factura a la carpeta del corte / orden al asociarla
    $this->mock(DirectUploadService::class, function ($mock) {
        $mock->shouldReceive('copyTo')->andReturnUsing(fn ($path, $directory) => "{$directory}/" . basename($path));
        $mock->shouldReceive('cleanSegment')->andReturnUsing(fn ($value) => trim($value));
    });

    $this->project = Project::create(['code' => 'vis', 'name' => 'San Miguel', 'is_active' => true]);
    $this->contractor = FtraContractor::create(['name' => 'INVALGO SAS', 'nit' => '900']);

    $this->contabilidad = makeUser();
    $this->contabilidad->givePermissionTo(['cortes.facturar', 'cortes.ver', 'proyectos.ver_todos']);

    $this->residente = makeUser();
    $this->residente->givePermissionTo(['cortes.ver', 'cortes.crear', 'ordenes.ver', 'ordenes.crear']);
    $this->residente->projects()->attach($this->project->id);

    $this->otroResidente = makeUser();
    $this->otroResidente->givePermissionTo(['cortes.ver', 'cortes.crear']);
});

function makeUser(): User
{
    return User::create(['name' => fake()->name(), 'email' => fake()->unique()->safeEmail(), 'password' => 'secret']);
}

function makeCorte(User $owner, string $status = CorteWorkflow::STATUS_APROBADO, ?int $contractorId = null): Corte
{
    return Corte::create([
        'code' => 'C-' . fake()->unique()->numberBetween(1000, 9999),
        'project_id' => test()->project->id,
        'contractor_id' => $contractorId ?? test()->contractor->id,
        'status' => $status,
        'file_name' => 'corte.pdf',
        'file_path' => 'tecnica/corte.pdf',
        'created_by' => $owner->id,
        'approved_at' => $status === CorteWorkflow::STATUS_APROBADO ? now()->subDays(5) : null,
    ]);
}

function uploadInvoice(array $overrides = []): FacturacionInvoice
{
    test()->actingAs(test()->contabilidad)->postJson('/api/v1/facturacion', [
        'period' => '2026-10',
        'document_type' => 'factura',
        'invoice_number' => '335',
        'contractor_id' => test()->contractor->id,
        'amount' => 1500000,
        'upload_token' => (string) Str::uuid(),
        ...$overrides,
    ])->assertCreated();

    return FacturacionInvoice::latest('id')->first();
}

function supports(): array
{
    return ['support_tokens' => [(string) Str::uuid(), (string) Str::uuid()]];
}

test('contabilidad carga la factura en la carpeta del mes y queda pendiente de obra', function () {
    $invoice = uploadInvoice();

    expect($invoice->status)->toBe(FacturacionInvoice::STATUS_PENDIENTE)
        ->and($invoice->file_path)->toStartWith('contabilidad/OCTUBRE 2026/')
        ->and($invoice->documentNumber())->toBe('FE 335')
        ->and($invoice->supplier_name)->toBe('INVALGO SAS');

    // El residente no puede cargar facturas
    $this->actingAs($this->residente)->postJson('/api/v1/facturacion', [
        'period' => '2026-10', 'document_type' => 'factura', 'invoice_number' => '1', 'supplier_name' => 'X', 'upload_token' => (string) Str::uuid(),
    ])->assertForbidden();
});

test('el residente asocia la factura a su corte aprobado con soportes', function () {
    $corte = makeCorte($this->residente);
    $invoice = uploadInvoice();

    // La ve como sugerida porque su corte es de ese proveedor
    $this->actingAs($this->residente)->getJson('/api/v1/facturacion?scope=pending')
        ->assertOk()
        ->assertJsonPath('data.data.0.suggested', true)
        ->assertJsonPath('counts.suggested', 1);

    $this->actingAs($this->residente)->getJson('/api/v1/facturacion/targets')
        ->assertOk()->assertJsonPath('cortes', null)->assertJsonPath('data.cortes.0.id', $corte->id);

    $this->actingAs($this->residente)
        ->postJson("/api/v1/facturacion/{$invoice->id}/associate", ['target_type' => 'corte', 'target_id' => $corte->id, 'comment' => 'Corte 6634 Multifox', ...supports()])
        ->assertOk()
        ->assertJsonPath('data.status', FacturacionInvoice::STATUS_PROCESADA)
        ->assertJsonPath('data.target.label', "Corte {$corte->code}");

    $invoice->refresh();
    $corteInvoice = CorteInvoice::first();

    expect($corte->fresh()->status)->toBe(CorteWorkflow::STATUS_FACTURADO)
        ->and($corteInvoice->invoice_number)->toBe('FE 335')
        ->and($corteInvoice->document_type)->toBe(CorteInvoice::TYPE_FACTURA_INICIAL)
        ->and((float) $corteInvoice->amount)->toBe(1500000.0)
        // La factura sigue en la carpeta del mes y queda copiada dentro de la carpeta del corte
        ->and($invoice->file_path)->toStartWith('contabilidad/OCTUBRE 2026/')
        ->and($corteInvoice->file_path)->toBe($corte->fresh()->storage_path . '/' . basename($invoice->file_path))
        ->and($invoice->corte_invoice_id)->toBe($corteInvoice->id)
        ->and($invoice->processed_by)->toBe($this->residente->id)
        ->and($invoice->supports()->count())->toBe(2)
        ->and($corte->history()->where('action', 'factura')->exists())->toBeTrue();

    // No se puede volver a asociar ni eliminar
    $this->actingAs($this->residente)
        ->postJson("/api/v1/facturacion/{$invoice->id}/associate", ['target_type' => 'corte', 'target_id' => $corte->id, ...supports()])
        ->assertStatus(422);
    $this->actingAs($this->contabilidad)->deleteJson("/api/v1/facturacion/{$invoice->id}")->assertStatus(422);
});

test('solo el responsable del corte puede asociar y solo con las tres aprobaciones', function () {
    $ajeno = makeCorte($this->otroResidente);
    $enRevision = makeCorte($this->residente, CorteWorkflow::STATUS_PENDIENTE_DIRECTOR);
    $invoice = uploadInvoice();

    $this->actingAs($this->residente)
        ->postJson("/api/v1/facturacion/{$invoice->id}/associate", ['target_type' => 'corte', 'target_id' => $ajeno->id, ...supports()])
        ->assertStatus(422)->assertJsonPath('message', 'Solo el residente responsable puede asociar facturas a su corte.');

    $this->actingAs($this->residente)
        ->postJson("/api/v1/facturacion/{$invoice->id}/associate", ['target_type' => 'corte', 'target_id' => $enRevision->id, ...supports()])
        ->assertStatus(422);

    // Sin soportes no se puede asociar
    $this->actingAs($this->residente)
        ->postJson("/api/v1/facturacion/{$invoice->id}/associate", ['target_type' => 'corte', 'target_id' => makeCorte($this->residente)->id])
        ->assertStatus(422)->assertJsonValidationErrors('support_tokens');

    expect($invoice->fresh()->status)->toBe(FacturacionInvoice::STATUS_PENDIENTE);
});

test('si contabilidad no registró el valor, el residente lo indica al asociar', function () {
    $corte = makeCorte($this->residente);
    $invoice = uploadInvoice(['amount' => null]);

    $this->actingAs($this->residente)
        ->postJson("/api/v1/facturacion/{$invoice->id}/associate", ['target_type' => 'corte', 'target_id' => $corte->id, ...supports()])
        ->assertStatus(422);

    $this->actingAs($this->residente)
        ->postJson("/api/v1/facturacion/{$invoice->id}/associate", ['target_type' => 'corte', 'target_id' => $corte->id, 'amount' => 800000, ...supports()])
        ->assertOk();

    expect((float) $invoice->fresh()->amount)->toBe(800000.0);
});

test('el residente asocia la factura a su orden pendiente de factura y la orden avanza a la entrada', function () {
    $orden = Orden::create([
        'code' => 'ORD-0001',
        'description' => 'Cemento',
        'project_id' => $this->project->id,
        'chapter' => 'Estructura',
        'activity' => 'Concreto',
        'status' => OrdenWorkflow::STATUS_PENDIENTE_FACTURA,
        'current_step' => OrdenWorkflow::STEP_FACTURA,
        'storage_path' => 'tecnica/Ordenes/ORD-0001',
        'created_by' => $this->residente->id,
        'approved_at' => now()->subDays(3),
    ]);
    $invoice = uploadInvoice(['invoice_number' => '485126']);

    $this->actingAs($this->residente)
        ->postJson("/api/v1/facturacion/{$invoice->id}/associate", ['target_type' => 'orden', 'target_id' => $orden->id, ...supports()])
        ->assertOk();

    $orden->refresh();
    $document = $orden->documents()->first();

    expect($orden->status)->toBe(OrdenWorkflow::STATUS_PENDIENTE_ENTRADA)
        ->and($document->number)->toBe('FE 485126')
        ->and($document->contractor_id)->toBe($this->contractor->id)
        ->and($document->file_path)->toStartWith('tecnica/Ordenes/ORD-0001/02 Factura proveedor/')
        ->and($invoice->fresh()->orden_document_id)->toBe($document->id);
});

test('el panel muestra a quién le falta factura y lo pendiente por proveedor', function () {
    makeCorte($this->residente);
    makeCorte($this->residente);
    makeCorte($this->otroResidente);
    $corte = makeCorte($this->residente);
    $processed = uploadInvoice();
    uploadInvoice(['invoice_number' => '336', 'contractor_id' => null, 'supplier_name' => 'NOTARIA 1', 'amount' => 100]);

    $this->actingAs($this->residente)
        ->postJson("/api/v1/facturacion/{$processed->id}/associate", ['target_type' => 'corte', 'target_id' => $corte->id, ...supports()])
        ->assertOk();

    $panel = $this->actingAs($this->contabilidad)->getJson('/api/v1/facturacion/panel?period=2026-10')->assertOk()->json('data');

    expect($panel['period_label'])->toBe('OCTUBRE 2026')
        ->and($panel['summary'])->toMatchArray(['total' => 2, 'processed' => 1, 'pending' => 1, 'cortes_waiting' => 3])
        ->and($panel['pending_by_supplier'][0]['supplier'])->toBe('NOTARIA 1')
        ->and($panel['residentes'][0]['id'])->toBe($this->residente->id)
        ->and($panel['residentes'][0]['cortes'])->toHaveCount(2)
        ->and($panel['residentes'][0]['cortes'][0]['days_waiting'])->toBe(5)
        ->and($panel['residentes'][0]['processed_in_period'])->toBe(1);

    // El panel es solo para Contabilidad
    $this->actingAs($this->residente)->getJson('/api/v1/facturacion/panel')->assertForbidden();
});

test('contabilidad corrige o elimina una factura pendiente', function () {
    $invoice = uploadInvoice();

    $this->actingAs($this->contabilidad)->putJson("/api/v1/facturacion/{$invoice->id}", [
        'document_type' => 'cuenta_cobro', 'invoice_number' => '15102026', 'contractor_id' => $this->contractor->id,
    ])->assertOk()->assertJsonPath('data.document_number', 'C-C 15102026');

    $this->actingAs($this->residente)->deleteJson("/api/v1/facturacion/{$invoice->id}")->assertForbidden();
    $this->actingAs($this->contabilidad)->deleteJson("/api/v1/facturacion/{$invoice->id}")->assertOk();

    expect(FacturacionInvoice::count())->toBe(0);
});

test('si la asociación falla, la copia en la carpeta del corte se elimina', function () {
    $first = makeCorte($this->residente);
    $second = makeCorte($this->residente);
    $original = uploadInvoice();
    $this->actingAs($this->residente)
        ->postJson("/api/v1/facturacion/{$original->id}/associate", ['target_type' => 'corte', 'target_id' => $first->id, ...supports()])
        ->assertOk();

    // Mismo número "FE 335" de otro proveedor: el corte lo rechaza después de copiar el archivo
    $duplicated = uploadInvoice(['contractor_id' => null, 'supplier_name' => 'OTRO SAS']);
    $this->actingAs($this->residente)
        ->postJson("/api/v1/facturacion/{$duplicated->id}/associate", ['target_type' => 'corte', 'target_id' => $second->id, ...supports()])
        ->assertStatus(422);

    expect($duplicated->fresh()->status)->toBe(FacturacionInvoice::STATUS_PENDIENTE)
        ->and($this->deleted)->toBe([$second->fresh()->storage_path . '/' . basename($duplicated->file_path)]);
});
