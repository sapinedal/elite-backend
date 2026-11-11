<?php

namespace App\Http\Modules\Ftra\Service;

use App\Http\Modules\Ftra\Models\FtraRecord;
use App\Http\Modules\Users\Models\User;

/**
 * Etapas del flujo FTRA. Cada etapa revisa un estado y, al recibir a satisfacción, lo pasa al siguiente:
 *
 *   Registrada  --Supervisor-->  Seguimiento  --Director de Obra-->  Aprobada
 *        ^                            |                                  
 *        |---- Devuelta (corrige y reenvía el residente) <---------------|
 *
 * El flujo termina cuando Supervisor y Director lo reciben a satisfacción.
 */
class FtraWorkflow
{
    public const STATUS_REGISTRADA = 'Registrada';   // Pendiente del Supervisor
    public const STATUS_SEGUIMIENTO = 'Seguimiento'; // Pendiente del Director de Obra
    public const STATUS_APROBADA = 'Aprobada';       // Recibido a satisfacción por ambos
    public const STATUS_DEVUELTA = 'Devuelta';       // Devuelto al residente para corregir
    public const STATUS_RECHAZADA = 'Rechazada';     // Estado anterior (solo registros antiguos)

    public const STATUSES = [self::STATUS_REGISTRADA, self::STATUS_SEGUIMIENTO, self::STATUS_APROBADA, self::STATUS_DEVUELTA, self::STATUS_RECHAZADA];

    public const STAGES = [
        'supervisor' => [
            'label' => 'Supervisor',
            'permission' => 'ftra.revisar',
            'status' => self::STATUS_REGISTRADA,
            'next_status' => self::STATUS_SEGUIMIENTO,
            'signature' => 'supervisor_signature',
            'user_column' => 'supervisor_user_id',
            'at_column' => 'supervisor_at',
        ],
        'director_obra' => [
            'label' => 'Director de Obra',
            'permission' => 'ftra.aprobar',
            'status' => self::STATUS_SEGUIMIENTO,
            'next_status' => self::STATUS_APROBADA,
            'signature' => 'director_signature',
            'user_column' => 'director_user_id',
            'at_column' => 'director_at',
        ],
    ];

    /** Etapa que debe revisar el registro en su estado actual (null si no está pendiente de revisión). */
    public static function currentStage(FtraRecord $record): ?string
    {
        foreach (self::STAGES as $key => $stage) {
            if ($record->status === $stage['status']) {
                return $key;
            }
        }

        return null;
    }

    public static function nextStage(string $stage): ?string
    {
        $keys = array_keys(self::STAGES);
        $index = array_search($stage, $keys, true);

        return $keys[$index + 1] ?? null;
    }

    /** @return string[] Etapas anteriores a $stage */
    public static function previousStages(string $stage): array
    {
        $keys = array_keys(self::STAGES);

        return array_slice($keys, 0, (int) array_search($stage, $keys, true));
    }

    public static function stageLabel(?string $stage): ?string
    {
        return $stage ? (self::STAGES[$stage]['label'] ?? $stage) : null;
    }

    public static function canReview(User $user, FtraRecord $record): bool
    {
        $stage = self::currentStage($record);

        return $stage !== null && $user->can(self::STAGES[$stage]['permission']);
    }

    /** El residente que lo registró (o quien tenga ftra.editar) puede corregir y reenviar un FTRA devuelto. */
    public static function canResubmit(User $user, FtraRecord $record): bool
    {
        return $record->status === self::STATUS_DEVUELTA
            && ($record->registered_by_id === $user->id || $user->can('ftra.editar'));
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            self::STATUS_REGISTRADA => 'Pendiente del Supervisor',
            self::STATUS_SEGUIMIENTO => 'Pendiente del Director de Obra',
            self::STATUS_APROBADA => 'Aprobado (recibido a satisfacción)',
            self::STATUS_DEVUELTA => 'Devuelto al residente',
            self::STATUS_RECHAZADA => 'Rechazado',
            default => (string) $status,
        };
    }

    /**
     * Avance para la línea de tiempo: Registrado → Supervisor → Director de Obra.
     *
     * @return array<int, array{label: string, state: string, user: ?string}>
     */
    public static function progress(FtraRecord $record): array
    {
        $record->loadMissing(['supervisorUser:id,name', 'directorUser:id,name']);
        $current = self::currentStage($record);
        $lastReturn = $record->status === self::STATUS_DEVUELTA
            ? $record->history()->where('action', 'devuelto')->latest('id')->value('stage')
            : null;

        $steps = [];
        foreach (self::STAGES as $key => $stage) {
            $approved = $record->{$stage['user_column']} !== null && $record->status !== self::STATUS_DEVUELTA;
            $state = match (true) {
                $record->status === self::STATUS_APROBADA, $approved => 'aprobado',
                $lastReturn === $key => 'devuelto',
                $current === $key => 'actual',
                default => 'pendiente',
            };
            $user = $key === 'supervisor' ? $record->supervisorUser?->name : $record->directorUser?->name;
            $steps[] = ['key' => $key, 'label' => $stage['label'], 'state' => $state, 'user' => $state === 'aprobado' ? $user : null];
        }

        return $steps;
    }
}
