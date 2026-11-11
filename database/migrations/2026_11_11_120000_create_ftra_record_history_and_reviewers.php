<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flujo de aprobación de FTRA: Residente registra → Supervisor recibe a satisfacción → Director de Obra recibe a satisfacción.
 * Cualquiera de los dos puede devolverlo al residente, que lo corrige y lo reenvía (la revisión comienza de nuevo).
 * El historial guarda cada paso para la línea de tiempo y los correos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ftra_records', function (Blueprint $table) {
            $table->foreignId('supervisor_user_id')->nullable()->after('supervisor_signature')->constrained('users')->nullOnDelete();
            $table->timestamp('supervisor_at')->nullable()->after('supervisor_user_id');
            $table->foreignId('director_user_id')->nullable()->after('director_signature')->constrained('users')->nullOnDelete();
            $table->timestamp('director_at')->nullable()->after('director_user_id');
        });

        Schema::create('ftra_record_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ftra_record_id')->constrained('ftra_records')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20); // registrado | reenviado | aprobado | devuelto
            $table->string('stage', 20)->nullable(); // supervisor | director_obra
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->index(['ftra_record_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ftra_record_history');
        Schema::table('ftra_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_user_id');
            $table->dropConstrainedForeignId('director_user_id');
            $table->dropColumn(['supervisor_at', 'director_at']);
        });
    }
};
