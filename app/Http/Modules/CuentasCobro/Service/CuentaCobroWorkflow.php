<?php

namespace App\Http\Modules\CuentasCobro\Service;

use App\Http\Modules\Cortes\Service\CorteWorkflow;
use App\Http\Modules\Users\Models\User;

/**
 * Definición del flujo de cuentas de cobro, en un solo lugar.
 *
 *   Radicada -> Director de Obra -> Gerencia -> Paula (¿requiere Multifox?)
 *                     \__________________\___ Devuelta     |-- No -> Cuadro de pagos
 *                                                          '-- Sí -> Pendiente Multifox -> (cargada) -> Cuadro de pagos
 *
 * Las aprobaciones se habilitan con permisos Spatie. Paula se identifica igual que en Cortes (cargo de
 * cortes.multifox_positions o permiso cortes.multifox).
 */
class CuentaCobroWorkflow
{
    public const STAGE_DIRECTOR = 'director_obra';
    public const STAGE_GERENCIA = 'gerencia';
    public const STEP_MULTIFOX = 'multifox';

    public const STATUS_PENDIENTE_DIRECTOR = 'pendiente_director';
    public const STATUS_PENDIENTE_GERENCIA = 'pendiente_gerencia';
    public const STATUS_DEVUELTA = 'devuelta';
    public const STATUS_PENDIENTE_REVISION = 'pendiente_revision'; // Paula decide si requiere Multifox
    public const STATUS_PENDIENTE_MULTIFOX = 'pendiente_multifox'; // Requiere Multifox y aún no se carga
    public const STATUS_EN_PAGOS = 'en_pagos';

    public const PERMISSION_VIEW_ALL = 'cuentas_cobro.ver_todos';
    public const PERMISSION_PARAMETRIZAR = 'cuentas_cobro.parametrizar';

    public const STAGES = [
        self::STAGE_DIRECTOR => [
            'label' => 'Director de Obra',
            'permission' => 'cuentas_cobro.aprobar_director',
            'status' => self::STATUS_PENDIENTE_DIRECTOR,
        ],
        self::STAGE_GERENCIA => [
            'label' => 'Gerencia',
            'permission' => 'cuentas_cobro.aprobar_gerencia',
            'status' => self::STATUS_PENDIENTE_GERENCIA,
        ],
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDIENTE_DIRECTOR => 'Pendiente Director de Obra',
        self::STATUS_PENDIENTE_GERENCIA => 'Pendiente Gerencia',
        self::STATUS_DEVUELTA => 'Devuelta',
        self::STATUS_PENDIENTE_REVISION => 'Pendiente revisión Multifox',
        self::STATUS_PENDIENTE_MULTIFOX => 'Pendiente cargue en Multifox',
        self::STATUS_EN_PAGOS => 'En cuadro de pagos',
    ];

    // Estados en los que Paula actúa
    public const MULTIFOX_STATUSES = [self::STATUS_PENDIENTE_REVISION, self::STATUS_PENDIENTE_MULTIFOX];

    public static function firstStage(): string
    {
        return array_key_first(self::STAGES);
    }

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
        if ($stage === self::STEP_MULTIFOX) {
            return 'Multifox';
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

    /**
     * Paula: quien registra en Multifox (mismo criterio que en Cortes).
     */
    public static function canHandleMultifox(User $user): bool
    {
        return CorteWorkflow::canConfirmMultifox($user);
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
