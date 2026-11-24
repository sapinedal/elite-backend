<?php

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Configuracion\Models\Tower;
use App\Http\Modules\Contratacion\Models\ContratacionContract;
use App\Http\Modules\Contratacion\Service\ContractStorageService;
use App\Http\Modules\Juridica\Models\ContractType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    config(['contratacion.s3_root' => 'tecnica']);

    $this->project = Project::create(['code' => 'vis', 'name' => 'San Miguel', 'is_active' => true, 'contracts_folder' => 'Contratos San Miguel']);
    $this->torre2 = Tower::create(['project_id' => $this->project->id, 'name' => 'TORRE 2']);
    $this->manoObra = ContractType::create(['name' => 'Mano de Obra', 'is_active' => true]);
    $this->suministro = ContractType::create(['name' => 'Suministro', 'is_active' => true]);

    $this->root = 'tecnica/Contratos San Miguel/TORRE 2';
    $this->storage = app(ContractStorageService::class);

    $this->contract = fn (array $attributes = []) => ContratacionContract::create([
        'project_id' => $this->project->id, 'tower_id' => $this->torre2->id, 'contract_type_id' => $this->manoObra->id,
        'contract_code' => 'T2-MO-04', 'consecutive' => '04', 'contractor_name' => 'Yancely Astrid Monsalve Yepes Y&M SAS',
        'object' => 'M.O Enchape', 'status' => 'En ejecución', ...$attributes,
    ]);
});

test('reutiliza la carpeta existente del contrato y registra sus archivos', function () {
    $folder = "{$this->root}/7. CONTRATOS MANO DE OBRA/04. YANCELLY MONSALVE - M.O enchape";
    Storage::disk('s3')->put("{$folder}/01. DOCUMENTACIÓN LEGAL/RUT 2025.pdf", 'x');
    Storage::disk('s3')->put("{$folder}/03. CONTRATO/Contrato 04.pdf", 'x');
    Storage::disk('s3')->put("{$this->root}/7. CONTRATOS MANO DE OBRA/05. YANCELLY MONSALVE - M.O mamposteria/03 CONTRATO/c.pdf", 'x');

    $contract = ($this->contract)();
    $path = $this->storage->createContractFolder($contract);
    $contract->update(['s3_path' => $path]);

    expect($path)->toBe($folder)
        ->and($this->storage->syncDocuments($contract))->toBe(2)
        ->and(Storage::disk('s3')->directories("{$this->root}/7. CONTRATOS MANO DE OBRA"))->toHaveCount(2)
        ->and($contract->documents()->where('folder', 'legal')->value('document_type'))->toBe('rut')
        ->and($contract->documents()->where('folder', 'contrato')->count())->toBe(1);
});

test('las cargas van a la subcarpeta existente aunque tenga otra numeración', function () {
    $folder = "{$this->root}/7. CONTRATOS MANO DE OBRA/04. YANCELLY MONSALVE - M.O enchape";
    Storage::disk('s3')->put("{$folder}/05.  CORTES DE OBRA/corte.pdf", 'x');

    $contract = ($this->contract)(['s3_path' => $folder]);
    $subfolder = (fn () => $this->subfolderPath($folder, 'cortes'))->call($this->storage);

    expect($subfolder)->toBe("{$folder}/05.  CORTES DE OBRA");
});

test('un contrato nuevo crea su carpeta con el objeto y las subcarpetas del catálogo', function () {
    Storage::disk('s3')->put("{$this->root}/7. CONTRATOS MANO DE OBRA/04. YANCELLY MONSALVE - M.O enchape/x.pdf", 'x');

    $contract = ($this->contract)(['consecutive' => '10', 'contractor_name' => 'Construaplicamos J Y J SAS', 'object' => 'Resanes']);
    $path = $this->storage->createContractFolder($contract);

    expect($path)->toBe("{$this->root}/7. CONTRATOS MANO DE OBRA/10. Construaplicamos J Y J SAS - Resanes")
        ->and(Storage::disk('s3')->exists("{$path}/01 DOCUMENTACIÓN LEGAL/.keep"))->toBeTrue()
        ->and(Storage::disk('s3')->exists("{$path}/07 OTROS/.keep"))->toBeTrue();
});

test('un tipo sin carpeta en el frente se crea con la numeración de la estructura', function () {
    Storage::disk('s3')->put("{$this->root}/10. CONTRATOS DE SUMINISTRO E INSTALACIÓN/01. IHC/x.pdf", 'x');

    $contract = ($this->contract)(['contract_type_id' => $this->suministro->id, 'consecutive' => '05', 'contractor_name' => 'Argos', 'object' => 'Concreto']);

    expect($this->storage->createContractFolder($contract))
        ->toBe("{$this->root}/8. CONTRATOS DE SUMINISTRO/05. Argos - Concreto");
});

test('otro contratista con el mismo consecutivo no reutiliza la carpeta', function () {
    Storage::disk('s3')->put("{$this->root}/7. CONTRATOS MANO DE OBRA/04. YANCELLY MONSALVE - M.O enchape/x.pdf", 'x');

    $contract = ($this->contract)(['contractor_name' => 'Emelect Group SAS', 'object' => 'Estructura']);

    expect($this->storage->createContractFolder($contract))
        ->toBe("{$this->root}/7. CONTRATOS MANO DE OBRA/04. Emelect Group SAS - Estructura");
});

test('otro proyecto usa su propia carpeta de contratos', function () {
    $caona = Project::create(['code' => 'caona', 'name' => 'Caona', 'is_active' => true]);
    $torre = Tower::create(['project_id' => $caona->id, 'name' => 'TORRE 2']);

    $contract = ($this->contract)(['project_id' => $caona->id, 'tower_id' => $torre->id]);

    expect($this->storage->createContractFolder($contract))
        ->toBe('tecnica/Contratos Caona/TORRE 2/7. CONTRATOS DE MANO DE OBRA/04. Yancely Astrid Monsalve Yepes Y-M SAS - M.O Enchape');
});

test('reutiliza la carpeta del proyecto aunque tenga otras mayúsculas', function () {
    $caona = Project::create(['code' => 'caona', 'name' => 'Caona', 'is_active' => true]);
    $torre = Tower::create(['project_id' => $caona->id, 'name' => 'TORRE 2']);
    Storage::disk('s3')->put('tecnica/Contratos CAONA/TORRE 2/x.pdf', 'x');

    $contract = ($this->contract)(['project_id' => $caona->id, 'tower_id' => $torre->id]);

    expect($this->storage->createContractFolder($contract))
        ->toStartWith('tecnica/Contratos CAONA/TORRE 2/');
});

describe('carpetas existentes del gestor documental', function () {
    beforeEach(function () {
        $this->folder = "{$this->root}/7. CONTRATOS MANO DE OBRA/04. YANCELLY MONSALVE - M.O enchape";
        $this->legalShared = 'tecnica/Contratacion personal/YANCELLY/LEGAL';
        Storage::disk('s3')->put("{$this->folder}/01. DOCUMENTACIÓN LEGAL/RUT viejo.pdf", 'x');
        Storage::disk('s3')->put("{$this->folder}/03. CONTRATO/Contrato.pdf", 'x');
        Storage::disk('s3')->put("{$this->legalShared}/RUT 2026.pdf", 'x');
        Storage::disk('s3')->put("{$this->legalShared}/Camara de comercio.pdf", 'x');

        $this->yancelly = ($this->contract)(['s3_path' => $this->folder]);
        $this->storage->syncDocuments($this->yancelly);

        $this->user = App\Http\Modules\Users\Models\User::create(['name' => 'Jurídica', 'email' => 'juridica@example.com', 'password' => 'secret']);
        foreach (['contratacion.ver', 'contratacion.editar', 'proyectos.ver_todos'] as $permission) {
            Spatie\Permission\Models\Permission::findOrCreate($permission, 'web');
        }
        $this->user->givePermissionTo(['contratacion.ver', 'contratacion.editar', 'proyectos.ver_todos']);
        $this->api = fn () => $this->actingAs($this->user)->withHeader('X-Project-Id', (string) $this->project->id);
    });

    test('vincular legal a otra carpeta reemplaza los archivos de su subcarpeta', function () {
        ($this->api)()->putJson("/api/v1/contratacion/contracts/{$this->yancelly->id}/folders/legal", ['path' => $this->legalShared])
            ->assertOk()
            ->assertJsonPath("data.folder_links.legal", $this->legalShared);

        $legal = $this->yancelly->documents()->where('folder', 'legal')->pluck('document_type', 'name')->all();
        expect($legal)->toBe(['Camara de comercio.pdf' => 'camara_comercio', 'RUT 2026.pdf' => 'rut'])
            ->and($this->yancelly->documents()->where('folder', 'contrato')->count())->toBe(1);
    });

    test('las cargas del tipo vinculado van a la carpeta vinculada y al quitarlo vuelve la subcarpeta', function () {
        $this->storage->setFolderLink($this->yancelly, 'legal', $this->legalShared, $this->user);

        $uploaded = $this->storage->uploadDocuments($this->yancelly, 'legal', 'cc',
            [Illuminate\Http\UploadedFile::fake()->create('cedula.pdf', 10, 'application/pdf')], $this->user);
        expect($uploaded[0]->path)->toBe("{$this->legalShared}/cedula.pdf");

        ($this->api)()->putJson("/api/v1/contratacion/contracts/{$this->yancelly->id}/folders/legal", ['path' => null])->assertOk();
        expect($this->yancelly->documents()->where('folder', 'legal')->pluck('name')->all())->toBe(['RUT viejo.pdf']);
    });

    test('cambiar la carpeta principal registra los archivos de la nueva', function () {
        $other = 'tecnica/Contratos San Miguel/TORRE 1/7.CONTRATOS MANO DE OBRA/06 - YANCELLY MONSALVE - Resanes';
        Storage::disk('s3')->put("{$other}/05 CORTES/CORTE 01/factura.pdf", 'x');

        ($this->api)()->putJson("/api/v1/contratacion/contracts/{$this->yancelly->id}/folder", ['path' => $other])
            ->assertOk()->assertJsonPath('data.s3_path', $other);

        expect($this->yancelly->documents()->pluck('folder', 'name')->all())->toBe(['factura.pdf' => 'cortes'])
            ->and(Storage::disk('s3')->exists("{$this->folder}/03. CONTRATO/Contrato.pdf"))->toBeTrue();
    });

    test('no acepta carpetas fuera de tecnica, inexistentes ni de otro proyecto', function () {
        // Carpeta asignada a Caona en el gestor documental (antes de la primera consulta, que la deja en caché)
        $caona = Project::create(['code' => 'caona', 'name' => 'Caona', 'is_active' => true]);
        Storage::disk('s3')->put('tecnica/Contratos Caona/x.pdf', 'x');
        App\Http\Modules\Documental\Models\DocumentProjectFolder::create(['folder_path' => 'tecnica/Contratos Caona', 'project_id' => $caona->id]);
        Illuminate\Support\Facades\Cache::store(config('documental.cache_store'))->flush();

        $url = "/api/v1/contratacion/contracts/{$this->yancelly->id}/folders/legal";
        ($this->api)()->putJson($url, ['path' => 'tecnica/Contratos Caona'])->assertStatus(422);
        ($this->api)()->putJson($url, ['path' => 'financiera/Bancos'])->assertStatus(422);
        ($this->api)()->putJson($url, ['path' => 'tecnica/../financiera'])->assertStatus(422);
        ($this->api)()->putJson($url, ['path' => 'tecnica/No existe'])->assertStatus(422);
    });

    test('lista las subcarpetas partiendo de la carpeta de contratos del proyecto', function () {
        ($this->api)()->getJson('/api/v1/contratacion/folders')
            ->assertOk()
            ->assertJsonPath('data.path', 'tecnica/Contratos San Miguel')
            ->assertJsonPath('data.folders.0.name', 'TORRE 2');

        ($this->api)()->getJson('/api/v1/contratacion/folders?path=tecnica')
            ->assertOk()
            ->assertJsonPath('data.parent', null);
    });

    test('no elimina un archivo que comparte otro contrato', function () {
        $otro = ($this->contract)(['consecutive' => '05', 'object' => 'Mampostería', 'contract_code' => 'T2-MO-05']);
        $this->storage->setFolderLink($this->yancelly, 'legal', $this->legalShared, $this->user);
        $this->storage->setFolderLink($otro, 'legal', $this->legalShared, $this->user);

        $document = $otro->documents()->where('name', 'RUT 2026.pdf')->first();
        ($this->api)()->deleteJson("/api/v1/contratacion/documents/{$document->id}")->assertStatus(422);
        expect(Storage::disk('s3')->exists("{$this->legalShared}/RUT 2026.pdf"))->toBeTrue();
    });
});
