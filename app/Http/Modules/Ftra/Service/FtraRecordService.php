<?php

namespace App\Http\Modules\Ftra\Service;

use App\Http\Modules\Ftra\Models\FtraRecord;
use App\Http\Modules\Ftra\Models\FtraRecordPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class FtraRecordService
{
    /**
     * Obtiene y filtra la lista de registros FTRA.
     */
    public function listRecords(array $filters = [])
    {
        $query = FtraRecord::query()->with(['contractor', 'format', 'photos', 'registeredBy:id,name']);

        if (!empty($filters['contractor_id'])) {
            $query->where('contractor_id', $filters['contractor_id']);
        }

        if (!empty($filters['format_id'])) {
            $query->where('format_id', $filters['format_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('contractor', function ($sub) use ($search) {
                    $sub->where('name', 'like', '%' . $search . '%');
                })->orWhereHas('format', function ($sub) use ($search) {
                    $sub->where('name', 'like', '%' . $search . '%')
                        ->orWhere('code', 'like', '%' . $search . '%');
                });
            });
        }

        $perPage = intval($filters['per_page'] ?? 15);

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Crea un registro FTRA con múltiples fotos.
     */
    public function createRecord(array $data, int $userId, ?array $photos = null): FtraRecord
    {
        return DB::transaction(function () use ($data, $userId, $photos) {
            $data['registered_by_id'] = $userId;
            $data['status'] = $data['status'] ?? 'Registrada';

            $record = FtraRecord::create($data);

            // Almacenar las fotos
            if ($photos) {
                foreach ($photos as $photoFile) {
                    if ($photoFile instanceof UploadedFile) {
                        $path = $photoFile->store('ftra_photos', 'public');
                        FtraRecordPhoto::create([
                            'ftra_record_id' => $record->id,
                            'photo_path' => $path,
                        ]);
                    }
                }
            }

            return $record->load(['contractor', 'format', 'photos', 'registeredBy:id,name']);
        });
    }

    /**
     * Actualiza un registro FTRA y añade opcionalmente nuevas fotos.
     */
    public function updateRecord(FtraRecord $record, array $data, ?array $photos = null): FtraRecord
    {
        return DB::transaction(function () use ($record, $data, $photos) {
            $record->update($data);

            // Añadir fotos adicionales si vienen
            if ($photos) {
                foreach ($photos as $photoFile) {
                    if ($photoFile instanceof UploadedFile) {
                        $path = $photoFile->store('ftra_photos', 'public');
                        FtraRecordPhoto::create([
                            'ftra_record_id' => $record->id,
                            'photo_path' => $path,
                        ]);
                    }
                }
            }

            return $record->load(['contractor', 'format', 'photos', 'registeredBy:id,name']);
        });
    }

    /**
     * Actualiza únicamente el estado del registro.
     */
    public function updateStatus(FtraRecord $record, string $status): FtraRecord
    {
        return DB::transaction(function () use ($record, $status) {
            $record->update(['status' => $status]);
            return $record->load(['contractor', 'format', 'photos', 'registeredBy:id,name']);
        });
    }

    /**
     * Elimina el registro y sus fotos físicas asociadas.
     */
    public function deleteRecord(FtraRecord $record): void
    {
        DB::transaction(function () use ($record) {
            // Borrar archivos físicos
            foreach ($record->photos as $photo) {
                if ($photo->photo_path) {
                    Storage::disk('public')->delete($photo->photo_path);
                }
            }
            // Eliminar de base de datos
            $record->delete();
        });
    }
}
