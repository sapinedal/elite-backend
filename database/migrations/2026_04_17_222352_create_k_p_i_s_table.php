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
        Schema::create('kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('formula')->nullable();
            $table->decimal('target', 15, 2)->default(0);
            $table->string('unit')->default('%');
            $table->string('stage')->nullable();
            $table->decimal('weight', 5, 2)->default(0);
            $table->integer('incidence')->default(100);
            $table->boolean('lower_is_better')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpis');
    }
};
