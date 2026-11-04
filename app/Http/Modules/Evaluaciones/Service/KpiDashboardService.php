<?php

namespace App\Http\Modules\Evaluaciones\Service;

use App\Http\Modules\Evaluaciones\Models\Evaluation;
use App\Http\Modules\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Datos del Dashboard de KPIs de un área para un periodo, calculados solo con lo que hay en la base de datos:
 * - Desglose por colaborador: cada categoría (KPI) con su incidencia (peso) y su calificación (puntaje 0-100).
 *   Si el colaborador no tiene evaluación en el periodo se muestran sus KPIs configurados sin calificación.
 * - Incidencia vs calificación: por colaborador y categoría, el peso y el aporte (peso × puntaje / 100).
 * - Comparativa de equipo: puntaje total de cada colaborador en los últimos meses.
 * - Evolución: promedio del área por mes.
 *
 * Lo usan la pantalla del Dashboard y el PDF, para que ambos muestren exactamente lo mismo.
 */
class KpiDashboardService
{
    /** Meses de la comparativa de equipo (terminando en el periodo consultado) */
    public const COMPARISON_MONTHS = 9;

    /** Meses que cubre la evolución mensual (terminando en el periodo consultado) */
    public const EVOLUTION_MONTHS = 12;

    protected const MONTH_LABELS = [1 => 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    public function areaReport(string $areaName, int $month, int $year): array
    {
        $period = CarbonImmutable::create($year, $month, 1);

        $users = User::whereHas('area', fn ($q) => $q->where('name', $areaName))
            ->with(['position:id,name', 'kpis'])
            ->get()
            ->sortBy(fn (User $u) => mb_strtolower(($u->position?->name ?? '') . ' ' . $u->name))
            ->values();

        $userIds = $users->pluck('id');
        $oldest = $period->subMonths(max(self::COMPARISON_MONTHS, self::EVOLUTION_MONTHS) - 1);

        // Evaluaciones del área en la ventana más amplia que se necesita (comparativa / evolución)
        $evaluations = Evaluation::with('results')
            ->whereIn('user_id', $userIds)
            ->whereRaw('(year * 100 + month) between ? and ?', [
                $oldest->year * 100 + $oldest->month,
                $period->year * 100 + $period->month,
            ])
            ->get();

        $periodEvaluations = $evaluations->where('month', $month)->where('year', $year)->keyBy('user_id');

        $collaborators = $users->map(fn (User $user) => $this->collaborator($user, $periodEvaluations->get($user->id)))->all();

        return [
            'area' => $areaName,
            'month' => $month,
            'year' => $year,
            'total_users' => $users->count(),
            'evaluated' => $periodEvaluations->count(),
            'average' => $periodEvaluations->isEmpty() ? null : round((float) $periodEvaluations->avg('total_score'), 2),
            'collaborators' => $collaborators,
            'breakdown' => $this->breakdown($collaborators),
            'months' => $this->monthWindow($period, self::COMPARISON_MONTHS),
            'comparison' => $this->comparison($users, $evaluations, $period),
            'evolution' => $this->evolution($evaluations, $period),
        ];
    }

    protected function collaborator(User $user, ?Evaluation $evaluation): array
    {
        $hasResults = $evaluation && $evaluation->results->isNotEmpty();

        // Con evaluación: sus resultados. Sin evaluación: los KPIs configurados, sin calificación.
        $categories = $hasResults
            ? $evaluation->results->map(fn ($r) => [
                'name' => $r->kpi_name,
                'incidence' => (float) $r->kpi_weight,
                'score' => (float) $r->score,
            ])
            : $user->kpis->map(fn ($k) => [
                'name' => $k->name,
                'incidence' => (float) $k->weight,
                'score' => null,
            ]);

        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'short_name' => $this->shortName($user),
            'position' => $user->position?->name,
            'evaluated' => $evaluation !== null,
            'total_score' => $evaluation ? (float) $evaluation->total_score : null,
            'categories' => $categories->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
        ];
    }

    /**
     * Incidencia vs calificación por colaborador evaluado y categoría.
     * La calificación es el aporte de la categoría a la nota final: peso × puntaje / 100.
     */
    protected function breakdown(array $collaborators): array
    {
        $rows = [];
        foreach ($collaborators as $c) {
            if (!$c['evaluated']) {
                continue;
            }
            foreach ($c['categories'] as $category) {
                $rows[] = [
                    'user_id' => $c['user_id'],
                    'short_name' => $c['short_name'],
                    'position' => $c['position'],
                    'category' => $category['name'],
                    'incidence' => round($category['incidence'], 1),
                    'contribution' => round($category['incidence'] * ($category['score'] ?? 0) / 100, 1),
                ];
            }
        }

        return $rows;
    }

    protected function comparison(Collection $users, Collection $evaluations, CarbonImmutable $period): array
    {
        $months = $this->monthWindow($period, self::COMPARISON_MONTHS);
        $byUser = $evaluations->groupBy('user_id');

        return $users->map(function (User $user) use ($months, $byUser) {
            $userEvaluations = $byUser->get($user->id, collect());
            $scores = [];
            foreach ($months as $m) {
                $evaluation = $userEvaluations->first(fn ($e) => (int) $e->month === $m['month'] && (int) $e->year === $m['year']);
                $scores[$m['key']] = $evaluation ? (float) $evaluation->total_score : null;
            }

            return [
                'user_id' => $user->id,
                'short_name' => $this->shortName($user),
                'position' => $user->position?->name,
                'scores' => $scores,
            ];
        })->all();
    }

    /**
     * Promedio del área por mes, solo para los meses con evaluaciones.
     */
    protected function evolution(Collection $evaluations, CarbonImmutable $period): array
    {
        $rows = [];
        foreach ($this->monthWindow($period, self::EVOLUTION_MONTHS) as $m) {
            $monthEvaluations = $evaluations->filter(fn ($e) => (int) $e->month === $m['month'] && (int) $e->year === $m['year']);
            if ($monthEvaluations->isEmpty()) {
                continue;
            }
            $rows[] = $m + [
                'average' => round((float) $monthEvaluations->avg('total_score'), 1),
                'evaluated' => $monthEvaluations->count(),
            ];
        }

        return $rows;
    }

    /**
     * Los $count meses que terminan en $period, del más antiguo al más reciente.
     */
    protected function monthWindow(CarbonImmutable $period, int $count): array
    {
        $months = [];
        for ($i = $count - 1; $i >= 0; $i--) {
            $date = $period->subMonths($i);
            $months[] = [
                'key' => $date->format('Y-m'),
                'year' => $date->year,
                'month' => $date->month,
                'label' => self::MONTH_LABELS[$date->month] . '-' . $date->format('y'),
            ];
        }

        return $months;
    }

    protected function shortName(User $user): string
    {
        $parts = preg_split('/\s+/', trim((string) $user->name)) ?: [];
        $first = !empty($user->first_name) ? strtok(trim($user->first_name), ' ') : ($parts[0] ?? '');
        // Con 4 palabras (2 nombres + 2 apellidos) el primer apellido es la tercera
        $last = !empty($user->last_name)
            ? strtok(trim($user->last_name), ' ')
            : ($parts[count($parts) >= 4 ? 2 : 1] ?? '');

        return mb_convert_case(trim("{$first} {$last}"), MB_CASE_TITLE, 'UTF-8');
    }
}
