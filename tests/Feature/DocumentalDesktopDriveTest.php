<?php

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Documental\Models\DocumentActivityLog;
use App\Http\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['documental.cache_store' => 'array']);
    Storage::fake('s3');
    Storage::disk('s3')->put('comercial/SAN MIGUEL/promesa.pdf', 'pdf-san-miguel');
    Storage::disk('s3')->put('comercial/CAONA/promesa.pdf', 'pdf-caona');
    Storage::disk('s3')->put('comercial/GENERAL/politica.pdf', 'politica');
    Storage::disk('s3')->put('juridica/contrato.pdf', 'contrato');

    foreach (['documental.ver', 'documental.subir', 'documental.crear_carpeta', 'documental.renombrar', 'documental.eliminar', 'documental.administrar', 'proyectos.ver_todos'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $this->sanMiguel = Project::create(['code' => 'vis', 'name' => 'San Miguel', 'is_active' => true]);
    $this->caona = Project::create(['code' => 'caona', 'name' => 'Caona', 'is_active' => true]);

    $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret']);
    $this->admin->givePermissionTo(['documental.ver', 'documental.administrar', 'proyectos.ver_todos']);

    $this->user = User::create(['name' => 'Comercial', 'email' => 'comercial@example.com', 'password' => 'secret']);
    $this->user->givePermissionTo(['documental.ver', 'documental.subir', 'documental.crear_carpeta', 'documental.renombrar', 'documental.eliminar']);
    $this->user->projects()->attach($this->sanMiguel);

    $this->viewer = User::create(['name' => 'Consulta', 'email' => 'consulta@example.com', 'password' => 'secret']);
    $this->viewer->givePermissionTo(['documental.ver']);

    $this->actingAs($this->admin)->putJson('/api/v1/documental/project-folders', ['folder_path' => 'comercial/SAN MIGUEL', 'project_id' => $this->sanMiguel->id])->assertOk();
    $this->actingAs($this->admin)->putJson('/api/v1/documental/project-folders', ['folder_path' => 'comercial/CAONA', 'project_id' => $this->caona->id])->assertOk();
    // Solo el rol administrador ve jurídica: sin reglas para otros roles la carpeta queda restringida
    $role = Spatie\Permission\Models\Role::findOrCreate('juridico', 'web');
    $this->actingAs($this->admin)->putJson('/api/v1/documental/permissions/folder', ['folder_path' => 'juridica', 'rules' => [['role_id' => $role->id, 'can_read' => true]]])->assertOk();
});

function desktopKeyFor(User $user): string
{
    return test()->actingAs($user)->postJson('/api/v1/documental/desktop-keys', ['name' => 'Portátil'])
        ->assertCreated()->json('data.key');
}

function dav(string $method, string $path, ?User $user = null, ?string $key = null, array $headers = [], ?string $body = null)
{
    $server = $method === 'PROPFIND' ? ['HTTP_DEPTH' => '1'] : [];
    if ($user) {
        $server['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode($user->email . ':' . $key);
    }
    foreach ($headers as $name => $value) {
        $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return test()->call($method, '/api/dav/' . ltrim($path, '/'), [], [], [], $server, $body);
}

function davNames($response): array
{
    preg_match_all('#<d:href>([^<]+)</d:href>#', $response->streamedContent(), $m);

    return collect($m[1])->map(fn ($href) => rawurldecode($href))->sort()->values()->all();
}

it('crea, lista y revoca claves de escritorio sin exponer el hash', function () {
    $key = desktopKeyFor($this->user);
    expect($key)->toMatch('/^[A-Z0-9]{5}(-[A-Z0-9]{5}){3}$/');

    $list = $this->actingAs($this->user)->getJson('/api/v1/documental/desktop-keys')->assertOk();
    expect($list->json('data.keys'))->toHaveCount(1)
        ->and($list->json('data.keys.0'))->not->toHaveKey('token_hash')
        ->and($list->json('data.username'))->toBe('comercial@example.com');

    // Nadie puede revocar claves ajenas
    $this->actingAs($this->viewer)->deleteJson('/api/v1/documental/desktop-keys/' . $list->json('data.keys.0.id'))->assertNotFound();
    $this->actingAs($this->user)->deleteJson('/api/v1/documental/desktop-keys/' . $list->json('data.keys.0.id'))->assertOk();

    dav('PROPFIND', '', $this->user, $key)->assertStatus(401);
});

it('rechaza credenciales inválidas y la contraseña normal de Elite', function () {
    desktopKeyFor($this->user);

    dav('PROPFIND', '')->assertStatus(401);
    dav('PROPFIND', '', $this->user, 'secret')->assertStatus(401);
    dav('PROPFIND', '', $this->viewer, desktopKeyFor($this->user))->assertStatus(401);
});

it('solo muestra las carpetas permitidas por proyecto y por restricciones', function () {
    $key = desktopKeyFor($this->user);

    expect(davNames(dav('PROPFIND', '', $this->user, $key)->assertStatus(207)))
        ->toBe(['/api/dav/', '/api/dav/comercial/']);

    expect(davNames(dav('PROPFIND', 'comercial', $this->user, $key)->assertStatus(207)))
        ->toBe(['/api/dav/comercial/', '/api/dav/comercial/GENERAL/', '/api/dav/comercial/SAN MIGUEL/']);

    // Lo que no puede ver no existe para él (404), ni siquiera escribiendo la ruta a mano
    dav('PROPFIND', 'comercial/CAONA', $this->user, $key)->assertStatus(404);
    dav('GET', 'comercial/CAONA/promesa.pdf', $this->user, $key)->assertStatus(404);
    dav('GET', 'juridica/contrato.pdf', $this->user, $key)->assertStatus(404);
});

it('descarga archivos y registra la descarga una sola vez', function () {
    $key = desktopKeyFor($this->user);

    $response = dav('GET', 'comercial/SAN MIGUEL/promesa.pdf', $this->user, $key)->assertOk();
    expect($response->streamedContent())->toBe('pdf-san-miguel');
    dav('GET', 'comercial/SAN MIGUEL/promesa.pdf', $this->user, $key)->assertOk();

    expect(DocumentActivityLog::where('action', 'download')->where('path', 'comercial/SAN MIGUEL/promesa.pdf')->count())->toBe(1);
});

it('sube, reemplaza, renombra, mueve y elimina con los permisos del usuario', function () {
    $key = desktopKeyFor($this->user);
    $disk = Storage::disk('s3');

    dav('PUT', 'comercial/SAN MIGUEL/acta.docx', $this->user, $key, [], 'v1')->assertStatus(201);
    dav('PUT', 'comercial/SAN MIGUEL/acta.docx', $this->user, $key, [], 'version-2')->assertStatus(204);
    expect($disk->get('comercial/SAN MIGUEL/acta.docx'))->toBe('version-2');

    dav('MKCOL', 'comercial/SAN MIGUEL/Actas', $this->user, $key)->assertStatus(201);

    dav('MOVE', 'comercial/SAN MIGUEL/acta.docx', $this->user, $key, ['Destination' => 'http://localhost/api/dav/comercial/SAN%20MIGUEL/Actas/acta-final.docx'])->assertStatus(201);
    expect($disk->exists('comercial/SAN MIGUEL/acta.docx'))->toBeFalse()
        ->and($disk->get('comercial/SAN MIGUEL/Actas/acta-final.docx'))->toBe('version-2');

    dav('MOVE', 'comercial/SAN MIGUEL/Actas/acta-final.docx', $this->user, $key, ['Destination' => 'http://localhost/api/dav/comercial/SAN%20MIGUEL/Actas/acta-firmada.docx'])->assertStatus(201);
    expect($disk->exists('comercial/SAN MIGUEL/Actas/acta-firmada.docx'))->toBeTrue();

    dav('DELETE', 'comercial/SAN MIGUEL/Actas/acta-firmada.docx', $this->user, $key)->assertStatus(204);
    expect($disk->exists('comercial/SAN MIGUEL/Actas/acta-firmada.docx'))->toBeFalse();

    $actions = DocumentActivityLog::where('user_id', $this->user->id)->pluck('action')->all();
    expect($actions)->toContain('upload', 'update', 'create_folder', 'move', 'rename', 'delete');
    expect(DocumentActivityLog::where('action', 'upload')->first()->details['source'])->toBe('escritorio');
});

it('no deja escribir a quien solo tiene permiso de consulta', function () {
    $key = desktopKeyFor($this->viewer);

    dav('PROPFIND', 'comercial/GENERAL', $this->viewer, $key)->assertStatus(207);
    dav('PUT', 'comercial/GENERAL/nuevo.pdf', $this->viewer, $key, [], 'x')->assertStatus(403);
    dav('DELETE', 'comercial/GENERAL/politica.pdf', $this->viewer, $key)->assertStatus(403);
    dav('MKCOL', 'comercial/GENERAL/Nueva', $this->viewer, $key)->assertStatus(403);
    expect(Storage::disk('s3')->exists('comercial/GENERAL/politica.pdf'))->toBeTrue();
});

it('no deja sacar archivos de un proyecto hacia otro sin acceso', function () {
    $key = desktopKeyFor($this->user);

    $status = dav('MOVE', 'comercial/SAN MIGUEL/promesa.pdf', $this->user, $key, ['Destination' => 'http://localhost/api/dav/comercial/CAONA/promesa-2.pdf'])->getStatusCode();
    expect($status)->toBeGreaterThanOrEqual(400)
        ->and(Storage::disk('s3')->exists('comercial/SAN MIGUEL/promesa.pdf'))->toBeTrue()
        ->and(Storage::disk('s3')->exists('comercial/CAONA/promesa-2.pdf'))->toBeFalse();
});

it('bloquea extensiones prohibidas y no sube los archivos basura del sistema', function () {
    $key = desktopKeyFor($this->user);

    dav('PUT', 'comercial/SAN MIGUEL/virus.exe', $this->user, $key, [], 'MZ')->assertStatus(403);
    dav('PUT', 'comercial/SAN MIGUEL/.DS_Store', $this->user, $key, [], 'x')->assertSuccessful();
    dav('PUT', 'comercial/SAN MIGUEL/~$acta.docx', $this->user, $key, [], 'x')->assertSuccessful();

    expect(Storage::disk('s3')->allFiles('comercial/SAN MIGUEL'))->toBe(['comercial/SAN MIGUEL/promesa.pdf']);
});

it('soporta bloqueos para que Finder y Windows monten con escritura', function () {
    $key = desktopKeyFor($this->user);

    $options = dav('OPTIONS', '', $this->user, $key)->assertOk();
    expect($options->headers->get('DAV'))->toContain('2');
});

it('responde HEAD con los metadatos sin abrir el archivo', function () {
    $key = desktopKeyFor($this->user);

    $response = dav('HEAD', 'comercial/SAN MIGUEL/promesa.pdf', $this->user, $key)->assertOk();
    expect($response->headers->get('Content-Length'))->toBe((string) strlen('pdf-san-miguel'))
        ->and($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and(DocumentActivityLog::where('action', 'download')->count())->toBe(0);
});

it('con descargas aceleradas delega el archivo a nginx tras validar permisos', function () {
    config(['documental.dav_accel' => true]);
    Storage::disk('s3')->buildTemporaryUrlsUsing(fn (string $path) => 'https://inver.example/obj?X-Amz-Signature=abc');
    $key = desktopKeyFor($this->user);

    $response = dav('GET', 'comercial/SAN MIGUEL/promesa.pdf', $this->user, $key)->assertOk();
    expect($response->headers->get('X-Accel-Redirect'))->toBe('/_dav_download')
        ->and($response->headers->get('X-Dav-Download-Url'))->toBe('https://inver.example/obj?X-Amz-Signature=abc')
        ->and($response->streamedContent())->toBe('')
        ->and(DocumentActivityLog::where('action', 'download')->count())->toBe(1);

    // Sin permiso no hay URL firmada
    dav('GET', 'comercial/CAONA/promesa.pdf', $this->user, $key)->assertNotFound()->assertHeaderMissing('X-Dav-Download-Url');
});
