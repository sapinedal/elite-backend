<?php

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Configuracion\Models\Tower;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Contratacion\Service\ContractStorageService;
use App\Http\Modules\Documental\Models\DocumentActivityLog;
use App\Http\Modules\Juridica\Models\ContractType;
use App\Http\Modules\Users\Models\User;
use App\Services\DirectUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

/**
 * Los archivos que otros módulos guardan en el bucket quedan en la Auditoría de Actividad del gestor documental.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    config(['contratacion.s3_root' => 'tecnica']);

    $this->project = Project::create(['code' => 'vis', 'name' => 'San Miguel', 'is_active' => true, 'contracts_folder' => 'Contratos San Miguel']);
    $this->user = User::create(['name' => 'Residente', 'email' => 'residente@example.com', 'password' => 'secret']);
    $this->actingAs($this->user);
    $this->uploads = app(DirectUploadService::class);

    // Archivo ya subido con URL firmada a la carpeta temporal (paso 2 de la carga directa)
    $this->pendingUpload = function (string $name = 'corte.pdf'): string {
        $token = (string) Str::uuid();
        $path = DirectUploadService::TMP_ROOT . "/{$token}/{$name}";
        Storage::disk('s3')->put($path, 'contenido');
        Cache::put('direct_upload:' . $token, [
            'user_id' => $this->user->id, 'path' => $path, 'name' => $name, 'mime' => 'application/pdf', 'max_mb' => 10,
        ]);

        return $token;
    };
});

test('la carga directa de un módulo queda auditada como subida con su módulo', function () {
    $file = $this->uploads->forModule('Cortes')->claimUpload(($this->pendingUpload)(), $this->user, 'tecnica/Contratos San Miguel/TORRE 2/05 CORTES/CORTE 01');

    $log = DocumentActivityLog::sole();
    expect($log->action)->toBe('upload')
        ->and($log->path)->toBe($file['file_path'])
        ->and($log->user_id)->toBe($this->user->id)
        ->and($log->details)->toMatchArray(['module' => 'Cortes', 'size' => 9, 'mime_type' => 'application/pdf']);
});

test('mover, copiar y eliminar desde un módulo también quedan auditados', function () {
    Storage::disk('s3')->put('tecnica/a/factura.pdf', 'x');
    $anticipos = $this->uploads->forModule('Anticipos');

    $copy = $anticipos->copyTo('tecnica/a/factura.pdf', 'tecnica/b');
    $moved = $anticipos->moveTo('tecnica/a/factura.pdf', 'tecnica/c');
    $this->uploads->forModule('Facturación')->delete($copy);

    $logs = DocumentActivityLog::orderBy('id')->get();
    expect($logs->pluck('action')->all())->toBe(['copy', 'move', 'delete'])
        ->and($logs->pluck('details.module')->all())->toBe(['Anticipos', 'Anticipos', 'Facturación'])
        ->and($logs[1]->details)->toMatchArray(['from' => 'tecnica/a/factura.pdf', 'to' => $moved])
        ->and($logs->pluck('user_id')->unique()->all())->toBe([$this->user->id]);
});

test('contratación audita la carpeta del contrato, sus subidas y eliminaciones', function () {
    $tower = Tower::create(['project_id' => $this->project->id, 'name' => 'TORRE 2']);
    $type = ContractType::create(['name' => 'Mano de Obra', 'is_active' => true]);
    $contract = ContratacionContract::create([
        'project_id' => $this->project->id, 'tower_id' => $tower->id, 'contract_type_id' => $type->id,
        'contract_code' => 'T2-MO-11', 'consecutive' => '11', 'contractor_name' => 'Acabados SAS', 'object' => 'Pintura', 'status' => 'Por iniciar',
    ]);
    $storage = app(ContractStorageService::class);

    [$document] = $storage->uploadDocuments($contract, 'contrato', null, [UploadedFile::fake()->create('contrato.pdf', 5, 'application/pdf')], $this->user);
    $storage->deleteDocument($document);

    $logs = DocumentActivityLog::orderBy('id')->get();
    expect($logs->pluck('action')->all())->toBe(['create_folder', 'upload', 'delete'])
        ->and($logs->pluck('details.module')->unique()->all())->toBe(['Contratación'])
        ->and($logs->pluck('details.contract_code')->unique()->all())->toBe(['T2-MO-11'])
        ->and($logs[0]->path)->toBe('tecnica/Contratos San Miguel/TORRE 2/7. CONTRATOS DE MANO DE OBRA/11. Acabados SAS - Pintura')
        ->and($logs[1]->path)->toBe($document->path)
        ->and($logs[1]->project_id)->toBeNull();
});

test('la auditoría filtra por módulo y separa lo hecho desde el gestor', function () {
    $this->uploads->forModule('Cortes')->claimUpload(($this->pendingUpload)(), $this->user, 'tecnica/cortes');
    $this->uploads->forModule('Pagos')->claimUpload(($this->pendingUpload)('pago.pdf'), $this->user, 'tecnica/pagos');
    DocumentActivityLog::create(['user_id' => $this->user->id, 'action' => 'upload', 'path' => 'tecnica/manual.pdf', 'details' => ['size' => 1]]);

    foreach (['documental.ver', 'documental.administrar', 'proyectos.ver_todos'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $this->user->givePermissionTo(['documental.ver', 'documental.administrar', 'proyectos.ver_todos']);

    $paths = fn (string $module) => $this->getJson("/api/v1/documental/logs?module={$module}")->assertOk()->json('logs.data.*.path');

    expect($paths('Cortes'))->toBe(['tecnica/cortes/corte.pdf'])
        ->and($paths('Pagos'))->toBe(['tecnica/pagos/pago.pdf'])
        ->and($paths('documental'))->toBe(['tecnica/manual.pdf']);
});

test('un fallo al auditar no interrumpe la operación del módulo', function () {
    DocumentActivityLog::creating(fn () => throw new RuntimeException('BD de auditoría caída'));
    $this->withoutExceptionHandling();
    app()->instance(Illuminate\Contracts\Debug\ExceptionHandler::class, Mockery::spy(Illuminate\Contracts\Debug\ExceptionHandler::class));

    $file = $this->uploads->forModule('Órdenes')->claimUpload(($this->pendingUpload)(), $this->user, 'tecnica/ordenes');

    expect(Storage::disk('s3')->exists($file['file_path']))->toBeTrue()
        ->and(DocumentActivityLog::count())->toBe(0);
});
