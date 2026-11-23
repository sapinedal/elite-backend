<?php

use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Aprobación de Contabilidad antes de cuentas por pagar, en todos los módulos de registro:
 *
 *   Cortes: factura cargada en Multifox -> Contabilidad aprueba -> cuentas por pagar
 *   Órdenes: entrada de almacén cargada -> Contabilidad aprueba -> cuentas por pagar
 *   Anticipos y cuentas de cobro: cargados en Multifox -> Contabilidad aprueba -> cuadro de pagos
 *
 * Si Contabilidad devuelve, vuelve a quien carga en Multifox (o a Almacén en órdenes) y se le notifica.
 * Quién aprueba: <módulo>.aprobar_contabilidad (rol contable). Quién recibe el aviso: correos.<módulo>.aprobacion_contable,
 * asignado directamente a los usuarios del rol contable.
 */
return new class extends Migration
{
    protected array $modules = ['cortes', 'ordenes', 'anticipos', 'cuentas_cobro'];

    public function up(): void
    {
        Schema::table('cortes_invoices', function (Blueprint $table) {
            // null: aún no se carga en Multifox · pendiente · aprobada · devuelta
            $table->string('accounting_status', 20)->nullable()->after('multifox_file_mime');
            $table->foreignId('accounting_by')->nullable()->after('accounting_status')->constrained('users')->nullOnDelete();
            $table->timestamp('accounting_at')->nullable()->after('accounting_by');
            $table->text('accounting_comment')->nullable()->after('accounting_at');
        });

        // Las facturas ya confirmadas en Multifox ya están en cuentas por pagar
        DB::table('cortes_invoices')->whereNotNull('multifox_confirmed_at')->update(['accounting_status' => 'aprobada']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $approve = array_map(fn (string $m) => "{$m}.aprobar_contabilidad", $this->modules);
        $mail = array_map(fn (string $m) => "correos.{$m}.aprobacion_contable", $this->modules);
        foreach ([...$approve, ...$mail] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Admin y gerencia tienen todos los permisos por rol
        foreach (['admin', 'gerencia'] as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($approve);
        }

        $contable = Role::where('name', 'contable')->where('guard_name', 'web')->first();
        if ($contable) {
            $contable->givePermissionTo([...$approve, 'cortes.ver', 'ordenes.ver', 'anticipos.ver', 'cuentas_cobro.ver']);
            // Los correos se asignan directamente al usuario (ver MailRecipients)
            User::role('contable')->get()->each(fn (User $user) => $user->givePermissionTo($mail));
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('cortes_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accounting_by');
            $table->dropColumn(['accounting_status', 'accounting_at', 'accounting_comment']);
        });

        Permission::whereIn('name', [
            ...array_map(fn (string $m) => "{$m}.aprobar_contabilidad", $this->modules),
            ...array_map(fn (string $m) => "correos.{$m}.aprobacion_contable", $this->modules),
        ])->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
