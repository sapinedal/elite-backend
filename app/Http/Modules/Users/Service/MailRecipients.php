<?php

namespace App\Http\Modules\Users\Service;

use App\Http\Modules\Users\Models\User;
use Illuminate\Support\Collection;

/**
 * Destinatarios de los correos de la herramienta por permiso.
 *
 * Cada correo que envía Elite tiene un permiso "correos.<módulo>.<destinatario>". Lo reciben los usuarios que
 * tienen ese permiso asignado DIRECTAMENTE (no por rol: admin y gerencia tienen todos los permisos por rol y
 * no deben recibirlo todo) y acceso al proyecto del registro. Los correos de config/*.php (.env) quedan solo
 * para buzones externos que no son usuarios de Elite.
 */
class MailRecipients
{
    public const PREFIX = 'correos';

    /** Permiso => descripción que se muestra en Permisos y en la ficha del usuario. */
    public const PERMISSIONS = [
        'correos.cortes.interventor' => 'Cortes: recibe los cortes radicados para la revisión del Interventor (supervisión)',
        'correos.cortes.director_obra' => 'Cortes: recibe los cortes aprobados por el Interventor para la revisión del Director de Obra',
        'correos.cortes.gerencia' => 'Cortes: recibe los cortes aprobados por el Director para la revisión de Gerencia',
        'correos.cortes.residentes' => 'Cortes: recibe los avisos al residente (radicado, avance, devuelto y aprobado)',
        'correos.cortes.facturacion' => 'Cortes: recibe los cortes con las tres aprobaciones para facturar',
        'correos.cortes.multifox' => 'Cortes: recibe las facturas cargadas para registrarlas en Multifox',
        'correos.cortes.copia' => 'Cortes: en copia de todos los correos del flujo',

        'correos.ftra.supervisor' => 'FTRA: recibe los FTRA registrados para la revisión del Supervisor',
        'correos.ftra.director_obra' => 'FTRA: recibe los FTRA recibidos por el Supervisor para la revisión del Director de Obra',
        'correos.ftra.copia' => 'FTRA: en copia de todos los correos del flujo',

        'correos.anticipos.director_obra' => 'Anticipos: recibe los anticipos radicados para la aprobación del Director de Obra',
        'correos.anticipos.gerencia' => 'Anticipos: recibe los anticipos aprobados por el Director para la aprobación de Gerencia',
        'correos.anticipos.contabilidad' => 'Anticipos: recibe los anticipos aprobados para generarlos en Multifox',
        'correos.anticipos.copia' => 'Anticipos: en copia de todos los correos del flujo',

        'correos.cuentas_cobro.director_obra' => 'Cuentas de cobro: recibe las cuentas radicadas para la aprobación del Director de Obra',
        'correos.cuentas_cobro.gerencia' => 'Cuentas de cobro: recibe las cuentas aprobadas por el Director para la aprobación de Gerencia',
        'correos.cuentas_cobro.multifox' => 'Cuentas de cobro: recibe las cuentas aprobadas para decidir y cargar Multifox',
        'correos.cuentas_cobro.copia' => 'Cuentas de cobro: en copia de todos los correos del flujo',

        'correos.ordenes.director_obra' => 'Órdenes: recibe las órdenes registradas para la aprobación del Director de Obra',
        'correos.ordenes.gerencia' => 'Órdenes: recibe las órdenes aprobadas por el Director para la aprobación de Gerencia',
        'correos.ordenes.almacen' => 'Órdenes: recibe las órdenes aprobadas y las facturas para cargar la orden ERP y la entrada de almacén',
        'correos.ordenes.facturacion' => 'Órdenes: recibe las órdenes con orden de compra ERP para cargar la factura del proveedor',
        'correos.ordenes.copia' => 'Órdenes: en copia de todos los correos del flujo',

        'correos.contratacion.polizas' => 'Contratación: recibe las alertas de pólizas próximas a vencer (40, 30 y 20 días)',
    ];

    public static function permission(string $module, string $recipient): string
    {
        return self::PREFIX . ".{$module}.{$recipient}";
    }

    /**
     * Correos de los usuarios con el permiso asignado directamente y, si se indica, acceso al proyecto.
     */
    public static function emails(string $permission, ?int $projectId = null): Collection
    {
        return User::query()
            ->withDirectPermission($permission)
            ->whereNotNull('email')
            ->get()
            ->filter(fn (User $user) => $projectId === null || $user->canAccessProject($projectId))
            ->pluck('email')
            ->values();
    }

    /**
     * Usuarios con el permiso "correos.<módulo>.<destinatario>" más los correos externos de config.
     */
    public static function for(string $module, string $recipient, ?int $projectId = null, array $external = []): Collection
    {
        return collect($external)->merge(self::emails(self::permission($module, $recipient), $projectId));
    }
}
