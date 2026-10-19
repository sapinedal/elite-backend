<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices para los filtros de la auditoría (acción, usuario y rango de fechas).
     */
    public function up(): void
    {
        Schema::table('document_activity_logs', function (Blueprint $table) {
            $table->index(['action', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('document_activity_logs', function (Blueprint $table) {
            $table->dropIndex(['action', 'created_at']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['created_at']);
        });
    }
};
