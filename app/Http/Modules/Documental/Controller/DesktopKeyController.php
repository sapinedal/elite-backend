<?php

namespace App\Http\Modules\Documental\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Documental\Service\DesktopKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Claves de escritorio del usuario autenticado (cada quien gestiona solo las suyas).
 */
class DesktopKeyController extends Controller
{
    public function __construct(protected DesktopKeyService $keys)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'keys' => $this->keys->list($request->user()),
                'url' => config('documental.dav_url'),
                'username' => $request->user()->email,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']], [], ['name' => 'nombre del equipo']);

        try {
            return response()->json(['success' => true, 'data' => $this->keys->create($request->user(), $data['name'])], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, int $key): JsonResponse
    {
        try {
            $this->keys->revoke($request->user(), $key);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }

        return response()->json(['success' => true, 'message' => 'Clave revocada. Ese equipo ya no podrá conectarse.']);
    }
}
