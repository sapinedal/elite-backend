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
        Schema::create('ftra_records', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('contractor_id')
                ->constrained('ftra_contractors')
                ->onDelete('cascade');
                
            $table->foreignId('format_id')
                ->constrained('ftra_formats')
                ->onDelete('cascade');
                
            $table->text('observations')->nullable();
            $table->boolean('is_completed')->default(false);
            
            $table->enum('status', ['Registrada', 'Seguimiento', 'Aprobada', 'Rechazada'])
                ->default('Registrada');
                
            $table->foreignId('registered_by_id')
                ->constrained('users')
                ->onDelete('cascade');
                
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ftra_records');
    }
};
