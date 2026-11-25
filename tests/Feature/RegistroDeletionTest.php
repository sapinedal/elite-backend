<?php

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Cortes\Models\CorteInvoice;
use App\Http\Modules\Cortes\Service\CorteWorkflow;
use App\Http\Modules\CuentasCobro\Models\CuentaCobro;
use App\Http\Modules\CuentasCobro\Models\CuentaCobroTipo;
use App\Http\Modules\CuentasCobro\Service\CuentaCobroWorkflow;
use App\Http\Modules\Facturacion\Models\FacturacionInvoice;
use App\Http\Modules\Facturacion\Service\FacturacionStorageService;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Ordenes\Models\Orden;
use App\Http\Modules\Ordenes\Service\OrdenWorkflow;
use App\Http\Modules\Pagos\Models\PagoCuenta;
use App\Http\Modules\Pagos\Service\PagoService;
use App\Http\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->deletedFiles = [];
    $this->mock(FacturacionStorageService::class, function ($mock) {
        $mock->shouldReceive('delete')->andReturnUsing(function ($path) {
            $this->deletedFiles[] = $path;
        });
    });

    foreach (['cortes.ver', 'cortes.eliminar', 'ordenes.ver', 'ordenes.eliminar', 'cuentas_cobro.ver', 'cuentas_cobro.eliminar', 'facturacion.eliminar', 'cortes.facturar', 'proyectos.ver_todos'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $this->project = Project::create(['code' => 'del', 'name' => 'San Miguel', 'is_active' => true]);
    $this->contractor = FtraContractor::create(['name' => 'INVALGO SAS', 'nit' => '900']);

    $this->admin = User::create(['name' => 'Admin', 'email' => 'admin-del@test.com', 'password' => 'secret']);
    $this->admin->givePermissionTo(['cortes.ver', 'cortes.eliminar', 'ordenes.ver', 'ordenes.eliminar', 'cuentas_cobro.ver', 'cuentas_cobro.eliminar', 'facturacion.eliminar', 'proyectos.ver_todos']);

    $this->sinPermiso = User::create(['name' => 'Residente', 'email' => 'res-del@test.com', 'password' => 'secret']);
    $this->sinPermiso->givePermissionTo(['cortes.ver', 'ordenes.ver', 'proyectos.ver_todos']);
});

/**
 * Corte facturado con una factura aprobada por Contabilidad, su cuenta por pagar y la factura del mes asociada.
 */
function delFacturedCorte(): array
{
    $t = test();
    $corte = Corte::create([
        'code' => 'CORTE 1', 'project_id' => $t->project->id, 'contractor_id' => $t->contractor->id,
        'status' => CorteWorkflow::STATUS_FACTURADO, 'file_name' => 'corte.pdf', 'file_path' => 'x/corte.pdf',
        'created_by' => $t->admin->id, 'approved_at' => now(),
    ]);
    $invoice = $corte->invoices()->create([
        'invoice_number' => 'FE 335', 'document_type' => CorteInvoice::TYPE_FACTURA_INICIAL, 'amount' => 1000000,
        'accounting_status' => CorteInvoice::ACCOUNTING_APROBADA, 'file_name' => 'fe.pdf', 'file_path' => 'x/fe.pdf', 'file_size' => 10,
    ]);
    $cuenta = PagoCuenta::create([
        'source_type' => PagoCuenta::SOURCE_CORTE, 'source_id' => $invoice->id, 'corte_id' => $corte->id, 'project_id' => $t->project->id,
        'frente' => 'TORRE 1', 'supplier_name' => 'INVALGO SAS', 'document_number' => 'FE 335', 'amount' => 1000000,
        'status' => PagoService::STATUS_PENDIENTE,
    ]);
    $factura = FacturacionInvoice::create([
        'period' => '2026-11-01', 'contractor_id' => $t->contractor->id, 'supplier_name' => 'INVALGO SAS', 'document_type' => 'factura',
        'invoice_number' => '335', 'amount' => 1000000, 'file_name' => 'fe.pdf', 'file_path' => 'contabilidad/fe.pdf', 'file_size' => 10,
        'status' => FacturacionInvoice::STATUS_PROCESADA, 'target_type' => FacturacionInvoice::TARGET_CORTE,
        'corte_id' => $corte->id, 'corte_invoice_id' => $invoice->id, 'processed_by' => $t->admin->id, 'processed_at' => now(),
    ]);

    return [$corte, $invoice, $cuenta, $factura];
}

it('elimina un corte facturado: anula su cuenta por pagar y libera la factura del mes', function () {
    [$corte, $invoice, $cuenta, $factura] = delFacturedCorte();

    $this->actingAs($this->admin)->deleteJson("/api/v1/cortes/{$corte->id}", ['reason' => 'Se radicó dos veces'])
        ->assertOk();

    expect(Corte::find($corte->id))->toBeNull()
        ->and(Corte::withTrashed()->find($corte->id))->not->toBeNull()
        ->and(CorteInvoice::find($invoice->id))->toBeNull()
        ->and($cuenta->fresh()->status)->toBe(PagoService::STATUS_ANULADA)
        ->and($factura->fresh()->status)->toBe(FacturacionInvoice::STATUS_PENDIENTE)
        ->and($factura->fresh()->corte_invoice_id)->toBeNull();
});

it('no elimina el corte si su cuenta por pagar ya tiene pagos', function () {
    [$corte, , $cuenta] = delFacturedCorte();
    $cuenta->update(['paid_amount' => 500000, 'status' => PagoService::STATUS_PARCIAL]);

    $this->actingAs($this->admin)->deleteJson("/api/v1/cortes/{$corte->id}", ['reason' => 'Se radicó dos veces'])
        ->assertStatus(422);

    expect(Corte::find($corte->id))->not->toBeNull()
        ->and($cuenta->fresh()->status)->toBe(PagoService::STATUS_PARCIAL);
});

it('exige permiso y motivo para eliminar', function () {
    [$corte] = delFacturedCorte();

    $this->actingAs($this->sinPermiso)->deleteJson("/api/v1/cortes/{$corte->id}", ['reason' => 'Se radicó dos veces'])
        ->assertForbidden();
    $this->actingAs($this->admin)->deleteJson("/api/v1/cortes/{$corte->id}")
        ->assertStatus(422);

    expect(Corte::find($corte->id))->not->toBeNull();
});

it('elimina una factura del corte y el corte vuelve a Aprobado', function () {
    [$corte, $invoice, $cuenta, $factura] = delFacturedCorte();

    $this->actingAs($this->admin)->deleteJson("/api/v1/cortes/invoices/{$invoice->id}", ['reason' => 'Factura de otro corte'])
        ->assertOk()
        ->assertJsonPath('data.status', CorteWorkflow::STATUS_APROBADO);

    expect(CorteInvoice::find($invoice->id))->toBeNull()
        ->and($cuenta->fresh()->status)->toBe(PagoService::STATUS_ANULADA)
        ->and($factura->fresh()->status)->toBe(FacturacionInvoice::STATUS_PENDIENTE);
});

it('no elimina una factura del corte que otra nota crédito afecta', function () {
    [$corte, $invoice] = delFacturedCorte();
    $corte->invoices()->create([
        'invoice_number' => 'NC 10', 'document_type' => CorteInvoice::TYPE_NOTA_CREDITO, 'related_document' => 'FE 335', 'amount' => 100,
        'file_name' => 'nc.pdf', 'file_path' => 'x/nc.pdf', 'file_size' => 10,
    ]);

    $this->actingAs($this->admin)->deleteJson("/api/v1/cortes/invoices/{$invoice->id}", ['reason' => 'Factura de otro corte'])
        ->assertStatus(422);
});

it('elimina la factura del mes procesada con lo que generó en el corte', function () {
    [$corte, $invoice, $cuenta, $factura] = delFacturedCorte();

    $this->actingAs($this->admin)->deleteJson("/api/v1/facturacion/{$factura->id}", ['reason' => 'Contabilidad la cargó repetida'])
        ->assertOk();

    expect(FacturacionInvoice::find($factura->id))->toBeNull()
        ->and(CorteInvoice::find($invoice->id))->toBeNull()
        ->and($cuenta->fresh()->status)->toBe(PagoService::STATUS_ANULADA)
        ->and($corte->fresh()->status)->toBe(CorteWorkflow::STATUS_APROBADO)
        ->and($this->deletedFiles)->toContain('contabilidad/fe.pdf');
});

it('elimina la entrada de una orden completada: vuelve a pendiente de entrada y anula la cuenta', function () {
    $orden = Orden::create([
        'code' => 'ORD-0001', 'description' => 'Cemento', 'project_id' => $this->project->id, 'chapter' => 'C', 'activity' => 'A',
        'status' => OrdenWorkflow::STATUS_COMPLETADA, 'created_by' => $this->admin->id, 'completed_at' => now(),
    ]);
    foreach ([OrdenWorkflow::STEP_ORDEN_ERP, OrdenWorkflow::STEP_FACTURA, OrdenWorkflow::STEP_ENTRADA] as $step) {
        $docs[$step] = $orden->documents()->create(['step' => $step, 'number' => "N-{$step}", 'file_name' => "{$step}.pdf", 'file_path' => "x/{$step}.pdf", 'file_size' => 10]);
    }
    $cuenta = PagoCuenta::create([
        'source_type' => PagoCuenta::SOURCE_ORDEN, 'source_id' => $orden->id, 'orden_id' => $orden->id, 'project_id' => $this->project->id,
        'frente' => 'TORRE 1', 'supplier_name' => 'X', 'document_number' => 'N-factura', 'amount' => 500, 'status' => PagoService::STATUS_PENDIENTE,
    ]);

    // Los documentos son secuenciales: primero el último
    $this->actingAs($this->admin)->deleteJson("/api/v1/ordenes/documents/{$docs['orden_erp']->id}", ['reason' => 'Número errado'])
        ->assertStatus(422);

    $this->actingAs($this->admin)->deleteJson("/api/v1/ordenes/documents/{$docs['entrada']->id}", ['reason' => 'Entrada de otra orden'])
        ->assertOk()
        ->assertJsonPath('data.status', OrdenWorkflow::STATUS_PENDIENTE_ENTRADA);

    expect($cuenta->fresh()->status)->toBe(PagoService::STATUS_ANULADA)
        ->and($cuenta->fresh()->source_id)->toBeNull()
        ->and($orden->fresh()->current_step)->toBe(OrdenWorkflow::STEP_ENTRADA);
});

it('elimina una cuenta de cobro en pagos y anula su cuenta por pagar', function () {
    $tipo = CuentaCobroTipo::create(['name' => 'Servicios']);
    $cuentaCobro = CuentaCobro::create([
        'code' => 'CC-0001', 'tipo_id' => $tipo->id, 'project_id' => $this->project->id, 'document_number' => '49', 'amount' => 300,
        'status' => CuentaCobroWorkflow::STATUS_EN_PAGOS, 'file_name' => 'cc.pdf', 'file_path' => 'x/cc.pdf', 'created_by' => $this->admin->id,
    ]);
    $cuenta = PagoCuenta::create([
        'source_type' => PagoCuenta::SOURCE_CUENTA_COBRO, 'source_id' => $cuentaCobro->id, 'cuenta_cobro_id' => $cuentaCobro->id,
        'project_id' => $this->project->id, 'frente' => 'TORRE 1', 'supplier_name' => 'X', 'document_number' => '49', 'amount' => 300,
        'status' => PagoService::STATUS_PENDIENTE,
    ]);

    $this->actingAs($this->admin)->deleteJson("/api/v1/cuentas-cobro/{$cuentaCobro->id}", ['reason' => 'Cuenta duplicada'])
        ->assertOk();

    expect(CuentaCobro::find($cuentaCobro->id))->toBeNull()
        ->and($cuenta->fresh()->status)->toBe(PagoService::STATUS_ANULADA);
});

it('quien radica cuentas de cobro puede crear tipos, pero no editarlos', function () {
    Permission::firstOrCreate(['name' => 'cuentas_cobro.crear', 'guard_name' => 'web']);
    $residente = User::create(['name' => 'Radica', 'email' => 'radica-del@test.com', 'password' => 'secret']);
    $residente->givePermissionTo('cuentas_cobro.crear');

    $id = $this->actingAs($residente)->postJson('/api/v1/cuentas-cobro/tipos', ['name' => 'Tipo de prueba rápida', 'requires_attachment' => false])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($residente)->putJson("/api/v1/cuentas-cobro/tipos/{$id}", ['name' => 'Otro', 'requires_attachment' => false])
        ->assertForbidden();
    $this->actingAs($this->sinPermiso)->postJson('/api/v1/cuentas-cobro/tipos', ['name' => 'Otro tipo de prueba', 'requires_attachment' => true])
        ->assertForbidden();
});
