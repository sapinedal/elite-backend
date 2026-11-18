<?php

use App\Http\Modules\Documental\Models\DocumentShareLink;
use App\Http\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['documental.cache_store' => 'array']);

    Storage::fake('s3');
    Storage::disk('s3')->buildTemporaryUrlsUsing(fn ($path) => "https://spaces.example.com/{$path}?signed=1");
    Storage::disk('s3')->put('tecnica/Plano.pdf', 'pdf');

    foreach (['documental.ver', 'documental.renombrar', 'documental.eliminar', 'proyectos.ver_todos'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $this->user = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'secret']);
    $this->user->givePermissionTo(['documental.ver', 'documental.renombrar', 'documental.eliminar', 'proyectos.ver_todos']);
});

function shareLink(User $user, string $path): string
{
    return test()->actingAs($user)->postJson('/api/v1/documental/share-links', ['path' => $path])
        ->assertOk()->json('data.url');
}

function publicPath(string $url): string
{
    return parse_url($url, PHP_URL_PATH);
}

it('genera un enlace permanente reutilizable que abre el archivo sin sesión', function () {
    $url = shareLink($this->user, 'tecnica/Plano.pdf');
    expect(shareLink($this->user, 'tecnica/Plano.pdf'))->toBe($url)
        ->and(DocumentShareLink::count())->toBe(1);

    // Mucho después (la firma de Spaces ya habría caducado) el enlace sigue funcionando
    $this->travel(400)->days();
    auth()->forgetGuards();
    $this->get(publicPath($url))->assertRedirect('https://spaces.example.com/tecnica/Plano.pdf?signed=1');

    expect(DocumentShareLink::first()->access_count)->toBe(1);
});

it('sigue al archivo al renombrarlo y deja de funcionar al revocarlo', function () {
    $url = shareLink($this->user, 'tecnica/Plano.pdf');

    $this->actingAs($this->user)->postJson('/api/v1/documental/rename', [
        'old_path' => 'tecnica/Plano.pdf', 'new_name' => 'Plano v2.pdf', 'type' => 'file',
    ])->assertOk();
    $this->get(publicPath($url))->assertRedirect('https://spaces.example.com/tecnica/Plano v2.pdf?signed=1');

    $this->actingAs($this->user)->deleteJson('/api/v1/documental/share-links?path=' . urlencode('tecnica/Plano v2.pdf'))->assertOk();
    $this->get(publicPath($url))->assertNotFound();
});

it('deja de funcionar si el archivo se elimina', function () {
    $url = shareLink($this->user, 'tecnica/Plano.pdf');

    $this->actingAs($this->user)->deleteJson('/api/v1/documental/item', ['path' => 'tecnica/Plano.pdf', 'type' => 'file'])->assertOk();
    $this->get(publicPath($url))->assertNotFound();
});

it('no permite compartir sin permiso de lectura del módulo', function () {
    $otro = User::create(['name' => 'Sin acceso', 'email' => 'otro@example.com', 'password' => 'secret']);
    $this->actingAs($otro)->postJson('/api/v1/documental/share-links', ['path' => 'tecnica/Plano.pdf'])->assertForbidden();
});
