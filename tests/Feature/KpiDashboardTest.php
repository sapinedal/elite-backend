<?php

use App\Http\Modules\Configuracion\Models\Area;
use App\Http\Modules\Configuracion\Models\Position;
use App\Http\Modules\Evaluaciones\Models\Evaluation;
use App\Http\Modules\Evaluaciones\Models\EvaluationResult;
use App\Http\Modules\Evaluaciones\Service\KpiDashboardService;
use App\Http\Modules\Plantillas\Models\KPI;
use App\Http\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::findOrCreate('kpi.ver', 'web');

    $comercial = Area::create(['name' => 'Comercial']);
    $otra = Area::create(['name' => 'Técnica']);
    $asesora = Position::create(['name' => 'Asesora comercial', 'area_id' => $comercial->id]);
    $directora = Position::create(['name' => 'Directora comercial', 'area_id' => $comercial->id]);

    $user = fn (string $name, Area $area, ?Position $position = null) => User::create([
        'name' => $name, 'email' => str()->slug($name) . '@example.com', 'password' => 'secret',
        'area_id' => $area->id, 'position_id' => $position?->id,
    ]);
    $this->paola = $user('PAOLA ANDREA ARENAS GAVIRIA', $comercial, $asesora);
    $this->sara = $user('SARA ELENA MORENO OROZCO', $comercial, $directora);
    $this->ajeno = $user('OTRA PERSONA', $otra);

    // KPIs configurados de Sara (no tiene evaluación en el periodo)
    foreach ([['A. Estrategia', 30], ['B. Liderazgo', 70]] as [$name, $weight]) {
        KPI::create(['user_id' => $this->sara->id, 'name' => $name, 'weight' => $weight, 'stage' => $name]);
    }

    $evaluate = function (User $u, int $month, int $year, float $total, array $results = []) {
        $evaluation = Evaluation::create(['user_id' => $u->id, 'month' => $month, 'year' => $year, 'total_score' => $total, 'status' => 'finalizada']);
        foreach ($results as [$name, $weight, $score]) {
            EvaluationResult::create(['evaluation_id' => $evaluation->id, 'kpi_name' => $name, 'kpi_weight' => $weight, 'kpi_target' => 100, 'score' => $score]);
        }
    };
    // Periodo consultado: 10/2026. Pesos reales 70/25/5 (no los 60/40 que tenía el dashboard escritos a mano)
    $evaluate($this->paola, 10, 2026, 70.25, [
        ['C. Gestión Operativa', 25, 75],
        ['A. Gestión de Clientes', 70, 70],
        ['B. Relaciones', 5, 50],
    ]);
    $evaluate($this->paola, 9, 2026, 80);
    $evaluate($this->sara, 9, 2026, 90);
    $evaluate($this->paola, 1, 2025, 10); // fuera de la ventana
    $evaluate($this->ajeno, 10, 2026, 99); // otra área

    $this->report = app(KpiDashboardService::class)->areaReport('Comercial', 10, 2026);
});

it('resume el área solo con sus colaboradores', function () {
    expect($this->report['total_users'])->toBe(2)
        ->and($this->report['evaluated'])->toBe(1)
        ->and($this->report['average'])->toBe(70.25)
        ->and(collect($this->report['collaborators'])->pluck('user_id')->all())->toBe([$this->paola->id, $this->sara->id]);
});

it('usa los resultados reales de la evaluación por categoría', function () {
    $paola = collect($this->report['collaborators'])->firstWhere('user_id', $this->paola->id);

    expect($paola['short_name'])->toBe('Paola Arenas')
        ->and($paola['total_score'])->toBe(70.25)
        ->and($paola['categories'])->toBe([
            ['name' => 'A. Gestión de Clientes', 'incidence' => 70.0, 'score' => 70.0],
            ['name' => 'B. Relaciones', 'incidence' => 5.0, 'score' => 50.0],
            ['name' => 'C. Gestión Operativa', 'incidence' => 25.0, 'score' => 75.0],
        ]);
});

it('sin evaluación muestra los KPIs configurados sin calificación (no inventa puntajes)', function () {
    $sara = collect($this->report['collaborators'])->firstWhere('user_id', $this->sara->id);

    expect($sara['evaluated'])->toBeFalse()
        ->and($sara['total_score'])->toBeNull()
        ->and($sara['categories'])->toBe([
            ['name' => 'A. Estrategia', 'incidence' => 30.0, 'score' => null],
            ['name' => 'B. Liderazgo', 'incidence' => 70.0, 'score' => null],
        ]);
});

it('la calificación del desglose es el aporte de cada categoría a la nota final', function () {
    expect(collect($this->report['breakdown'])->map(fn ($r) => [$r['category'], $r['incidence'], $r['contribution']])->all())->toBe([
        ['A. Gestión de Clientes', 70.0, 49.0],
        ['B. Relaciones', 5.0, 2.5],
        ['C. Gestión Operativa', 25.0, 18.8],
    ]);
    // La suma de aportes coincide con la nota final
    expect(round(collect($this->report['breakdown'])->sum('contribution'), 1))->toBe(70.3);
});

it('la comparativa cubre los últimos 9 meses hasta el periodo', function () {
    expect(collect($this->report['months'])->pluck('key')->all())->toBe([
        '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10',
    ]);

    $paola = collect($this->report['comparison'])->firstWhere('user_id', $this->paola->id);
    expect($paola['scores']['2026-09'])->toBe(80.0)
        ->and($paola['scores']['2026-10'])->toBe(70.25)
        ->and($paola['scores']['2026-08'])->toBeNull();
});

it('la evolución promedia solo los meses con evaluaciones', function () {
    expect($this->report['evolution'])->toBe([
        ['key' => '2026-09', 'year' => 2026, 'month' => 9, 'label' => 'Sep-26', 'average' => 85.0, 'evaluated' => 2],
        ['key' => '2026-10', 'year' => 2026, 'month' => 10, 'label' => 'Oct-26', 'average' => 70.3, 'evaluated' => 1],
    ]);
});

it('expone el reporte por API con permiso kpi.ver', function () {
    $this->paola->givePermissionTo('kpi.ver');

    $this->actingAs($this->paola)
        ->getJson('/api/v1/dashboard/area?area=Comercial&month=10&year=2026')
        ->assertOk()
        ->assertJsonPath('average', 70.25)
        ->assertJsonCount(3, 'breakdown');

    $this->actingAs($this->ajeno)
        ->getJson('/api/v1/dashboard/area?area=Comercial&month=10&year=2026')
        ->assertForbidden();
});

it('el PDF muestra los datos reales y no los valores fijos', function () {
    $html = view('pdf.dashboard', ['report' => $this->report])->render();

    expect($html)
        ->toContain('PAOLA ANDREA ARENAS GAVIRIA')
        ->toContain('A. Gestión de Clientes')
        ->toContain('SIN EVALUACIÓN EN EL PERIODO')
        ->toContain('70.3%')
        ->not->toContain('Estrategia y Planificación');
});
