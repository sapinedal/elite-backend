<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distribución de apartamentos de cada torre: número de pisos y apartamentos por piso.
 * Los apartamentos se numeran piso × 100 + consecutivo (piso 12 → 1201 ... 1210).
 * Se configura desde Administración › Proyectos y alimenta las listas de FTRA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('towers', function (Blueprint $table) {
            $table->unsignedSmallInteger('floors')->nullable()->after('code');
            $table->unsignedSmallInteger('apartments_per_floor')->nullable()->after('floors');
        });
    }

    public function down(): void
    {
        Schema::table('towers', function (Blueprint $table) {
            $table->dropColumn(['floors', 'apartments_per_floor']);
        });
    }
};
