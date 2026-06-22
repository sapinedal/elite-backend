<?php

namespace App\Http\Modules\Ftra\Service;

use App\Http\Modules\Ftra\Models\FtraFormat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class FormatService
{
    /**
     * Obtiene y filtra la lista de formatos.
     */
    public function listFormats(array $filters = [])
    {
        $query = FtraFormat::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('code', 'like', '%' . $search . '%');
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = intval($filters['per_page'] ?? 15);
        
        return $query->orderBy('code', 'asc')->paginate($perPage);
    }

    /**
     * Crea un formato guardando su PDF.
     */
    public function createFormat(array $data, UploadedFile $pdfFile): FtraFormat
    {
        return DB::transaction(function () use ($data, $pdfFile) {
            // Guardamos el PDF en storage/app/public/formats
            $pdfPath = $pdfFile->store('formats', 'public');
            
            $data['pdf_path'] = $pdfPath;
            $data['is_active'] = isset($data['is_active']) ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) : true;

            return FtraFormat::create($data);
        });
    }

    /**
     * Actualiza un formato reemplazando opcionalmente el PDF.
     */
    public function updateFormat(FtraFormat $format, array $data, ?UploadedFile $pdfFile = null): FtraFormat
    {
        return DB::transaction(function () use ($format, $data, $pdfFile) {
            if ($pdfFile) {
                // Borramos el PDF anterior si existe
                if ($format->pdf_path) {
                    Storage::disk('public')->delete($format->pdf_path);
                }
                
                // Guardamos el nuevo PDF
                $pdfPath = $pdfFile->store('formats', 'public');
                $data['pdf_path'] = $pdfPath;
            }

            if (isset($data['is_active'])) {
                $data['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
            }

            $format->update($data);
            return $format;
        });
    }

    /**
     * Elimina un formato y su archivo PDF.
     */
    public function deleteFormat(FtraFormat $format): void
    {
        DB::transaction(function () use ($format) {
            if ($format->pdf_path) {
                Storage::disk('public')->delete($format->pdf_path);
            }
            $format->delete();
        });
    }
}
