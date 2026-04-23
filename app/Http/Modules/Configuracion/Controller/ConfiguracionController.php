<?php

namespace App\Http\Modules\Configuracion\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Configuracion\Models\Area;
use App\Http\Modules\Configuracion\Models\Position;
use Illuminate\Http\Request;

class ConfiguracionController extends Controller
{
    public function getAreas()
    {
        return response()->json(Area::with('positions')->get());
    }

    public function storeArea(Request $request)
    {
        $area = Area::create($request->all());
        return response()->json($area, 201);
    }

    public function updateArea(Request $request, Area $area)
    {
        $area->update($request->all());
        return response()->json($area);
    }

    public function destroyArea(Area $area)
    {
        $area->delete();
        return response()->json(null, 204);
    }

    public function getPositions(Area $area)
    {
        return response()->json($area->positions);
    }

    public function storePosition(Request $request)
    {
        $position = Position::create($request->all());
        return response()->json($position, 201);
    }

    public function updatePosition(Request $request, Position $position)
    {
        $position->update($request->all());
        return response()->json($position);
    }

    public function destroyPosition(Position $position)
    {
        $position->delete();
        return response()->json(null, 204);
    }
}
