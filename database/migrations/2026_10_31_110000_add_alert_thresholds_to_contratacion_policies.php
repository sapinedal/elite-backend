<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las pólizas se avisan varias veces antes de vencer (40, 30 y 20 días, config contratacion.policy_alert_thresholds).
 * `last_alert_days` guarda el último umbral avisado para no repetirlo; se limpia si cambia la vigencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratacion_policies', function (Blueprint $table) {
            $table->unsignedSmallInteger('last_alert_days')->nullable()->after('alert_sent_at');
        });

        // Las pólizas ya avisadas con la regla anterior (un solo aviso a 40 días) quedan con el aviso de 40
        DB::table('contratacion_policies')->whereNotNull('alert_sent_at')->update(['last_alert_days' => 40]);
    }

    public function down(): void
    {
        Schema::table('contratacion_policies', function (Blueprint $table) {
            $table->dropColumn('last_alert_days');
        });
    }
};
