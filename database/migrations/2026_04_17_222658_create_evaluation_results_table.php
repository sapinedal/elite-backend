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
        Schema::create('evaluation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->onDelete('cascade');
            $table->foreignId('kpi_id')->nullable()->constrained()->onDelete('set null');
            $table->string('kpi_name');
            $table->decimal('kpi_weight', 5, 2);
            $table->decimal('kpi_target', 15, 2);
            $table->decimal('real_value', 15, 2)->nullable();
            $table->decimal('score', 5, 2)->default(0);
            $table->json('details')->nullable(); // Para la tabla de detalle libre
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_results');
    }
};
