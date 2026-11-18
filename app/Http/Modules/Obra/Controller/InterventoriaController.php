<?php

namespace App\Http\Modules\Obra\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Obra\Service\InterventoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Datos del último informe semanal de interventoría para el Módulo Técnico.
 */
class InterventoriaController extends Controller
{
    public function __construct(protected InterventoriaService $service)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $numero = $request->integer('numero') ?: null;

        return response()->json(['success' => true, 'data' => $this->service->dashboard($numero)]);
    }

    /**
     * Botón "Sincronizar": trae lo nuevo desde Drive (si está configurado) e importa el último informe.
     */
    public function sync(): JsonResponse
    {
        $summary = $this->service->sync();

        $message = match (true) {
            $summary['latest'] === null => 'No hay informes en la carpeta de interventoría.',
            (bool) $summary['failed'] => "No se pudo leer el informe N° {$summary['latest']}; se mantiene el último informe válido.",
            (bool) $summary['imported'] => "Se cargó el informe N° {$summary['latest']}.",
            default => "Ya tienes el último informe (N° {$summary['latest']}).",
        };
        if ($summary['drive'] === false) {
            $message .= ' No se pudo consultar Drive en este momento; se usó lo que ya estaba copiado.';
        }

        return response()->json([
            'success' => !$summary['failed'],
            'message' => $message,
            'data' => $this->service->dashboard(),
        ]);
    }

    /**
     * Foto del registro fotográfico (enlace firmado y temporal, para usarlo directo en <img>).
     */
    public function photo(int $numero, string $archivo)
    {
        $path = $this->service->photoPath($numero, $archivo);
        abort_unless($path, 404);

        return Storage::disk(config('interventoria.photos_disk'))->response($path, $archivo, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=14400',
        ]);
    }
}
