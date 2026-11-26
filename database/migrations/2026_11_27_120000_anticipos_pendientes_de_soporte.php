<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Anticipos aprobados antes de existir el paso del contrato / orden de compra: los que siguen esperando que
 * Contabilidad los genere en Multifox y no tienen el soporte vuelven a "Pendiente soporte", así el residente lo
 * adjunta antes de Multifox (como en el flujo nuevo).
 *
 * Los que ya se cargaron en Multifox o están en el cuadro de pagos conservan su estado: el residente puede adjuntar
 * el contrato / OC desde el detalle sin que cambie el flujo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('anticipos')
            ->where('status', 'pendiente_contabilidad')
            ->whereNull('support_file_path')
            ->whereNull('deleted_at')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('anticipos')->whereIn('id', $ids)->update(['status' => 'pendiente_soporte', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Los que siguen sin soporte vuelven a Contabilidad (Multifox)
        DB::table('anticipos')
            ->where('status', 'pendiente_soporte')
            ->whereNull('support_file_path')
            ->update(['status' => 'pendiente_contabilidad', 'updated_at' => now()]);
    }
};
