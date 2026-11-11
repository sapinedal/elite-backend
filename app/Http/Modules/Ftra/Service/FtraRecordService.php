<?php

namespace App\Http\Modules\Ftra\Service;

use App\Http\Modules\Configuracion\Models\Tower;
use App\Http\Modules\Ftra\Models\FtraRecord;
use App\Http\Modules\Ftra\Models\FtraRecordHistory;
use App\Http\Modules\Ftra\Models\FtraRecordPhoto;
use App\Http\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class FtraRecordService
{
    public function __construct(protected FtraNotificationService $notifications)
    {
    }

    /**
     * Obtiene y filtra la lista de registros FTRA.
     */
    public function listRecords(User $user, ?int $projectId, array $filters = [])
    {
        $perPage = intval($filters['per_page'] ?? 15);

        return $this->filteredQuery($user, $projectId, $filters)
            ->with(FtraRecord::DETAIL_RELATIONS)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Registros del proyecto con los filtros de Seguimiento (los usa el listado y el informe en Excel).
     */
    public function filteredQuery(User $user, ?int $projectId, array $filters = [])
    {
        $query = FtraRecord::query()->accessibleBy($user);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        if (!empty($filters['contractor_id'])) {
            $query->where('contractor_id', $filters['contractor_id']);
        }

        if (!empty($filters['format_id'])) {
            $query->where('format_id', $filters['format_id']);
        }

        if (!empty($filters['tower_id'])) {
            $query->where('tower_id', $filters['tower_id']);
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

        return $query;
    }

    /**
     * Semaforización: por apartamento, el último FTRA de cada formato (tipo de FTRA).
     * El frontend pinta en gris los que están en proceso y en verde los aprobados por Supervisor y Director.
     */
    public function unitsStatus(Tower $tower): array
    {
        return FtraRecord::query()
            ->where('tower_id', $tower->id)
            ->with('contractor:id,name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'tower_id', 'contractor_id', 'format_id', 'piso', 'apartamento', 'status', 'resultado_inspeccion', 'created_at'])
            ->groupBy('apartamento')
            ->map(fn ($records, $apartamento) => [
                'apartamento' => (string) $apartamento,
                'piso' => $records->last()->piso,
                'records_count' => $records->count(),
                'formats' => $records->groupBy('format_id')->map(fn ($byFormat) => [
                    'format_id' => $byFormat->last()->format_id,
                    'record_id' => $byFormat->last()->id,
                    'status' => $byFormat->last()->status,
                    'resultado_inspeccion' => $byFormat->last()->resultado_inspeccion,
                    'contractor' => $byFormat->last()->contractor?->name,
                    'created_at' => $byFormat->last()->created_at,
                ])->values(),
            ])
            ->values()
            ->all();
    }

    /**
     * El Supervisor o el Director de Obra recibe a satisfacción (firma) y el FTRA avanza a la siguiente etapa.
     */
    public function approve(FtraRecord $record, User $user, string $signature, ?string $comment): FtraRecord
    {
        $stage = FtraWorkflow::currentStage($record);
        $config = FtraWorkflow::STAGES[$stage];

        DB::transaction(function () use ($record, $user, $signature, $comment, $stage, $config) {
            $record->update([
                $config['signature'] => $signature,
                $config['user_column'] => $user->id,
                $config['at_column'] => now(),
                'status' => $config['next_status'],
            ]);
            $this->log($record, $user, FtraRecordHistory::ACTION_APROBADO, $stage, $comment);
        });

        $this->notifications->approved($record->fresh(), $stage, $comment);

        return $record->fresh()->load(FtraRecord::DETAIL_RELATIONS);
    }

    /**
     * El Supervisor o el Director devuelve el FTRA al residente con el motivo.
     */
    public function returnRecord(FtraRecord $record, User $user, string $comment): FtraRecord
    {
        $stage = FtraWorkflow::currentStage($record);

        DB::transaction(function () use ($record, $user, $comment, $stage) {
            $record->update(['status' => FtraWorkflow::STATUS_DEVUELTA]);
            $this->log($record, $user, FtraRecordHistory::ACTION_DEVUELTO, $stage, $comment);
        });

        $this->notifications->returned($record->fresh(), $stage, $comment);

        return $record->fresh()->load(FtraRecord::DETAIL_RELATIONS);
    }

    /**
     * El residente corrige un FTRA devuelto y lo reenvía: se borran las firmas de revisión y comienza de nuevo con el Supervisor.
     */
    public function resubmit(FtraRecord $record, User $user, array $data, ?array $photos, ?string $comment): FtraRecord
    {
        DB::transaction(function () use ($record, $user, $data, $photos, $comment) {
            if (isset($data['resultado_inspeccion'])) {
                $data['is_completed'] = ($data['resultado_inspeccion'] !== 'Rechazado');
            }
            $record->update([
                ...$data,
                'status' => FtraWorkflow::STATUS_REGISTRADA,
                'supervisor_signature' => null, 'supervisor_user_id' => null, 'supervisor_at' => null,
                'director_signature' => null, 'director_user_id' => null, 'director_at' => null,
            ]);
            $this->storePhotos($record, $photos);
            $this->log($record, $user, FtraRecordHistory::ACTION_REENVIADO, null, $comment);
        });

        $this->notifications->submitted($record->fresh(), true, $comment);

        return $record->fresh()->load(FtraRecord::DETAIL_RELATIONS);
    }

    protected function log(FtraRecord $record, User $user, string $action, ?string $stage, ?string $comment): void
    {
        FtraRecordHistory::create([
            'ftra_record_id' => $record->id,
            'user_id' => $user->id,
            'action' => $action,
            'stage' => $stage,
            'comment' => $comment ?: null,
        ]);
    }

    protected function storePhotos(FtraRecord $record, ?array $photos): void
    {
        foreach ($photos ?? [] as $photoFile) {
            if ($photoFile instanceof UploadedFile) {
                FtraRecordPhoto::create([
                    'ftra_record_id' => $record->id,
                    'photo_path' => $photoFile->store('ftra_photos', 'public'),
                ]);
            }
        }
    }

    /**
     * Crea un registro FTRA con múltiples fotos.
     */
    public function createRecord(array $data, int $userId, ?array $photos = null): FtraRecord
    {
        $record = DB::transaction(function () use ($data, $userId, $photos) {
            $data['registered_by_id'] = $userId;
            // El responsable de la revisión es el residente que registra (usuario logueado)
            $data['responsable_user_id'] = $userId;
            $data['status'] = FtraWorkflow::STATUS_REGISTRADA;
            if (isset($data['resultado_inspeccion'])) {
                $data['is_completed'] = ($data['resultado_inspeccion'] !== 'Rechazado');
            }

            $record = FtraRecord::create($data);

            $this->storePhotos($record, $photos);

            $this->log($record, User::findOrFail($userId), FtraRecordHistory::ACTION_REGISTRADO, null, $data['observations'] ?? null);

            return $record;
        });

        // Fuera de la transacción: el correo no debe revertir el registro
        $this->notifications->submitted($record, false, $record->observations);

        return $record->load(FtraRecord::DETAIL_RELATIONS);
    }

    /**
     * Actualiza un registro FTRA y añade opcionalmente nuevas fotos.
     */
    public function updateRecord(FtraRecord $record, array $data, ?array $photos = null): FtraRecord
    {
        return DB::transaction(function () use ($record, $data, $photos) {
            if (isset($data['resultado_inspeccion'])) {
                $data['is_completed'] = ($data['resultado_inspeccion'] !== 'Rechazado');
            }
            $record->update($data);
            $this->storePhotos($record, $photos);

            return $record->load(FtraRecord::DETAIL_RELATIONS);
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
