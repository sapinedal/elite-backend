<?php

namespace App\Http\Modules\Plantillas\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Plantillas\Models\KPI;
use App\Http\Modules\Users\Models\User;
use Illuminate\Http\Request;

class KPIController extends Controller
{
    public function index(User $user)
    {
        return response()->json($user->kpis()->orderBy('stage', 'asc')->get());
    }


    public function sync(Request $request, User $user)
    {
        $kpis = $request->input('kpis', []);

        // Delete KPIs not in the request
        $incomingIds = collect($kpis)->pluck('id')->filter()->toArray();
        KPI::where('user_id', $user->id)
            ->whereNotIn('id', $incomingIds)
            ->delete();

        foreach ($kpis as $kpiData) {
            if (isset($kpiData['id'])) {
                KPI::where('id', $kpiData['id'])->update([
                    'name' => $kpiData['name'],
                    'description' => $kpiData['description'] ?? null,
                    'indicators' => $kpiData['indicators'] ?? [],
                    'formula' => $kpiData['formula'] ?? null,
                    'target' => $kpiData['target'] ?? 0,
                    'unit' => $kpiData['unit'] ?? '',
                    'stage' => $kpiData['stage'] ?? '',
                    'weight' => $kpiData['weight'] ?? 0,
                    'incidence' => $kpiData['incidence'] ?? 0,
                    'lower_is_better' => $kpiData['lower_is_better'] ?? false,
                ]);
            } else {
                KPI::create([
                    'user_id' => $user->id,
                    'name' => $kpiData['name'],
                    'description' => $kpiData['description'] ?? null,
                    'indicators' => $kpiData['indicators'] ?? [],
                    'formula' => $kpiData['formula'] ?? null,
                    'target' => $kpiData['target'] ?? 0,
                    'unit' => $kpiData['unit'] ?? '',
                    'stage' => $kpiData['stage'] ?? '',
                    'weight' => $kpiData['weight'] ?? 0,
                    'incidence' => $kpiData['incidence'] ?? 0,
                    'lower_is_better' => $kpiData['lower_is_better'] ?? false,
                ]);
            }
        }

        return response()->json($user->load(['kpis' => function($query) {
            $query->orderBy('stage', 'asc');
        }]), 200);
    }

    public function destroy(KPI $kpi)
    {
        $kpi->delete();
        return response()->json(['message' => 'KPI eliminado correctamente'], 200);
    }
}

