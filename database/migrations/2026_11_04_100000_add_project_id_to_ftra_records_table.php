<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Divide los registros FTRA por proyecto (contexto multiproyecto).
     * Los registros existentes son de Ciudadela San Miguel (code: vis).
     * Los catálogos (formatos, contratistas, residentes) siguen siendo globales.
     */
    public function up(): void
    {
        Schema::table('ftra_records', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('id')->constrained('projects')->restrictOnDelete();
        });

        $sanMiguel = DB::table('projects')
            ->where('code', 'vis')
            ->orWhere('name', 'Ciudadela San Miguel')
            ->orderBy('id')
            ->first();

        throw_if(
            DB::table('ftra_records')->exists() && !$sanMiguel,
            Exception::class,
            'No existe el proyecto Ciudadela San Miguel (code: vis) para asignar los registros FTRA existentes.'
        );

        if ($sanMiguel) {
            DB::table('ftra_records')->whereNull('project_id')->update(['project_id' => $sanMiguel->id]);
        }

        Schema::table('ftra_records', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable(false)->change();
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('ftra_records', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'status']);
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
