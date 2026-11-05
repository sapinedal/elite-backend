<?php

use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Multifox, priorizar pagos y Almacén dejan de habilitarse por cargo: ahora solo por permiso.
 * Para que nadie pierda el acceso al desplegar, se asignan directamente a las personas que lo usan:
 *
 *   - Paula Camacho: Multifox de cortes y cuentas de cobro, y priorizar pagos (con pagos.ver para ver el cuadro).
 *   - Juan Carlos Esquivel: orden del ERP y entrada de almacén.
 *
 * Antes también lo tenían por cargo Mónica Jiménez, Maira De la Peña y Richard Neeley (mismo cargo que Paula):
 * no se les asigna. Si un usuario no existe (ej. en local) se omite.
 */
return new class extends Migration
{
    protected array $assignments = [
        'residentedeobra@inverconstruccion.com' => ['cortes.multifox', 'pagos.priorizar', 'pagos.ver'],
        'juan.esquivel@elite.com' => ['ordenes.gestionar_almacen'],
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->assignments as $email => $permissions) {
            $user = User::where('email', $email)->first();
            if (!$user) {
                continue;
            }

            foreach ($permissions as $name) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            }
            $user->givePermissionTo($permissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ($this->assignments as $email => $permissions) {
            User::where('email', $email)->first()?->revokePermissionTo($permissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
