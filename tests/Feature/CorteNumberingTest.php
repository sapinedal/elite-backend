<?php

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Cortes\Models\Corte;
use App\Http\Modules\Cortes\Service\CorteWorkflow;
use App\Http\Modules\Ftra\Models\FtraContractor;
use App\Http\Modules\Users\Models\User;
use App\Services\DirectUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

const EMELECT_FOLDER = 'tecnica/Contratos San Miguel/TORRE 2/7. CONTRATOS MANO DE OBRA/8. EMELECT - M.O Fundación';

beforeEach(function () {
    Mail::fake();

    // S3 simulado: el archivo queda en la carpeta que se pide
    $this->mock(DirectUploadService::class, function ($mock) {
        $mock->shouldReceive('claimUpload')->andReturnUsing(fn ($token, $user, $directory) => [
            'file_name' => 'corte.pdf',
            'file_path' => "{$directory}/corte.pdf",
            'file_size' => 1000,
            'file_mime' => 'application/pdf',
        ]);
        $mock->shouldReceive('moveTo')->andReturnUsing(fn ($path, $directory) => "{$directory}/" . basename($path));
        $mock->shouldReceive('cleanSegment')->andReturnUsing(fn ($value) => trim($value));
    });

    $this->project = Project::create(['code' => 'vis', 'name' => 'San Miguel', 'is_active' => true]);
    $this->contractor = FtraContractor::create(['name' => 'EMELECT GROUP SAS']);

    // Mismo proveedor, dos contratos: cada uno lleva su propia numeración y carpeta
    $this->fundacion = ContratacionContract::create([
        'project_id' => $this->project->id, 'contract_code' => '2759', 'consecutive' => '8',
        'contractor_name' => 'EMELECT - M.O Fundación', 'object' => 'Fundación', 'status' => 'En ejecución', 's3_path' => EMELECT_FOLDER,
    ]);
    $this->redes = ContratacionContract::create([
        'project_id' => $this->project->id, 'contract_code' => '3167', 'consecutive' => '9',
        'contractor_name' => 'EMELECT - M.O Redes', 'object' => 'Redes', 'status' => 'En ejecución',
        's3_path' => 'tecnica/Contratos San Miguel/TORRE 2/7. CONTRATOS MANO DE OBRA/9. EMELECT - M.O Redes',
    ]);

    $this->residente = User::create(['name' => 'Residente', 'email' => 'residente@example.com', 'password' => 'secret']);
    $this->residente->givePermissionTo(['cortes.ver', 'cortes.crear', 'proyectos.ver_todos']);
});

function radicar(ContratacionContract $contract, string $code)
{
    return test()->actingAs(test()->residente)->postJson('/api/v1/cortes', [
        'code' => $code,
        'project_id' => test()->project->id,
        'contractor_id' => test()->contractor->id,
        'contract_id' => $contract->id,
        'upload_token' => (string) Str::uuid(),
    ]);
}

test('el corte se numera CORTE N y queda en 05 CORTES de la carpeta de su contrato', function () {
    radicar($this->fundacion, 'CORTE 1')->assertCreated()->assertJsonPath('data.label', 'CORTE 1');
    // "2" o "corte 2" se guardan como CORTE 2
    radicar($this->fundacion, 'corte 2')->assertCreated();

    $corte = Corte::where('code', 'CORTE 1')->first();
    expect($corte->storage_path)->toBe(EMELECT_FOLDER . '/05 CORTES/CORTE 1')
        ->and($corte->file_path)->toBe(EMELECT_FOLDER . '/05 CORTES/CORTE 1/corte.pdf')
        ->and($corte->reference())->toBe('CORTE 1 · Contrato 2759')
        ->and(Corte::where('code', 'CORTE 2')->exists())->toBeTrue();
});

test('el número no se repite en el contrato pero sí entre contratos del mismo proveedor', function () {
    radicar($this->fundacion, 'CORTE 1')->assertCreated();
    radicar($this->fundacion, 'CORTE 1')->assertStatus(422)->assertJsonValidationErrors('code');
    radicar($this->redes, 'CORTE 1')->assertCreated();

    expect(Corte::where('contract_id', $this->redes->id)->first()->storage_path)->toEndWith('9. EMELECT - M.O Redes/05 CORTES/CORTE 1');

    // Un código libre ya no se acepta
    radicar($this->fundacion, 'C-6634')->assertStatus(422);
});

test('el catálogo indica los números usados por contrato', function () {
    radicar($this->fundacion, 'CORTE 1')->assertCreated();
    radicar($this->fundacion, 'CORTE 3')->assertCreated();

    $contracts = collect($this->actingAs($this->residente)->getJson('/api/v1/cortes/catalogs')->assertOk()->json('data.contracts'));

    expect($contracts->firstWhere('id', $this->fundacion->id)['corte_numbers'])->toBe([1, 3])
        ->and($contracts->firstWhere('id', $this->redes->id)['corte_numbers'])->toBe([]);
});

test('al reenviar con otro contrato el archivo se mueve a la carpeta del nuevo contrato', function () {
    radicar($this->fundacion, 'CORTE 1')->assertCreated();
    $corte = Corte::first();
    $corte->update(['status' => CorteWorkflow::STATUS_DEVUELTO, 'current_stage' => null]);

    $this->actingAs($this->residente)->postJson("/api/v1/cortes/{$corte->id}/resubmit", [
        'code' => 'CORTE 1',
        'project_id' => $this->project->id,
        'contractor_id' => $this->contractor->id,
        'contract_id' => $this->redes->id,
    ])->assertOk();

    $corte->refresh();
    expect($corte->storage_path)->toEndWith('9. EMELECT - M.O Redes/05 CORTES/CORTE 1')
        ->and($corte->file_path)->toBe($corte->storage_path . '/corte.pdf');
});

test('un corte anterior a la numeración conserva su código al reenviarse', function () {
    $corte = Corte::create([
        'code' => '6634', 'project_id' => $this->project->id, 'contractor_id' => $this->contractor->id, 'contract_id' => $this->fundacion->id,
        'status' => CorteWorkflow::STATUS_DEVUELTO, 'file_name' => 'c.pdf', 'file_path' => EMELECT_FOLDER . '/Cortes/6634/01 Corte/c.pdf',
        'storage_path' => EMELECT_FOLDER . '/Cortes/6634', 'created_by' => $this->residente->id,
    ]);

    $this->actingAs($this->residente)->postJson("/api/v1/cortes/{$corte->id}/resubmit", [
        'code' => '6634', 'project_id' => $this->project->id, 'contractor_id' => $this->contractor->id, 'contract_id' => $this->fundacion->id,
    ])->assertOk()->assertJsonPath('data.label', 'Corte 6634');

    expect($corte->fresh()->storage_path)->toBe(EMELECT_FOLDER . '/Cortes/6634');
});
