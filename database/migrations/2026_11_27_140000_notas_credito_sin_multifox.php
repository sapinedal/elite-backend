<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las notas crédito de los cortes ya no se cargan en Multifox: pasan directo a la aprobación de Contabilidad.
 * Las que estaban esperando Multifox quedan pendientes de Contabilidad, y la factura que anulan (si aún no llegó a
 * cuentas por pagar) queda anulada y sale de la cola de Multifox.
 */
return new class extends Migration
{
    public function up(): void
    {
        $notas = DB::table('cortes_invoices')
            ->where('document_type', 'nota_credito')
            ->whereNull('multifox_confirmed_at')
            ->where(fn ($q) => $q->whereNull('accounting_status')->orWhere('accounting_status', 'devuelta'))
            ->get(['id', 'corte_id', 'related_document']);

        foreach ($notas as $nota) {
            DB::table('cortes_invoices')->where('id', $nota->id)->update(['accounting_status' => 'pendiente', 'updated_at' => now()]);

            if ($nota->related_document) {
                DB::table('cortes_invoices')
                    ->where('corte_id', $nota->corte_id)
                    ->where('id', '!=', $nota->id)
                    ->where('document_type', '!=', 'nota_credito')
                    ->whereRaw('UPPER(TRIM(invoice_number)) = ?', [mb_strtoupper(trim($nota->related_document))])
                    ->where(fn ($q) => $q->whereNull('accounting_status')->orWhere('accounting_status', '!=', 'aprobada'))
                    ->update(['accounting_status' => 'anulada', 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Las anuladas vuelven a esperar Multifox; las notas crédito sin cargar en Multifox, también
        DB::table('cortes_invoices')->where('accounting_status', 'anulada')->update(['accounting_status' => null]);
        DB::table('cortes_invoices')->where('document_type', 'nota_credito')->whereNull('multifox_confirmed_at')
            ->where('accounting_status', 'pendiente')->update(['accounting_status' => null]);
    }
};
