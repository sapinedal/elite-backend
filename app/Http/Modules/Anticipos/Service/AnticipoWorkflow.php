<?php

namespace App\Http\Modules\Anticipos\Service;

use App\Http\Modules\Users\Models\User;

/**
 * Definición del flujo de anticipos, en un solo lugar.
 *
 *   Radicado -> Director de Obra -> Gerencia -> Contabilidad (anticipo en Multifox) -> Completado (cuadro de pagos)
 *                     \__________________\___ Devuelto (al residente)
 *
 * Cada aprobación y el paso de Contabilidad se habilitan con permisos Spatie, así se configuran desde Roles.
 */
class AnticipoWorkflow
{
    public const STAGE_DIRECTOR = 'director_obra';
    public const STAGE_GERENCIA = 'gerencia';
    public const STEP_CONTABILIDAD = 'contabilidad';

    public const STATUS_PENDIENTE_DIRECTOR = 'pendiente_director';
    public const STATUS_PENDIENTE_GERENCIA = 'pendiente_gerencia';
    public const STATUS_DEVUELTO = 'devuelto';
    public const STATUS_PENDIENTE_CONTABILIDAD = 'pendiente_contabilidad';
    public const STATUS_COMPLETADO = 'completado';

    public const PERMISSION_VIEW_ALL = 'anticipos.ver_todos';
    public const PERMISSION_CONTABILIZAR = 'anticipos.contabilizar';

    /**
     * Aprobaciones en orden.
     */
    public const STAGES = [
        self::STAGE_DIRECTOR => [
            'label' => 'Director de Obra',
            'permission' => 'anticipos.aprobar_director',
            'status' => self::STATUS_PENDIENTE_DIRECTOR,
        ],
        self::STAGE_GERENCIA => [
            'label' => 'Gerencia',
            'permission' => 'anticipos.aprobar_gerencia',
            'status' => self::STATUS_PENDIENTE_GERENCIA,
        ],
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDIENTE_DIRECTOR => 'Pendiente Director de Obra',
        self::STATUS_PENDIENTE_GERENCIA => 'Pendiente Gerencia',
        self::STATUS_DEVUELTO => 'Devuelto',
        self::STATUS_PENDIENTE_CONTABILIDAD => 'Pendiente Contabilidad',
        self::STATUS_COMPLETADO => 'En cuadro de pagos',
    ];

    public static function firstStage(): string
    {
        return array_key_first(self::STAGES);
    }

    /**
     * Etapa siguiente, o null si $stage es la última (pasa a Contabilidad).
     */
    public static function nextStage(string $stage): ?string
    {
        $keys = array_keys(self::STAGES);
        $index = array_search($stage, $keys, true);

        return $index === false ? null : ($keys[$index + 1] ?? null);
    }

    /**
     * @return list<string>
     */
    public static function previousStages(string $stage): array
    {
        $keys = array_keys(self::STAGES);

        return array_slice($keys, 0, (int) array_search($stage, $keys, true));
    }

    public static function stageLabel(?string $stage): ?string
    {
        if ($stage === self::STEP_CONTABILIDAD) {
            return 'Contabilidad';
        }

        return $stage ? (self::STAGES[$stage]['label'] ?? $stage) : null;
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    /**
     * @return list<string>
     */
    public static function stagesFor(User $user): array
    {
        return array_values(array_filter(
            array_keys(self::STAGES),
            fn (string $stage) => $user->can(self::STAGES[$stage]['permission'])
        ));
    }

    public static function canContabilizar(User $user): bool
    {
        return $user->can(self::PERMISSION_CONTABILIZAR);
    }

    public static function toArray(): array
    {
        return [
            'stages' => array_map(
                fn (string $key) => ['key' => $key, 'label' => self::STAGES[$key]['label'], 'status' => self::STAGES[$key]['status']],
                array_keys(self::STAGES)
            ),
            'statuses' => array_map(
                fn (string $key) => ['key' => $key, 'label' => self::STATUS_LABELS[$key]],
                array_keys(self::STATUS_LABELS)
            ),
        ];
    }
}
