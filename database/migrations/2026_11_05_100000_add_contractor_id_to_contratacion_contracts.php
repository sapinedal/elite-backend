<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Enlaza cada contrato con su proveedor (ftra_contractors) para que los formularios de cortes, anticipos
 * y cuentas de cobro listen solo los contratos del proveedor elegido.
 *
 * Los contratos existentes se enlazan solos cuando el nombre del contratista coincide con un único proveedor
 * (sin tildes, mayúsculas ni sufijos SAS/LTDA). Los que no coinciden quedan sin proveedor hasta que se editen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratacion_contracts', function (Blueprint $table) {
            $table->foreignId('contractor_id')->nullable()->after('contract_type_id')
                ->constrained('ftra_contractors')->nullOnDelete();
        });

        $contractors = DB::table('ftra_contractors')->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => self::normalize($c->name)])
            ->filter(fn ($c) => $c['name'] !== '');

        DB::table('contratacion_contracts')->whereNull('contractor_id')->orderBy('id')
            ->get(['id', 'contractor_name'])
            ->each(function ($contract) use ($contractors) {
                // "EMELECT - M.O Fundación" → "emelect"
                $name = self::normalize(Str::before((string) $contract->contractor_name, ' - '));
                if ($name === '') {
                    return;
                }

                $matches = $contractors->filter(fn ($c) => $c['name'] === $name
                    || str_starts_with($name, $c['name'] . ' ')
                    || str_starts_with($c['name'], $name . ' '));

                if ($matches->count() === 1) {
                    DB::table('contratacion_contracts')->where('id', $contract->id)
                        ->update(['contractor_id' => $matches->first()['id']]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('contratacion_contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contractor_id');
        });
    }

    private static function normalize(string $name): string
    {
        $name = Str::lower(Str::ascii($name));
        $name = preg_replace('/[^a-z0-9 ]+/', ' ', $name);
        $name = preg_replace('/\b(s ?a ?s|ltda|s ?a)\b/', ' ', $name);

        return trim(preg_replace('/\s+/', ' ', $name));
    }
};
