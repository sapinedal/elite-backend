<?php

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Documental\Models\DocumentActivityLog;
use App\Http\Modules\Documental\Models\DocumentProjectFolder;
use App\Http\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['documental.cache_store' => 'array']);
    Storage::fake('s3');
    Storage::disk('s3')->put('comercial/SAN MIGUEL/promesa.pdf', 'x');
    Storage::disk('s3')->put('comercial/CAONA/promesa.pdf', 'x');
    Storage::disk('s3')->put('comercial/GENERAL/politica.pdf', 'x');

    foreach (['documental.ver', 'documental.subir', 'documental.crear_carpeta', 'documental.renombrar', 'documental.eliminar', 'documental.administrar', 'proyectos.ver_todos'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $this->sanMiguel = Project::create(['code' => 'vis', 'name' => 'San Miguel', 'is_active' => true]);
    $this->caona = Project::create(['code' => 'caona', 'name' => 'Caona', 'is_active' => true]);

    $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret']);
    $this->admin->givePermissionTo(['documental.ver', 'documental.administrar', 'proyectos.ver_todos']);

    // Usuario de San Miguel con permisos de edición
    $this->user = User::create(['name' => 'Comercial', 'email' => 'comercial@example.com', 'password' => 'secret']);
    $this->user->givePermissionTo(['documental.ver', 'documental.subir', 'documental.renombrar', 'documental.eliminar']);
    $this->user->projects()->attach($this->sanMiguel);

    $this->actingAs($this->admin)->putJson('/api/v1/documental/project-folders', ['folder_path' => 'comercial/SAN MIGUEL', 'project_id' => $this->sanMiguel->id])->assertOk();
    $this->actingAs($this->admin)->putJson('/api/v1/documental/project-folders', ['folder_path' => 'comercial/CAONA', 'project_id' => $this->caona->id])->assertOk();
});

function folderNames($response): array
{
    return collect($response->json('data.folders'))->pluck('name')->sort()->values()->all();
}

it('con proyecto activo oculta las carpetas de otros proyectos y deja las transversales', function () {
    $response = $this->actingAs($this->admin)
        ->withHeader('X-Project-Id', (string) $this->sanMiguel->id)
        ->getJson('/api/v1/documental?path=comercial')
        ->assertOk();

    expect(folderNames($response))->toBe(['GENERAL', 'SAN MIGUEL']);
});

it('sin proyecto activo muestra solo los proyectos a los que el usuario tiene acceso', function () {
    expect(folderNames($this->actingAs($this->admin)->getJson('/api/v1/documental?path=comercial')))
        ->toBe(['CAONA', 'GENERAL', 'SAN MIGUEL']);

    expect(folderNames($this->actingAs($this->user)->getJson('/api/v1/documental?path=comercial')))
        ->toBe(['GENERAL', 'SAN MIGUEL']);
});

it('no permite entrar ni descargar en carpetas de otro proyecto', function () {
    $this->actingAs($this->user)
        ->withHeader('X-Project-Id', (string) $this->sanMiguel->id)
        ->getJson('/api/v1/documental?path=comercial/CAONA')
        ->assertForbidden();

    $this->actingAs($this->user)
        ->withHeader('X-Project-Id', (string) $this->sanMiguel->id)
        ->getJson('/api/v1/documental/download?path=comercial/CAONA/promesa.pdf')
        ->assertForbidden();
});

it('la búsqueda recursiva no devuelve archivos de otros proyectos', function () {
    $files = $this->actingAs($this->admin)
        ->withHeader('X-Project-Id', (string) $this->sanMiguel->id)
        ->getJson('/api/v1/documental?path=comercial&search=promesa&recursive=1')
        ->assertOk()
        ->json('data.files');

    expect(collect($files)->pluck('path')->all())->toBe(['comercial/SAN MIGUEL/promesa.pdf']);
});

it('no permite eliminar una carpeta que contiene carpetas de otro proyecto', function () {
    $this->actingAs($this->user)
        ->withHeader('X-Project-Id', (string) $this->sanMiguel->id)
        ->deleteJson('/api/v1/documental/item', ['path' => 'comercial', 'type' => 'folder'])
        ->assertForbidden();

    expect(Storage::disk('s3')->exists('comercial/CAONA/promesa.pdf'))->toBeTrue();
});

it('al renombrar una carpeta etiquetada conserva su proyecto', function () {
    $this->actingAs($this->user)
        ->withHeader('X-Project-Id', (string) $this->sanMiguel->id)
        ->postJson('/api/v1/documental/rename', ['old_path' => 'comercial/SAN MIGUEL', 'new_name' => 'CIUDADELA SAN MIGUEL', 'type' => 'folder'])
        ->assertOk();

    expect(DocumentProjectFolder::where('folder_path', 'comercial/CIUDADELA SAN MIGUEL')->value('project_id'))->toBe($this->sanMiguel->id)
        ->and(DocumentProjectFolder::where('folder_path', 'comercial/SAN MIGUEL')->exists())->toBeFalse();
});

it('registra el proyecto activo en la auditoría', function () {
    $this->actingAs($this->user)
        ->withHeader('X-Project-Id', (string) $this->sanMiguel->id)
        ->postJson('/api/v1/documental/rename', ['old_path' => 'comercial/SAN MIGUEL/promesa.pdf', 'new_name' => 'promesa-firmada.pdf', 'type' => 'file'])
        ->assertOk();

    expect(DocumentActivityLog::where('action', 'rename')->value('project_id'))->toBe($this->sanMiguel->id);
});

it('quitar la etiqueta vuelve la carpeta transversal', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/documental/project-folders', ['folder_path' => 'comercial/CAONA', 'project_id' => null])
        ->assertOk();

    expect(folderNames($this->actingAs($this->user)->withHeader('X-Project-Id', (string) $this->sanMiguel->id)->getJson('/api/v1/documental?path=comercial')))
        ->toBe(['CAONA', 'GENERAL', 'SAN MIGUEL']);
});

it('solo el administrador del módulo puede etiquetar carpetas', function () {
    $this->actingAs($this->user)
        ->putJson('/api/v1/documental/project-folders', ['folder_path' => 'comercial/GENERAL', 'project_id' => $this->sanMiguel->id])
        ->assertForbidden();
});
