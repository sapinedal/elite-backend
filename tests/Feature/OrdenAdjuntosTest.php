<?php

use App\Http\Modules\Configuracion\Models\Project;
use App\Http\Modules\Ordenes\Models\Orden;
use App\Http\Modules\Users\Models\User;
use App\Services\DirectUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    // S3 simulado: cada token "sube" un archivo a la carpeta indicada
    $this->mock(DirectUploadService::class, function ($mock) {
        $mock->shouldReceive('forModule')->andReturnSelf();
        $mock->shouldReceive('cleanSegment')->andReturnUsing(fn ($value) => trim($value));
        $mock->shouldReceive('claimUpload')->andReturnUsing(fn ($token, $user, $folder) => [
            'file_name' => "{$token}.pdf", 'file_path' => "{$folder}/{$token}.pdf", 'file_size' => 100, 'file_mime' => 'application/pdf',
        ]);
        $mock->shouldReceive('downloadUrl')->andReturn('https://s3.test/descarga');
    });

    foreach (['ordenes.ver', 'ordenes.crear'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $this->project = Project::create(['code' => 'adj', 'name' => 'San Miguel', 'is_active' => true]);
    $this->residente = User::create(['name' => 'Residente', 'email' => 'res-adj@test.com', 'password' => 'secret']);
    $this->residente->givePermissionTo(['ordenes.ver', 'ordenes.crear']);
    $this->residente->projects()->attach($this->project->id);
});

function ordenPayload(array $overrides = []): array
{
    return [
        'description' => 'Compra de cemento',
        'project_id' => test()->project->id,
        'chapter' => 'Estructura',
        'activity' => 'Concreto',
        ...$overrides,
    ];
}

it('registra la orden con adjuntos opcionales en su carpeta', function () {
    $tokens = [fake()->uuid(), fake()->uuid()];

    $response = $this->actingAs($this->residente)->postJson('/api/v1/ordenes', ordenPayload(['attachment_tokens' => $tokens]))
        ->assertCreated()
        ->assertJsonCount(2, 'data.adjuntos');

    $orden = Orden::findOrFail($response->json('data.id'));
    expect($orden->adjuntos)->toHaveCount(2)
        ->and($orden->adjuntos->first()->file_path)->toContain($orden->code)
        ->and($orden->adjuntos->first()->file_path)->toContain('/00 Adjuntos/');

    $this->actingAs($this->residente)->getJson("/api/v1/ordenes/adjuntos/{$orden->adjuntos->first()->id}/download")
        ->assertOk()
        ->assertJsonPath('data.download_url', 'https://s3.test/descarga');
});

it('registra la orden sin adjuntos', function () {
    $this->actingAs($this->residente)->postJson('/api/v1/ordenes', ordenPayload())
        ->assertCreated()
        ->assertJsonCount(0, 'data.adjuntos');
});

it('quien registra órdenes puede pedir la URL de carga de adjuntos', function () {
    $this->mock(\App\Http\Modules\Ordenes\Service\OrdenStorageService::class, fn ($mock) => $mock->shouldReceive('requestUpload')->andReturn(['upload_token' => 'x']));

    $this->actingAs($this->residente)->postJson('/api/v1/ordenes/upload-url', ['file_name' => 'cotizacion.pdf', 'file_size' => 100, 'mime_type' => 'application/pdf'])
        ->assertOk();
});
