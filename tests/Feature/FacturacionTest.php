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
use Spatie\Permission\Models\Permission;

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

test('el residente asocia la factura a su corte aprobado sin adjuntar soportes', function () {
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
        ->postJson("/api/v1/facturacion/{$invoice->id}/associate", ['target_type' => 'corte', 'target_id' => $corte->id, 'comment' => 'Corte 6634 Multifox'])
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
        // El soporte del corte lo carga Paula al confirmar en Multifox, no el residente al asociar
        ->and($invoice->supports()->count())->toBe(0)
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

    expect($invoice->fresh()->status)->toBe(FacturacionInvoice::STATUS_PENDIENTE);
});

test('Paula confirma Multifox con el soporte y queda en la carpeta del corte', function () {
    Permission::firstOrCreate(['name' => 'cortes.multifox', 'guard_name' => 'web']);
    $paula = makeUser();
    $paula->givePermissionTo(['cortes.ver', 'cortes.multifox', 'proyectos.ver_todos']);

    $this->mock(DirectUploadService::class, function ($mock) {
        $mock->shouldReceive('copyTo')->andReturnUsing(fn ($path, $directory) => "{$directory}/" . basename($path));
        $mock->shouldReceive('cleanSegment')->andReturnUsing(fn ($value) => trim($value));
        $mock->shouldReceive('claimUpload')->andReturnUsing(fn ($token, $user, $directory) => [
            'file_name' => 'Corte Multifox 6634.pdf',
            'file_path' => "{$directory}/Corte Multifox 6634.pdf",
            'file_size' => 2000,
            'file_mime' => 'application/pdf',
        ]);
        $mock->shouldReceive('requestUpload')->andReturn(['upload_token' => 'tok', 'upload_url' => 'https://s3/put', 'headers' => []]);
    });

    $corte = makeCorte($this->residente);
    $invoice = uploadInvoice();
    $this->actingAs($this->residente)
        ->postJson("/api/v1/facturacion/{$invoice->id}/associate", ['target_type' => 'corte', 'target_id' => $corte->id])
        ->assertOk();
    $corteInvoice = CorteInvoice::first();

    // Sin soporte no se confirma
    $this->actingAs($paula)->postJson("/api/v1/cortes/invoices/{$corteInvoice->id}/multifox")
        ->assertStatus(422)->assertJsonValidationErrors('upload_token');

    // El residente no puede confirmar Multifox
    $this->actingAs($this->residente)
        ->postJson("/api/v1/cortes/invoices/{$corteInvoice->id}/multifox", ['upload_token' => (string) Str::uuid()])
        ->assertStatus(422);

    $this->actingAs($paula)
        ->postJson("/api/v1/cortes/invoices/{$corteInvoice->id}/multifox", ['upload_token' => (string) Str::uuid()])
        ->assertOk();

    $corteInvoice->refresh();
    $folder = $corte->fresh()->storage_path;

    expect($corteInvoice->multifox_confirmed_at)->not->toBeNull()
        ->and($corteInvoice->multifox_file_path)->toBe("{$folder}/Corte Multifox 6634.pdf")
        ->and($corteInvoice->multifox_file_name)->toBe('Corte Multifox 6634.pdf')
        ->and($corte->history()->where('action', 'multifox')->value('file_path'))->toBe("{$folder}/Corte Multifox 6634.pdf");

    // Paula puede pedir la URL de carga aunque no radique cortes
    $this->actingAs($paula)->postJson('/api/v1/cortes/upload-url', ['file_name' => 'x.pdf', 'file_size' => 10])
        ->assertOk()->assertJsonPath('data.upload_token', 'tok');

    // Quien solo ve cortes (sin radicar ni Multifox) no puede subir archivos
    $soloVer = makeUser();
    $soloVer->givePermissionTo(['cortes.ver']);
    $this->actingAs($soloVer)->postJson('/api/v1/cortes/upload-url', ['file_name' => 'x.pdf', 'file_size' => 10])
        ->assertForbidden();
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

    // Los soportes de la orden son opcionales: se asocia sin adjuntarlos
    $this->actingAs($this->residente)
        ->postJson("/api/v1/facturacion/{$invoice->id}/associate", ['target_type' => 'orden', 'target_id' => $orden->id])
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

function mockClaimUploads(): void
{
    test()->mock(DirectUploadService::class, function ($mock) {
        $mock->shouldReceive('copyTo')->andReturnUsing(fn ($path, $directory) => "{$directory}/" . basename($path));
        $mock->shouldReceive('cleanSegment')->andReturnUsing(fn ($value) => trim($value));
        $mock->shouldReceive('claimUpload')->andReturnUsing(fn ($token, $user, $directory) => [
            'file_name' => "{$token}.pdf",
            'file_path' => "{$directory}/{$token}.pdf",
            'file_size' => 2000,
            'file_mime' => 'application/pdf',
        ]);
    });
}

function accountingMail(string $event, string $email): bool
{
    return Mail::sent(\App\Http\Modules\Pagos\Mail\AccountingApprovalMail::class, fn ($m) => $m->event === $event && $m->hasTo($email))->isNotEmpty();
}

test('la factura cargada en Multifox pasa a cuentas por pagar solo con la aprobación de Contabilidad; si la devuelve vuelve a Multifox', function () {
    mockClaimUploads();
    Permission::firstOrCreate(['name' => 'cortes.multifox', 'guard_name' => 'web']);
    $paula = User::create(['name' => 'Paula', 'email' => 'paula@example.com', 'password' => 'secret']);
    $paula->givePermissionTo(['cortes.ver', 'cortes.multifox', 'proyectos.ver_todos']);
    $contador = User::create(['name' => 'Contador', 'email' => 'contador@example.com', 'password' => 'secret']);
    $contador->givePermissionTo(['cortes.ver', 'cortes.aprobar_contabilidad', 'correos.cortes.aprobacion_contable', 'proyectos.ver_todos']);

    $corte = makeCorte($this->residente);
    $invoice = uploadInvoice();
    $this->actingAs($this->residente)
        ->postJson("/api/v1/facturacion/{$invoice->id}/associate", ['target_type' => 'corte', 'target_id' => $corte->id])
        ->assertOk();
    $corteInvoice = CorteInvoice::first();

    // Contabilidad no aprueba lo que aún no está en Multifox
    $this->actingAs($contador)->postJson("/api/v1/cortes/invoices/{$corteInvoice->id}/accounting", ['decision' => 'aprobar'])->assertStatus(422);

    $this->actingAs($paula)->postJson("/api/v1/cortes/invoices/{$corteInvoice->id}/multifox", ['upload_token' => (string) Str::uuid()])->assertOk()
        ->assertJsonPath('data.invoices.0.accounting_status', CorteInvoice::ACCOUNTING_PENDIENTE);
    expect(\App\Http\Modules\Pagos\Models\PagoCuenta::count())->toBe(0)
        ->and(accountingMail('pending', 'contador@example.com'))->toBeTrue();

    // Pestaña de Contabilidad y permisos
    $this->actingAs($contador)->getJson('/api/v1/cortes?scope=accounting')->assertOk()->assertJsonPath('counts.accounting', 1);
    $this->actingAs($this->residente)->postJson("/api/v1/cortes/invoices/{$corteInvoice->id}/accounting", ['decision' => 'aprobar'])->assertForbidden();
    $this->actingAs($contador)->postJson("/api/v1/cortes/invoices/{$corteInvoice->id}/accounting", ['decision' => 'devolver'])->assertStatus(422);

    // Devuelta: vuelve a Multifox (sin soporte) y se avisa a quien tiene cortes.multifox
    $this->actingAs($contador)->postJson("/api/v1/cortes/invoices/{$corteInvoice->id}/accounting", ['decision' => 'devolver', 'comment' => 'Falta la captura de aprobación'])
        ->assertOk()->assertJsonPath('data.invoices.0.can_review_accounting', false);
    $corteInvoice->refresh();
    expect($corteInvoice->accounting_status)->toBe(CorteInvoice::ACCOUNTING_DEVUELTA)
        ->and($corteInvoice->multifox_confirmed_at)->toBeNull()
        ->and($corteInvoice->multifox_file_path)->toBeNull()
        ->and(accountingMail('returned', 'paula@example.com'))->toBeTrue()
        ->and(\App\Http\Modules\Pagos\Models\PagoCuenta::count())->toBe(0);

    // Paula la vuelve a cargar y Contabilidad la aprueba: pasa a cuentas por pagar y se avisa al residente
    $this->actingAs($paula)->postJson("/api/v1/cortes/invoices/{$corteInvoice->id}/multifox", ['upload_token' => (string) Str::uuid()])->assertOk();
    $this->actingAs($contador)->postJson("/api/v1/cortes/invoices/{$corteInvoice->id}/accounting", ['decision' => 'aprobar'])
        ->assertOk()->assertJsonPath('data.invoices.0.accounting_status', CorteInvoice::ACCOUNTING_APROBADA);

    $pago = \App\Http\Modules\Pagos\Models\PagoCuenta::first();
    expect($pago->corte_id)->toBe($corte->id)
        ->and((float) $pago->amount)->toBe(1500000.0)
        ->and(accountingMail('approved', $this->residente->email))->toBeTrue()
        ->and($corte->history()->pluck('action')->all())->toContain('contabilidad_devuelta', 'contabilidad_aprobada');
});

test('a la orden se asocia la factura sin pedir el proveedor y pasa a cuentas por pagar con la aprobación de Contabilidad', function () {
    mockClaimUploads();
    $almacen = User::create(['name' => 'Almacen', 'email' => 'almacen@example.com', 'password' => 'secret']);
    $almacen->givePermissionTo(['ordenes.ver', 'ordenes.gestionar_almacen', 'proyectos.ver_todos']);
    $contador = User::create(['name' => 'Contador', 'email' => 'contador@example.com', 'password' => 'secret']);
    $contador->givePermissionTo(['ordenes.ver', 'ordenes.aprobar_contabilidad', 'correos.ordenes.aprobacion_contable', 'proyectos.ver_todos']);

    $orden = Orden::create([
        'code' => 'ORD-0002', 'description' => 'Escrituras', 'project_id' => $this->project->id, 'chapter' => 'Legal', 'activity' => 'Notaría',
        'status' => OrdenWorkflow::STATUS_PENDIENTE_FACTURA, 'current_step' => OrdenWorkflow::STEP_FACTURA,
        'storage_path' => 'tecnica/Ordenes/ORD-0002', 'created_by' => $this->residente->id, 'approved_at' => now()->subDay(),
    ]);
    // Factura de un proveedor que no está en el catálogo (solo nombre)
    $invoice = uploadInvoice(['invoice_number' => '336', 'contractor_id' => null, 'supplier_name' => 'NOTARIA 1', 'amount' => 420000]);

    $this->actingAs($this->residente)
        ->postJson("/api/v1/facturacion/{$invoice->id}/associate", ['target_type' => 'orden', 'target_id' => $orden->id])
        ->assertOk();
    expect($orden->fresh()->status)->toBe(OrdenWorkflow::STATUS_PENDIENTE_ENTRADA);

    $entrada = fn () => $this->actingAs($almacen)->postJson("/api/v1/ordenes/{$orden->id}/documents", ['number' => 'EA-1', 'upload_token' => (string) Str::uuid()]);
    $entrada()->assertCreated()->assertJsonPath('data.status', OrdenWorkflow::STATUS_PENDIENTE_APROBACION_CONTABLE);
    expect(\App\Http\Modules\Pagos\Models\PagoCuenta::count())->toBe(0)
        ->and(accountingMail('pending', 'contador@example.com'))->toBeTrue();

    // Devuelta: vuelve a la entrada de almacén
    $this->actingAs($contador)->postJson("/api/v1/ordenes/{$orden->id}/accounting", ['decision' => 'devolver', 'comment' => 'Entrada incompleta'])
        ->assertOk()->assertJsonPath('data.status', OrdenWorkflow::STATUS_PENDIENTE_ENTRADA);
    expect($orden->documents()->where('step', OrdenWorkflow::STEP_ENTRADA)->count())->toBe(0)
        ->and(accountingMail('returned', 'almacen@example.com'))->toBeTrue();

    $entrada()->assertCreated();
    $this->actingAs($contador)->postJson("/api/v1/ordenes/{$orden->id}/accounting", ['decision' => 'aprobar'])
        ->assertOk()->assertJsonPath('data.status', OrdenWorkflow::STATUS_COMPLETADA);

    $pago = \App\Http\Modules\Pagos\Models\PagoCuenta::first();
    expect($pago->orden_id)->toBe($orden->id)
        ->and($pago->supplier_name)->toBe('NOTARIA 1')
        ->and((float) $pago->amount)->toBe(420000.0);
});

test('al asociar solo se listan los cortes aprobados sin factura y las órdenes pendientes de factura', function () {
    $pendiente = makeCorte($this->residente);
    $facturado = makeCorte($this->residente, CorteWorkflow::STATUS_FACTURADO);
    makeCorte($this->residente, CorteWorkflow::STATUS_PENDIENTE_GERENCIA);
    $orden = fn (string $code, string $status, ?string $step) => Orden::create([
        'code' => $code, 'description' => 'x', 'project_id' => $this->project->id, 'chapter' => 'c', 'activity' => 'a',
        'status' => $status, 'current_step' => $step, 'created_by' => $this->residente->id,
    ]);
    $porFacturar = $orden('ORD-0010', OrdenWorkflow::STATUS_PENDIENTE_FACTURA, OrdenWorkflow::STEP_FACTURA);
    $orden('ORD-0011', OrdenWorkflow::STATUS_PENDIENTE_ENTRADA, OrdenWorkflow::STEP_ENTRADA);

    $factura = uploadInvoice();
    $targets = $this->actingAs($this->residente)->getJson("/api/v1/facturacion/targets?invoice={$factura->id}")->assertOk()->json('data');
    expect(collect($targets['cortes'])->pluck('id')->all())->toBe([$pendiente->id])
        ->and(collect($targets['ordenes'])->pluck('id')->all())->toBe([$porFacturar->id]);

    // Una factura inicial no se asocia a un corte que ya tiene factura
    $this->actingAs($this->residente)
        ->postJson("/api/v1/facturacion/{$factura->id}/associate", ['target_type' => 'corte', 'target_id' => $facturado->id])
        ->assertStatus(422);

    // La nota crédito solo se lista contra cortes ya facturados y nunca contra órdenes
    $nota = uploadInvoice(['document_type' => 'nota_credito', 'invoice_number' => '12', 'related_document' => 'FE 335']);
    $targets = $this->actingAs($this->residente)->getJson("/api/v1/facturacion/targets?invoice={$nota->id}")->assertOk()->json('data');
    expect(collect($targets['cortes'])->pluck('id')->all())->toBe([$facturado->id])
        ->and($targets['ordenes'])->toBe([]);
});
