<?php

namespace App\Http\Modules\Cortes\Service;

use App\Http\Modules\Users\Models\User;

/**
 * Definición del flujo de aprobación de cortes, en un solo lugar.
 *
 *   Radicado -> Interventor -> Director de Obra -> Gerencia -> Aprobado
 *                    \______________\_________________\___ Devuelto (al residente)
 *
 * Cada etapa se habilita con un permiso Spatie, así los revisores se configuran desde Roles
 * sin tocar código. Agregar o reordenar etapas solo requiere cambiar STAGES.
 */
class CorteWorkflow
{
    public const STAGE_INTERVENTOR = 'interventor';
    public const STAGE_DIRECTOR = 'director_obra';
    public const STAGE_GERENCIA = 'gerencia';

    public const STATUS_PENDIENTE_INTERVENTOR = 'pendiente_interventor';
    public const STATUS_PENDIENTE_DIRECTOR = 'pendiente_director';
    public const STATUS_PENDIENTE_GERENCIA = 'pendiente_gerencia';
    public const STATUS_DEVUELTO = 'devuelto';
    public const STATUS_APROBADO = 'aprobado';

    public const PERMISSION_VIEW_ALL = 'cortes.ver_todos';

    /**
     * Etapas en orden de revisión.
     */
    public const STAGES = [
        self::STAGE_INTERVENTOR => [
            'label' => 'Interventor',
            'permission' => 'cortes.aprobar_interventor',
            'status' => self::STATUS_PENDIENTE_INTERVENTOR,
        ],
        self::STAGE_DIRECTOR => [
            'label' => 'Director de Obra',
            'permission' => 'cortes.aprobar_director',
            'status' => self::STATUS_PENDIENTE_DIRECTOR,
        ],
        self::STAGE_GERENCIA => [
            'label' => 'Gerencia',
            'permission' => 'cortes.aprobar_gerencia',
            'status' => self::STATUS_PENDIENTE_GERENCIA,
        ],
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDIENTE_INTERVENTOR => 'Pendiente Interventor',
        self::STATUS_PENDIENTE_DIRECTOR => 'Pendiente Director de Obra',
        self::STATUS_PENDIENTE_GERENCIA => 'Pendiente Gerencia',
        self::STATUS_DEVUELTO => 'Devuelto',
        self::STATUS_APROBADO => 'Aprobado',
    ];

    public static function firstStage(): string
    {
        return array_key_first(self::STAGES);
    }

    /**
     * Etapa siguiente, o null si $stage es la última (el corte queda aprobado).
     */
    public static function nextStage(string $stage): ?string
    {
        $keys = array_keys(self::STAGES);
        $index = array_search($stage, $keys, true);

        return $keys[$index + 1] ?? null;
    }

    /**
     * Etapas anteriores a $stage (quienes ya lo aprobaron en el ciclo actual).
     *
     * @return list<string>
     */
    public static function previousStages(string $stage): array
    {
        $keys = array_keys(self::STAGES);

        return array_slice($keys, 0, (int) array_search($stage, $keys, true));
    }

    public static function statusFor(string $stage): string
    {
        return self::STAGES[$stage]['status'];
    }

    public static function stageLabel(?string $stage): ?string
    {
        return $stage ? (self::STAGES[$stage]['label'] ?? $stage) : null;
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    /**
     * Etapas que el usuario puede revisar según sus permisos.
     *
     * @return list<string>
     */
    public static function stagesFor(User $user): array
    {
        return array_values(array_filter(
            array_keys(self::STAGES),
            fn (string $stage) => $user->can(self::STAGES[$stage]['permission'])
        ));
    }

    /**
     * Catálogo para el frontend (etiquetas de estados y etapas en orden).
     */
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
