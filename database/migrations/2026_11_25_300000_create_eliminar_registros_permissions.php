<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos para eliminar registros cargados por error en cualquier estado (aprobados, facturados, completados):
 * cortes y sus facturas, órdenes y sus documentos (orden ERP, factura, entrada), anticipos, cuentas de cobro y
 * facturas del mes ya procesadas. Al eliminar se reversa lo que generaron (cuentas por pagar sin pagos, facturas
 * del mes que vuelven a quedar pendientes). Admin y Gerencia los reciben de una vez; a los demás desde Roles.
 */
return new class extends Migration
{
    protected array $permissions = [
        'cortes.eliminar',
        'ordenes.eliminar',
        'anticipos.eliminar',
        'cuentas_cobro.eliminar',
        'facturacion.eliminar',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::whereIn('name', ['admin', 'gerencia'])->where('guard_name', 'web')->get()
            ->each(fn (Role $role) => $role->givePermissionTo($this->permissions));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permissions)->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
