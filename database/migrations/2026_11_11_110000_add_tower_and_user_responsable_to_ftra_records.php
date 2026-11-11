<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FTRA:
 *  - tower_id: torre del apartamento revisado (los pisos y apartamentos salen de su distribución).
 *  - responsable_user_id: el residente responsable ahora es el usuario de Elite que registra el FTRA.
 *    Los registros anteriores apuntaban al catálogo `residentes`; se enlazan al usuario con el mismo correo.
 *    La columna antigua responsable_id se conserva como histórico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ftra_records', function (Blueprint $table) {
            $table->foreignId('tower_id')->nullable()->after('project_id')->constrained('towers')->nullOnDelete();
            $table->foreignId('responsable_user_id')->nullable()->after('responsable_id')->constrained('users')->nullOnDelete();
        });

        $residentes = DB::table('residentes')->whereNotNull('email')->pluck('email', 'id');
        foreach ($residentes as $residenteId => $email) {
            $userId = DB::table('users')->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->value('id');
            if ($userId) {
                DB::table('ftra_records')
                    ->where('responsable_id', $residenteId)
                    ->whereNull('responsable_user_id')
                    ->update(['responsable_user_id' => $userId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('ftra_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsable_user_id');
            $table->dropConstrainedForeignId('tower_id');
        });
    }
};
