<?php

namespace App\Http\Modules\Ordenes\Service;

use App\Http\Modules\Users\Models\User;
use Illuminate\Support\Str;

/**
 * Definición del flujo de órdenes, en un solo lugar.
 *
 *   Registrada -> Director de Obra -> Gerencia -> Orden ERP -> Factura proveedor -> Entrada almacén -> Completada
 *                       \__________________\___ Devuelta (al residente)
 *
 * Las aprobaciones (STAGES) se habilitan con permisos Spatie. Los documentos (STEPS) con un permiso o, en los
 * pasos de Almacén, también con el cargo configurado en ordenes.almacen_positions.
 */
class OrdenWorkflow
{
    public const STAGE_DIRECTOR = 'director_obra';
    public const STAGE_GERENCIA = 'gerencia';

    public const STEP_ORDEN_ERP = 'orden_erp';
    public const STEP_FACTURA = 'factura';
    public const STEP_ENTRADA = 'entrada';

    public const STATUS_PENDIENTE_DIRECTOR = 'pendiente_director';
    public const STATUS_PENDIENTE_GERENCIA = 'pendiente_gerencia';
    public const STATUS_DEVUELTO = 'devuelto';
    public const STATUS_PENDIENTE_ERP = 'pendiente_erp';
    public const STATUS_PENDIENTE_FACTURA = 'pendiente_factura';
    public const STATUS_PENDIENTE_ENTRADA = 'pendiente_entrada';
    public const STATUS_COMPLETADA = 'completada';

    public const PERMISSION_VIEW_ALL = 'ordenes.ver_todos';
    public const PERMISSION_ALMACEN = 'ordenes.gestionar_almacen';

    /**
     * Aprobaciones en orden.
     */
    public const STAGES = [
        self::STAGE_DIRECTOR => [
            'label' => 'Director de Obra',
            'permission' => 'ordenes.aprobar_director',
            'status' => self::STATUS_PENDIENTE_DIRECTOR,
        ],
        self::STAGE_GERENCIA => [
            'label' => 'Gerencia',
            'permission' => 'ordenes.aprobar_gerencia',
            'status' => self::STATUS_PENDIENTE_GERENCIA,
        ],
    ];

    /**
     * Documentos que se cargan después de la aprobación, en orden. `folder` es la subcarpeta S3 de la orden.
     */
    public const STEPS = [
        self::STEP_ORDEN_ERP => [
            'label' => 'Orden de compra ERP',
            'responsible' => 'Almacén',
            'permission' => self::PERMISSION_ALMACEN,
            'by_position' => true,
            'status' => self::STATUS_PENDIENTE_ERP,
            'folder' => '01 Orden ERP',
            'number_label' => 'N.º de orden de compra',
        ],
        self::STEP_FACTURA => [
            'label' => 'Factura del proveedor',
            'responsible' => 'Facturación',
            'permission' => 'ordenes.facturar',
            'by_position' => false,
            'status' => self::STATUS_PENDIENTE_FACTURA,
            'folder' => '02 Factura proveedor',
            'number_label' => 'N.º de factura',
        ],
        self::STEP_ENTRADA => [
            'label' => 'Entrada de almacén',
            'responsible' => 'Almacén',
            'permission' => self::PERMISSION_ALMACEN,
            'by_position' => true,
            'status' => self::STATUS_PENDIENTE_ENTRADA,
            'folder' => '03 Entrada almacen',
            'number_label' => 'N.º de entrada',
        ],
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDIENTE_DIRECTOR => 'Pendiente Director de Obra',
        self::STATUS_PENDIENTE_GERENCIA => 'Pendiente Gerencia',
        self::STATUS_DEVUELTO => 'Devuelta',
        self::STATUS_PENDIENTE_ERP => 'Pendiente orden ERP',
        self::STATUS_PENDIENTE_FACTURA => 'Pendiente factura',
        self::STATUS_PENDIENTE_ENTRADA => 'Pendiente entrada',
        self::STATUS_COMPLETADA => 'Completada',
    ];

    public static function firstStage(): string
    {
        return array_key_first(self::STAGES);
    }

    public static function firstStep(): string
    {
        return array_key_first(self::STEPS);
    }

    /**
     * Etapa siguiente, o null si $stage es la última (la orden queda aprobada).
     */
    public static function nextStage(string $stage): ?string
    {
        return self::nextKey(self::STAGES, $stage);
    }

    /**
     * Documento siguiente, o null si $step es el último (la orden queda completada).
     */
    public static function nextStep(string $step): ?string
    {
        return self::nextKey(self::STEPS, $step);
    }

    protected static function nextKey(array $list, string $key): ?string
    {
        $keys = array_keys($list);
        $index = array_search($key, $keys, true);

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

    public static function stageLabel(?string $key): ?string
    {
        if (!$key) {
            return null;
        }

        return self::STAGES[$key]['label'] ?? self::STEPS[$key]['label'] ?? $key;
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    /**
     * Aprobaciones que el usuario puede hacer según sus permisos.
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
     * Documentos que el usuario puede cargar: por permiso o, en los pasos de Almacén, por su cargo.
     *
     * @return list<string>
     */
    public static function stepsFor(User $user): array
    {
        $isAlmacen = self::hasAlmacenPosition($user);

        return array_values(array_filter(
            array_keys(self::STEPS),
            fn (string $step) => $user->can(self::STEPS[$step]['permission']) || (self::STEPS[$step]['by_position'] && $isAlmacen)
        ));
    }

    public static function hasAlmacenPosition(User $user): bool
    {
        $user->loadMissing('position:id,name');
        if (!$user->position) {
            return false;
        }

        $positions = collect(config('ordenes.almacen_positions', []))->map(fn ($name) => self::comparable($name));

        return $positions->contains(self::comparable($user->position->name));
    }

    /**
     * Texto comparable sin mayúsculas ni tildes (para nombres de cargos).
     */
    public static function comparable(string $value): string
    {
        return trim(Str::lower(Str::ascii($value)));
    }

    /**
     * Catálogo para el frontend.
     */
    public static function toArray(): array
    {
        return [
            'stages' => array_map(
                fn (string $key) => ['key' => $key, 'label' => self::STAGES[$key]['label'], 'status' => self::STAGES[$key]['status']],
                array_keys(self::STAGES)
            ),
            'steps' => array_map(
                fn (string $key) => [
                    'key' => $key,
                    'label' => self::STEPS[$key]['label'],
                    'responsible' => self::STEPS[$key]['responsible'],
                    'status' => self::STEPS[$key]['status'],
                    'number_label' => self::STEPS[$key]['number_label'],
                ],
                array_keys(self::STEPS)
            ),
            'statuses' => array_map(
                fn (string $key) => ['key' => $key, 'label' => self::STATUS_LABELS[$key]],
                array_keys(self::STATUS_LABELS)
            ),
        ];
    }
}
