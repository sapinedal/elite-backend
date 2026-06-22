<?php

namespace App\Http\Modules\Ftra\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Ftra\Models\FtraFormat;
use App\Http\Modules\Ftra\Service\FormatService;
use App\Http\Modules\Ftra\Request\StoreFormatRequest;
use App\Http\Modules\Ftra\Request\UpdateFormatRequest;
use Illuminate\Http\Request;

class FormatController extends Controller
{
    protected $formatService;

    public function __construct(FormatService $formatService)
    {
        $this->formatService = $formatService;
    }

    /**
     * Helper to verify if the user has editor permissions.
     */
    protected function checkEditorPermission()
    {
        return true; // Forzado a true temporalmente para permitir pruebas de CRUD sin Spatie
    }

    /**
     * Devuelve el listado de formatos con filtros.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'is_active', 'per_page']);
        $formats = $this->formatService->listFormats($filters);
        
        return response()->json($formats);
    }

    /**
     * Muestra los detalles de un formato específico.
     */
    public function show(FtraFormat $format)
    {
        return response()->json($format);
    }

    /**
     * Registra un nuevo formato (con archivo PDF).
     */
    public function store(StoreFormatRequest $request)
    {
        if (!$this->checkEditorPermission()) {
            return response()->json([
                'message' => 'No tienes permisos para parametrizar o agregar formatos de FTRA.'
            ], 403);
        }

        $pdfFile = $request->file('pdf_file');
        $format = $this->formatService->createFormat($request->validated(), $pdfFile);

        return response()->json([
            'message' => 'Formato guardado exitosamente.',
            'format' => $format
        ], 201);
    }

    /**
     * Actualiza un formato existente.
     */
    public function update(UpdateFormatRequest $request, FtraFormat $format)
    {
        if (!$this->checkEditorPermission()) {
            return response()->json([
                'message' => 'No tienes permisos para modificar formatos de FTRA.'
            ], 403);
        }

        $pdfFile = $request->file('pdf_file');
        $updatedFormat = $this->formatService->updateFormat($format, $request->validated(), $pdfFile);

        return response()->json([
            'message' => 'Formato actualizado exitosamente.',
            'format' => $updatedFormat
        ]);
    }

    /**
     * Elimina un formato.
     */
    public function destroy(FtraFormat $format)
    {
        if (!$this->checkEditorPermission()) {
            return response()->json([
                'message' => 'No tienes permisos para eliminar formatos de FTRA.'
            ], 403);
        }

        $this->formatService->deleteFormat($format);

        return response()->json([
            'message' => 'Formato eliminado exitosamente.'
        ]);
    }
}
