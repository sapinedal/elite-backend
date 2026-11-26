<?php

namespace App\Http\Modules\CuentasCobro\Service;

use App\Http\Modules\Cortes\Service\CorteWorkflow;
use App\Http\Modules\Users\Models\User;

/**
 * Definición del flujo de cuentas de cobro, en un solo lugar.
 *
 *   Radicada -> Director de Obra -> Gerencia -> Paula (¿requiere Multifox?)
 *                     \__________________\___ Devuelta     |-- No -> Aprobación de Contabilidad -> Cuadro de pagos
 *                                                          '-- Sí -> Pendiente Multifox -> (cargada) -> Aprobación de Contabilidad -> Cuadro de pagos
 *   Si Contabilidad la devuelve, vuelve a Paula (revisión o cargue de Multifox).
 *
 * Las cuentas directas (con frente / torre) siguen el flujo de cortes y órdenes en lugar de Multifox:
 *
 *   ... -> Gerencia -> Cargue ERP -> Factura (se asocia desde Facturas del mes) -> Liquidación ERP
 *       -> Aprobación de Contabilidad -> Cuadro de pagos   (si Contabilidad la devuelve, vuelve a la liquidación)
 *
 * El cargue ERP y la liquidación los hace quien radicó la cuenta o quien tenga cuentas_cobro.cargar_erp.
 *
 * Las aprobaciones se habilitan con permisos Spatie. Paula se identifica igual que en Cortes
 * (permiso cortes.multifox).
 */
class CuentaCobroWorkflow
{
    public const STAGE_DIRECTOR = 'director_obra';
    public const STAGE_GERENCIA = 'gerencia';
    public const STEP_MULTIFOX = 'multifox';
    public const STEP_APROBACION_CONTABLE = 'aprobacion_contable';
    public const STEP_ERP = 'erp';
    public const STEP_FACTURA = 'factura';
    public const STEP_LIQUIDACION = 'liquidacion_erp';

    public const STATUS_PENDIENTE_DIRECTOR = 'pendiente_director';
    public const STATUS_PENDIENTE_GERENCIA = 'pendiente_gerencia';
    public const STATUS_DEVUELTA = 'devuelta';
    public const STATUS_PENDIENTE_REVISION = 'pendiente_revision'; // Paula decide si requiere Multifox
    public const STATUS_PENDIENTE_MULTIFOX = 'pendiente_multifox'; // Requiere Multifox y aún no se carga
    public const STATUS_PENDIENTE_APROBACION_CONTABLE = 'pendiente_aprobacion_contable'; // Falta la aprobación de Contabilidad
    public const STATUS_EN_PAGOS = 'en_pagos';
    public const STATUS_PENDIENTE_ERP = 'pendiente_erp';
    public const STATUS_PENDIENTE_FACTURA = 'pendiente_factura';
    public const STATUS_PENDIENTE_LIQUIDACION = 'pendiente_liquidacion';

    public const PERMISSION_VIEW_ALL = 'cuentas_cobro.ver_todos';
    public const PERMISSION_PARAMETRIZAR = 'cuentas_cobro.parametrizar';
    public const PERMISSION_ACCOUNTING = 'cuentas_cobro.aprobar_contabilidad';
    public const PERMISSION_ERP = 'cuentas_cobro.cargar_erp';

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

    /**
     * Documentos de las cuentas directas, en orden. La factura no se carga aquí: el residente la asocia desde
     * Facturas del mes. `folder` es la subcarpeta S3 dentro de la carpeta de la cuenta.
     */
    public const STEPS = [
        self::STEP_ERP => [
            'label' => 'Cargue ERP',
            'status' => self::STATUS_PENDIENTE_ERP,
            'folder' => '01 Cargue ERP',
            'number_label' => 'N.º de documento ERP',
        ],
        self::STEP_FACTURA => [
            'label' => 'Factura',
            'status' => self::STATUS_PENDIENTE_FACTURA,
            'folder' => '02 Factura',
            'number_label' => 'N.º de factura',
        ],
        self::STEP_LIQUIDACION => [
            'label' => 'Liquidación ERP',
            'status' => self::STATUS_PENDIENTE_LIQUIDACION,
            'folder' => '03 Liquidacion ERP',
            'number_label' => 'N.º de liquidación ERP',
        ],
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDIENTE_DIRECTOR => 'Pendiente Director de Obra',
        self::STATUS_PENDIENTE_GERENCIA => 'Pendiente Gerencia',
        self::STATUS_DEVUELTA => 'Devuelta',
        self::STATUS_PENDIENTE_REVISION => 'Pendiente revisión Multifox',
        self::STATUS_PENDIENTE_MULTIFOX => 'Pendiente cargue en Multifox',
        self::STATUS_PENDIENTE_APROBACION_CONTABLE => 'Pendiente aprobación Contabilidad',
        self::STATUS_PENDIENTE_ERP => 'Pendiente cargue ERP',
        self::STATUS_PENDIENTE_FACTURA => 'Pendiente factura',
        self::STATUS_PENDIENTE_LIQUIDACION => 'Pendiente liquidación ERP',
        self::STATUS_EN_PAGOS => 'En cuadro de pagos',
    ];

    // Estados en los que Paula actúa
    public const MULTIFOX_STATUSES = [self::STATUS_PENDIENTE_REVISION, self::STATUS_PENDIENTE_MULTIFOX];

    public static function firstStage(): string
    {
        return array_key_first(self::STAGES);
    }

    public static function firstStep(): string
    {
        return array_key_first(self::STEPS);
    }

    /**
     * Documento siguiente, o null si $step es el último (pasa a la aprobación de Contabilidad).
     */
    public static function nextStep(string $step): ?string
    {
        $keys = array_keys(self::STEPS);
        $index = array_search($step, $keys, true);

        return $index === false ? null : ($keys[$index + 1] ?? null);
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
        if ($stage === self::STEP_APROBACION_CONTABLE) {
            return 'Aprobación Contabilidad';
        }

        return $stage ? (self::STAGES[$stage]['label'] ?? self::STEPS[$stage]['label'] ?? $stage) : null;
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

    public static function canLoadErp(User $user): bool
    {
        return $user->can(self::PERMISSION_ERP);
    }

    public static function canApproveAccounting(User $user): bool
    {
        return $user->can(self::PERMISSION_ACCOUNTING);
    }

    public static function toArray(): array
    {
        return [
            'stages' => array_map(
                fn (string $key) => ['key' => $key, 'label' => self::STAGES[$key]['label'], 'status' => self::STAGES[$key]['status']],
                array_keys(self::STAGES)
            ),
            'steps' => array_map(
                fn (string $key) => ['key' => $key, 'label' => self::STEPS[$key]['label'], 'status' => self::STEPS[$key]['status'], 'number_label' => self::STEPS[$key]['number_label']],
                array_keys(self::STEPS)
            ),
            'statuses' => array_map(
                fn (string $key) => ['key' => $key, 'label' => self::STATUS_LABELS[$key]],
                array_keys(self::STATUS_LABELS)
            ),
        ];
    }
}
