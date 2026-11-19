<?php

use App\Http\Modules\Users\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Los correos de la herramienta pasan a enviarse por permiso: "correos.<módulo>.<destinatario>", asignado
 * directamente al usuario (ver App\Http\Modules\Users\Service\MailRecipients).
 *
 * Para que nadie deje de recibir lo que hoy recibe, se asigna cada permiso a:
 *   - los usuarios cuyos correos estaban configurados (valores por defecto de config/*.php y los del .env), y
 *   - los usuarios que recibían el aviso por otro permiso asignado directamente (ftra.revisar, ftra.aprobar,
 *     cortes.multifox, ordenes.gestionar_almacen, contratacion.alertas_polizas).
 *
 * Los correos configurados que no son de ningún usuario de Elite se informan en el log: deben quedar en el .env
 * (buzón externo) o crearse como usuarios.
 */
return new class extends Migration
{
    /** Permiso => [clave de config que lee el .env, correos que venían por defecto en el código] */
    protected array $configured = [
        'correos.cortes.interventor' => ['cortes.stage_emails.interventor', ['druizeqp@gmail.com']],
        'correos.cortes.director_obra' => ['cortes.stage_emails.director_obra', ['dirobrasanmiguel@inverconstruccion.com']],
        'correos.cortes.gerencia' => ['cortes.stage_emails.gerencia', ['gerentedeproyectos@inverconstruccion.com']],
        'correos.cortes.residentes' => ['cortes.residentes', ['residenteacabados@inverconstruccion.com', 'residenteobrasanmiguel@inverconstruccion.com']],
        'correos.cortes.facturacion' => ['cortes.facturacion', ['facturacion@inverconstruccion.com']],
        'correos.cortes.multifox' => ['cortes.multifox_emails', []],
        'correos.cortes.copia' => ['cortes.monitor', ['residentedeobra@inverconstruccion.com']],

        'correos.ftra.supervisor' => ['ftra.stage_emails.supervisor', ['druiz.eqp@gmail.com', 'gaguilar.eqp@gmail.com']],
        'correos.ftra.director_obra' => ['ftra.stage_emails.director_obra', ['dirobrasanmiguel@inverconstruccion.com']],
        'correos.ftra.copia' => ['ftra.monitor', []],

        'correos.anticipos.director_obra' => ['anticipos.stage_emails.director_obra', ['dirobrasanmiguel@inverconstruccion.com']],
        'correos.anticipos.gerencia' => ['anticipos.stage_emails.gerencia', ['gerentedeproyectos@inverconstruccion.com']],
        'correos.anticipos.contabilidad' => ['anticipos.contabilidad', ['facturacion@inverconstruccion.com']],
        'correos.anticipos.copia' => ['anticipos.monitor', ['residentedeobra@inverconstruccion.com']],

        'correos.cuentas_cobro.director_obra' => ['cuentas_cobro.stage_emails.director_obra', ['dirobrasanmiguel@inverconstruccion.com']],
        'correos.cuentas_cobro.gerencia' => ['cuentas_cobro.stage_emails.gerencia', ['gerentedeproyectos@inverconstruccion.com']],
        'correos.cuentas_cobro.multifox' => ['cortes.multifox_emails', []],
        'correos.cuentas_cobro.copia' => ['cuentas_cobro.monitor', ['residentedeobra@inverconstruccion.com']],

        'correos.ordenes.director_obra' => ['ordenes.stage_emails.director_obra', ['dirobrasanmiguel@inverconstruccion.com']],
        'correos.ordenes.gerencia' => ['ordenes.stage_emails.gerencia', ['gerentedeproyectos@inverconstruccion.com']],
        'correos.ordenes.almacen' => ['ordenes.almacen_emails', []],
        'correos.ordenes.facturacion' => ['ordenes.facturacion', ['facturacion@inverconstruccion.com']],
        'correos.ordenes.copia' => ['ordenes.monitor', ['residentedeobra@inverconstruccion.com']],

        'correos.contratacion.polizas' => ['contratacion.alert_emails', ['residentedeobra@inverconstruccion.com', 'dirobrasanmiguel@inverconstruccion.com']],
    ];

    /** Permiso anterior (asignado directamente) => permisos de correo que reemplaza */
    protected array $fromPermissions = [
        'ftra.revisar' => ['correos.ftra.supervisor'],
        'ftra.aprobar' => ['correos.ftra.director_obra'],
        'cortes.multifox' => ['correos.cortes.multifox', 'correos.cuentas_cobro.multifox'],
        'ordenes.gestionar_almacen' => ['correos.ordenes.almacen'],
        'contratacion.alertas_polizas' => ['correos.contratacion.polizas'],
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (array_keys($this->configured) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $users = User::whereNotNull('email')->get()->keyBy(fn (User $u) => $this->key($u->email));
        $unmatched = [];

        foreach ($this->configured as $permission => [$configKey, $defaults]) {
            $emails = collect($defaults)->merge((array) config($configKey, []))->map(fn ($e) => trim((string) $e))->filter()->unique();

            foreach ($emails as $email) {
                $user = $users->get($this->key($email));
                $user ? $user->givePermissionTo($permission) : $unmatched[$email][] = $permission;
            }
        }

        foreach ($this->fromPermissions as $old => $new) {
            if (!Permission::where('name', $old)->exists()) {
                continue;
            }
            User::query()->withDirectPermission($old)->get()->each(fn (User $u) => $u->givePermissionTo($new));
        }

        // contratacion.alertas_polizas solo servía para recibir el correo: lo reemplaza correos.contratacion.polizas
        Permission::where('name', 'contratacion.alertas_polizas')->delete();

        foreach ($unmatched as $email => $permissions) {
            $message = "Correos por permiso: {$email} no es usuario de Elite; si debe seguir recibiendo (" . implode(', ', $permissions) . ') déjelo en el .env o créelo como usuario.';
            Log::warning($message);
            if (app()->runningInConsole() && !app()->runningUnitTests()) {
                echo "  {$message}" . PHP_EOL;
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::firstOrCreate(['name' => 'contratacion.alertas_polizas', 'guard_name' => 'web']);
        Permission::whereIn('name', array_keys($this->configured))->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /** En Gmail los puntos del usuario no cuentan: druiz.eqp@gmail.com y druizeqp@gmail.com son el mismo buzón. */
    protected function key(string $email): string
    {
        $email = mb_strtolower(trim($email));
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return in_array($domain, ['gmail.com', 'googlemail.com'], true)
            ? str_replace('.', '', Str::before($local, '+')) . '@gmail.com'
            : $email;
    }
};
