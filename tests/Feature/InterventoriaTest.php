<?php

use App\Http\Modules\Obra\Models\InterventoriaReport;
use App\Http\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

const INTERVENTORIA_FOLDER = 'tecnica/INFORMES SEMANALES INTERVENTORÍA';

function informeJson(int $numero, float $avance, array $extra = []): string
{
    return json_encode(array_merge([
        'parser_version' => 1,
        'numero' => $numero,
        // Fecha mal formada a propósito en informes antiguos: no debe impedir la importación
        'fecha' => $numero < 20 ? '2026-11--10' : '2026-11-17',
        'presupuesto' => ['presupuesto_aprobado' => 22454184688.0, 'inversion_ejecutada' => 14778731338.05, 'avance_inversion' => $avance],
        'capitulos' => [['numero' => 4, 'nombre' => '6. Mampostería', 'avance_actual' => 85.81, 'control' => ['estado' => 'atraso', 'dias' => 12]]],
        'comite' => ['Pendiente el control presupuestal de octubre.'],
        'actividades' => [],
        'observaciones' => ['Avance de mampostería en el nivel 18'],
        'polizas' => [['contratista' => 'TECNOIZAJES', 'estado' => 'VENCIDA']],
    ], $extra));
}

beforeEach(function () {
    Storage::fake('s3');
    config(['interventoria.disk' => 's3']);
    foreach ([2, 10, 34, 33] as $semana) {
        Storage::disk('s3')->put(INTERVENTORIA_FOLDER . "/INFORME DE INTERVENTORÍA SEMANA {$semana}.pdf", "pdf-{$semana}");
    }
    Storage::disk('s3')->put(INTERVENTORIA_FOLDER . '/Acta de comité.pdf', 'otro');

    Permission::findOrCreate('obra.ver', 'web');
    $this->user = User::create(['name' => 'Residente', 'email' => 'residente@example.com', 'password' => 'secret']);
    $this->user->givePermissionTo('obra.ver');
});

it('toma siempre el informe con el mayor número de semana, no el último en orden alfabético', function () {
    Process::fake(fn ($process) => Process::result(informeJson(34, 65.82)));

    $summary = app(App\Http\Modules\Obra\Service\InterventoriaService::class)->import();

    expect($summary['latest'])->toBe(34)
        ->and($summary['imported'])->toBe([34])
        ->and(InterventoriaReport::pluck('numero')->all())->toBe([34]);
    Process::assertRan(fn ($process) => str_contains(implode(' ', (array) $process->command), 'parse_informe.py'));
});

it('no vuelve a leer un informe sin cambios y con --todos importa el historial', function () {
    Process::fake(fn ($process) => Process::result(informeJson(34, 65.82)));
    $this->artisan('interventoria:importar')->assertSuccessful();
    $this->artisan('interventoria:importar')->expectsOutputToContain('sin cambios: 1')->assertSuccessful();

    Process::fake(function ($process) {
        preg_match('/(\d+)$/', file_get_contents(end($process->command)), $m);

        return Process::result(informeJson((int) $m[1], (float) $m[1]));
    });
    $this->artisan('interventoria:importar --todos')->assertSuccessful();

    expect(InterventoriaReport::orderBy('numero')->pluck('numero')->all())->toBe([2, 10, 33, 34]);
});

it('si un PDF no se puede leer conserva el último informe bueno', function () {
    Process::fake(fn ($process) => Process::result(informeJson(33, 65.62)));
    app(App\Http\Modules\Obra\Service\InterventoriaService::class)->importFile(33, [
        'path' => INTERVENTORIA_FOLDER . '/INFORME DE INTERVENTORÍA SEMANA 33.pdf', 'size' => 6, 'last_modified' => 1,
    ]);

    Process::fake(fn ($process) => Process::result('{"error": "PDFSyntaxError: archivo dañado"}', exitCode: 1));
    $this->artisan('interventoria:importar')->expectsOutputToContain('Semana 34')->assertFailed();

    $response = $this->actingAs($this->user)->getJson('/api/v1/obra/interventoria')->assertOk();
    expect($response->json('data.report.numero'))->toBe(33);
});

it('entrega el último informe con su serie histórica y el enlace al PDF', function () {
    foreach ([[10, 29.89], [33, 65.62], [34, 65.82]] as [$numero, $avance]) {
        InterventoriaReport::create([
            'numero' => $numero,
            'fecha' => '2026-11-17',
            'file_path' => INTERVENTORIA_FOLDER . "/INFORME DE INTERVENTORÍA SEMANA {$numero}.pdf",
            'data' => json_decode(informeJson($numero, $avance), true),
            'imported_at' => now(),
        ]);
    }

    $response = $this->actingAs($this->user)->getJson('/api/v1/obra/interventoria')->assertOk();
    expect($response->json('data.report.numero'))->toBe(34)
        ->and($response->json('data.report.es_ultimo'))->toBeTrue()
        ->and($response->json('data.report.presupuesto.avance_inversion'))->toBe(65.82)
        ->and($response->json('data.report.capitulos.0.control.estado'))->toBe('atraso')
        ->and($response->json('data.report.archivo'))->toBe('INFORME DE INTERVENTORÍA SEMANA 34.pdf')
        ->and(collect($response->json('data.history'))->pluck('avance_inversion')->all())->toBe([29.89, 65.62, 65.82]);

    // Un informe anterior puntual
    expect($this->actingAs($this->user)->getJson('/api/v1/obra/interventoria?numero=10')->json('data.report.es_ultimo'))->toBeFalse();
});

it('exige el permiso del Módulo Técnico', function () {
    $otro = User::create(['name' => 'Otro', 'email' => 'otro@example.com', 'password' => 'secret']);
    $this->actingAs($otro)->getJson('/api/v1/obra/interventoria')->assertForbidden();
});
