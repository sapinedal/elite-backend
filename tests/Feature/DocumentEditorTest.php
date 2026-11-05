<?php

use App\Http\Modules\Documental\Models\DocumentActivityLog;
use App\Http\Modules\Users\Models\User;
use App\Services\OnlyOfficeJwt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

const OO_SECRET = 'secreto-de-prueba';

beforeEach(function () {
    config([
        'documental.cache_store' => 'array',
        'onlyoffice.url' => 'https://docs.example.com',
        'onlyoffice.jwt_secret' => OO_SECRET,
        'onlyoffice.callback_base_url' => 'https://elite.example.com',
        'onlyoffice.keep_versions' => 2,
    ]);

    Storage::fake('s3');
    // El disco falso local no firma URLs temporales como S3
    Storage::disk('s3')->buildTemporaryUrlsUsing(fn ($path) => "https://spaces.example.com/{$path}?signed=1");
    Storage::disk('s3')->put('tecnica/Presupuesto.xlsx', 'v1');
    Storage::disk('s3')->put('tecnica/Antiguo.xls', 'xls');

    foreach (['documental.ver', 'documental.subir', 'proyectos.ver_todos'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $this->editor = User::create(['name' => 'Editora', 'email' => 'editora@example.com', 'password' => 'secret']);
    $this->editor->givePermissionTo(['documental.ver', 'documental.subir', 'proyectos.ver_todos']);
    $this->lector = User::create(['name' => 'Lector', 'email' => 'lector@example.com', 'password' => 'secret']);
    $this->lector->givePermissionTo(['documental.ver', 'proyectos.ver_todos']);
});

function editorConfig(User $user, string $path)
{
    return test()->actingAs($user)->getJson('/api/v1/documental/editor/config?path=' . urlencode($path));
}

/** Simula el POST de ONLYOFFICE al callback que la configuración le indicó */
function postCallback(string $callbackUrl, array $payload)
{
    $relative = parse_url($callbackUrl, PHP_URL_PATH) . '?' . parse_url($callbackUrl, PHP_URL_QUERY);

    return test()->postJson($relative, ['token' => (new OnlyOfficeJwt(OO_SECRET))->encode($payload)]);
}

it('entrega la configuración firmada en modo edición a quien puede subir', function () {
    $data = editorConfig($this->editor, 'tecnica/Presupuesto.xlsx')->assertOk()->json('data');

    expect($data['document_server_url'])->toBe('https://docs.example.com')
        ->and($data['can_edit'])->toBeTrue()
        ->and($data['config']['documentType'])->toBe('cell')
        ->and($data['config']['editorConfig']['mode'])->toBe('edit')
        ->and($data['config']['editorConfig']['user'])->toBe(['id' => (string) $this->editor->id, 'name' => 'Editora'])
        ->and($data['config']['document']['url'])->toContain('spaces.example.com')
        ->and($data['config']['editorConfig']['callbackUrl'])->toStartWith('https://elite.example.com/api/v1/documental/editor/callback?');

    // El token es la misma configuración firmada con el secreto compartido
    $decoded = (new OnlyOfficeJwt(OO_SECRET))->decode($data['config']['token']);
    expect($decoded['document']['key'])->toBe($data['config']['document']['key']);

    expect(DocumentActivityLog::where('action', 'open_editor')->exists())->toBeTrue();
});

it('abre en solo lectura a quien no puede subir y los formatos antiguos', function () {
    $lector = editorConfig($this->lector, 'tecnica/Presupuesto.xlsx')->assertOk()->json('data');
    expect($lector['can_edit'])->toBeFalse()
        ->and($lector['config']['editorConfig']['mode'])->toBe('view')
        ->and($lector['read_only_reason'])->toContain('solo lectura');

    $xls = editorConfig($this->editor, 'tecnica/Antiguo.xls')->assertOk()->json('data');
    expect($xls['can_edit'])->toBeFalse()
        ->and($xls['read_only_reason'])->toContain('.xlsx');
});

it('la key de coedición es la misma para todos y cambia cuando cambia el archivo', function () {
    $keyA = editorConfig($this->editor, 'tecnica/Presupuesto.xlsx')->json('data.config.document.key');
    $keyB = editorConfig($this->lector, 'tecnica/Presupuesto.xlsx')->json('data.config.document.key');
    expect($keyA)->toBe($keyB);

    Storage::disk('s3')->put('tecnica/Presupuesto.xlsx', 'contenido nuevo más largo');
    expect(editorConfig($this->editor, 'tecnica/Presupuesto.xlsx')->json('data.config.document.key'))->not->toBe($keyA);
});

it('rechaza archivos que no se pueden abrir', function () {
    Storage::disk('s3')->put('tecnica/foto.png', 'png');
    editorConfig($this->editor, 'tecnica/foto.png')->assertStatus(422);
    editorConfig($this->editor, 'tecnica/no-existe.xlsx')->assertStatus(422);
});

it('al guardar sube la nueva versión y conserva la anterior en .versiones', function () {
    Http::fake(['docs.example.com/*' => Http::response('v2-editado')]);
    $callback = editorConfig($this->editor, 'tecnica/Presupuesto.xlsx')->json('data.config.editorConfig.callbackUrl');

    postCallback($callback, [
        'key' => 'k', 'status' => 2, 'url' => 'https://docs.example.com/cache/files/out.xlsx', 'users' => [(string) $this->editor->id],
    ])->assertOk()->assertExactJson(['error' => 0]);

    $disk = Storage::disk('s3');
    expect($disk->get('tecnica/Presupuesto.xlsx'))->toBe('v2-editado');
    $versions = $disk->files('tecnica/.versiones/Presupuesto.xlsx');
    expect($versions)->toHaveCount(1)
        ->and($disk->get($versions[0]))->toBe('v1');

    $log = DocumentActivityLog::where('action', 'edit')->first();
    expect($log->user_id)->toBe($this->editor->id)
        ->and($log->path)->toBe('tecnica/Presupuesto.xlsx');

    // La carpeta de versiones no aparece en el listado
    $folders = $this->actingAs($this->editor)->getJson('/api/v1/documental?path=tecnica')->json('data.folders');
    expect($folders)->toBe([]);
});

it('conserva solo las últimas versiones configuradas', function () {
    $this->travelTo(now()->startOfDay());
    Http::fake(['docs.example.com/*' => Http::sequence()->push('v2')->push('v3')->push('v4')]);
    $callback = editorConfig($this->editor, 'tecnica/Presupuesto.xlsx')->json('data.config.editorConfig.callbackUrl');

    foreach ([1, 2, 3] as $i) {
        $this->travel(1)->minutes();
        postCallback($callback, ['status' => 6, 'url' => 'https://docs.example.com/x', 'users' => [(string) $this->editor->id]])->assertOk();
    }

    expect(Storage::disk('s3')->files('tecnica/.versiones/Presupuesto.xlsx'))->toHaveCount(2)
        ->and(Storage::disk('s3')->get('tecnica/Presupuesto.xlsx'))->toBe('v4');
});

it('no guarda si la firma de la URL o el JWT no son válidos', function () {
    Http::fake();
    $callback = editorConfig($this->editor, 'tecnica/Presupuesto.xlsx')->json('data.config.editorConfig.callbackUrl');

    // Ruta cambiada en la URL firmada
    postCallback(str_replace('Presupuesto', 'Otro', $callback), ['status' => 2, 'url' => 'https://docs.example.com/x', 'users' => [(string) $this->editor->id]])
        ->assertForbidden();

    // JWT firmado con otro secreto
    $relative = parse_url($callback, PHP_URL_PATH) . '?' . parse_url($callback, PHP_URL_QUERY);
    $this->postJson($relative, ['token' => (new OnlyOfficeJwt('otro'))->encode(['status' => 2, 'url' => 'https://docs.example.com/x', 'users' => ['1']])])
        ->assertForbidden();

    // URL de descarga fuera del servidor de documentos
    postCallback($callback, ['status' => 2, 'url' => 'https://evil.example.com/x', 'users' => [(string) $this->editor->id]])
        ->assertStatus(422);

    Http::assertNothingSent();
    expect(Storage::disk('s3')->get('tecnica/Presupuesto.xlsx'))->toBe('v1');
});

it('no guarda si quien editó ya no tiene permiso de escritura', function () {
    Http::fake();
    $callback = editorConfig($this->editor, 'tecnica/Presupuesto.xlsx')->json('data.config.editorConfig.callbackUrl');

    postCallback($callback, ['status' => 2, 'url' => 'https://docs.example.com/x', 'users' => [(string) $this->lector->id]])
        ->assertForbidden();

    expect(Storage::disk('s3')->get('tecnica/Presupuesto.xlsx'))->toBe('v1');
});

it('ignora los estados que no son de guardado', function () {
    Http::fake();
    $callback = editorConfig($this->editor, 'tecnica/Presupuesto.xlsx')->json('data.config.editorConfig.callbackUrl');

    postCallback($callback, ['status' => 1, 'users' => [(string) $this->editor->id]])->assertExactJson(['error' => 0]);
    postCallback($callback, ['status' => 4])->assertExactJson(['error' => 0]);
    Http::assertNothingSent();
});

it('crea hojas de cálculo, documentos y presentaciones en blanco', function () {
    foreach (['xlsx', 'docx', 'pptx'] as $type) {
        $this->actingAs($this->editor)
            ->postJson('/api/v1/documental/editor/new', ['parent_path' => 'tecnica', 'name' => 'Seguimiento', 'type' => $type])
            ->assertCreated()
            ->assertJsonPath('data.path', "tecnica/Seguimiento.{$type}");

        expect(Storage::disk('s3')->get("tecnica/Seguimiento.{$type}"))->toBe(file_get_contents(resource_path("templates/blank.{$type}")));
    }

    // Nombre repetido: no sobrescribe
    $this->actingAs($this->editor)
        ->postJson('/api/v1/documental/editor/new', ['parent_path' => 'tecnica', 'name' => 'Seguimiento', 'type' => 'xlsx'])
        ->assertCreated()
        ->assertJsonPath('data.path', fn ($p) => $p !== 'tecnica/Seguimiento.xlsx' && str_ends_with($p, '.xlsx'));

    $this->actingAs($this->lector)
        ->postJson('/api/v1/documental/editor/new', ['parent_path' => 'tecnica', 'name' => 'X', 'type' => 'xlsx'])
        ->assertForbidden();
});

it('informa si el editor está deshabilitado', function () {
    config(['onlyoffice.url' => '']);

    $this->actingAs($this->editor)->getJson('/api/v1/documental/editor/info')->assertJsonPath('data.enabled', false);
    editorConfig($this->editor, 'tecnica/Presupuesto.xlsx')->assertStatus(422);
});
