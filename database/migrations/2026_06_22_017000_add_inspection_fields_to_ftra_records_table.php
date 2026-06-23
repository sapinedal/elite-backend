<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ftra_records', function (Blueprint $table) {
            $table->string('resultado_inspeccion')->default('Recibido a satisfacción')->after('format_id');
            $table->string('orden_aseo')->default('Aprobado')->after('resultado_inspeccion');
            $table->string('piso')->nullable()->after('orden_aseo');
            $table->string('apartamento')->nullable()->after('piso');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ftra_records', function (Blueprint $table) {
            $table->dropColumn(['resultado_inspeccion', 'orden_aseo', 'piso', 'apartamento']);
        });
    }
};
