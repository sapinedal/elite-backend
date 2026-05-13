<?php

namespace App\Http\Modules\Evaluaciones\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Evaluaciones\Models\Evaluation;
use App\Http\Modules\Evaluaciones\Models\EvaluationResult;
use App\Http\Modules\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class EvaluationController extends Controller
{
    public function store(Request $request, User $user)
    {
        $validated = $request->validate([
            'month' => 'required|integer',
            'year' => 'required|integer',
            'total_score' => 'required|numeric',
            'general_analysis' => 'nullable|string',
            'status' => 'nullable|string',
            'results' => 'required|array',
        ]);

        return DB::transaction(function () use ($validated, $user) {
            // Buscamos si ya existe una evaluación para este periodo
            $evaluation = Evaluation::where('user_id', $user->id)
                ->where('month', $validated['month'])
                ->where('year', $validated['year'])
                ->first();

            if ($evaluation) {
                // GUARDAR HISTORIAL: Antes de actualizar, guardamos el estado actual en el log
                $previousData = [
                    'total_score' => $evaluation->total_score,
                    'general_analysis' => $evaluation->general_analysis,
                    'status' => $evaluation->status,
                    'updated_at' => $evaluation->updated_at,
                ];
                
                $history = $evaluation->history ?? [];
                // Mantener solo los últimos 5 registros para evitar saturar el campo JSON
                array_unshift($history, $previousData);
                $history = array_slice($history, 0, 5);

                $evaluation->update([
                    'total_score' => $validated['total_score'],
                    'status' => $validated['status'] ?? 'finalizada',
                    'general_analysis' => $validated['general_analysis'] ?? null,
                    'history' => $history
                ]);

                // Limpiamos resultados anteriores para re-insertar los nuevos
                $evaluation->results()->delete();
            } else {
                // Si no existe, la creamos desde cero
                $evaluation = Evaluation::create([
                    'user_id' => $user->id,
                    'month' => $validated['month'],
                    'year' => $validated['year'],
                    'total_score' => $validated['total_score'],
                    'status' => $validated['status'] ?? 'finalizada',
                    'general_analysis' => $validated['general_analysis'] ?? null,
                    'history' => []
                ]);
            }

            // Insertar los nuevos resultados
            foreach ($validated['results'] as $res) {
                EvaluationResult::create([
                    'evaluation_id' => $evaluation->id,
                    'kpi_id' => $res['kpi_id'] ?? null,
                    'kpi_name' => $res['kpi_name'],
                    'kpi_weight' => $res['kpi_weight'],
                    'kpi_target' => $res['kpi_target'],
                    'real_value' => $res['real_value'],
                    'score' => $res['score'],
                    'ai_analysis' => $res['ai_analysis'] ?? $res['details']['ai_analysis'] ?? null,
                    'details' => [
                        'tablaDetalle' => $res['details']['tablaDetalle'] ?? $res['tablaDetalle'] ?? null,
                        'indicator_results' => $res['details']['indicator_results'] ?? $res['indicator_results'] ?? [],
                        'ai_analysis' => $res['details']['ai_analysis'] ?? $res['ai_analysis'] ?? null
                    ],
                ]);

            }

            return response()->json($evaluation->refresh()->load('results'), 201);

        });

    }

    public function show(Request $request, User $user)
    {
        $query = Evaluation::where('user_id', $user->id)
            ->with('results');

        if ($request->has('month') && $request->has('year')) {
            $evaluation = $query->where('month', $request->month)
                ->where('year', $request->year)
                ->first();
            
            return response()->json($evaluation);
        }

        return response()->json(
            $query->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->get()
        );
    }

    public function history(User $user)
    {
        return response()->json(
            Evaluation::where('user_id', $user->id)
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->get()
        );
    }

    public function globalHistory(Request $request)
    {
        $query = Evaluation::with(['user', 'results']);

        if ($request->has('user_id') && $request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('month') && $request->month) {
            $query->where('month', $request->month);
        }

        if ($request->has('year') && $request->year) {
            $query->where('year', $request->year);
        }

        if ($request->has('area') && $request->area) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('area_id', $request->area);
            });
        }

        return response()->json(
            $query->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->get()
        );
    }

    public function exportPdf(Evaluation $evaluation)
    {
        // Solo permitir exportar evaluaciones finalizadas
        if ($evaluation->status !== 'finalizada') {
            return response()->json(['message' => 'Solo se pueden exportar evaluaciones finalizadas'], 400);
        }

        $evaluation->load(['user', 'results']);
        
        $pdf = Pdf::loadView('pdf.evaluation', compact('evaluation'));
        
        return $pdf->stream("Evaluacion_{$evaluation->user->name}_{$evaluation->month}_{$evaluation->year}.pdf");
    }

    public function exportDashboard(Request $request)
    {
        $request->validate([
            'month' => 'required|integer',
            'year' => 'required|integer',
            'area' => 'required|string',
        ]);

        $month = $request->month;
        $year = $request->year;
        $areaName = $request->area;

        // Obtener evaluaciones de usuarios que pertenecen a esa área en el periodo dado
        $evaluations = Evaluation::with(['user', 'results'])
            ->where('month', $month)
            ->where('year', $year)
            ->whereHas('user', function ($q) use ($areaName) {
                $q->whereHas('area', function($sq) use ($areaName) {
                      $sq->where('name', $areaName);
                  });
            })
            ->get();

        $pdf = Pdf::loadView('pdf.dashboard', [
            'evaluations' => $evaluations,
            'area' => $areaName,
            'month' => $month,
            'year' => $year
        ]);

        return $pdf->stream("Dashboard_{$areaName}_{$month}_{$year}.pdf");
    }
}
