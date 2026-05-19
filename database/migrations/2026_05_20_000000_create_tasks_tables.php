<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Con esta migración estructuramos de forma relacional y limpia la bitácora de tareas,
     * separando las observaciones (dailys) y las auditorías en tablas dedicadas (1 a muchos).
     * Esto evita que la tabla principal crezca horizontalmente de forma insostenible.
     */
    public function up(): void
    {
        // 1. Tabla principal de tareas
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // Descripción o nombre de la tarea
            
            // Prioridades acordadas: P0 (Crítica), P1 (Alta), P2 (Media), P3 (Baja)
            $table->enum('priority', ['P0', 'P1', 'P2', 'P3'])->default('P2');
            
            // Estados de la bitácora
            $table->enum('status', ['Por hacer', 'En espera', 'En progreso', 'Completada'])->default('Por hacer');
            
            // Relaciones
            // Solicitado por: El usuario que crea o solicita la tarea (dueño/creador)
            $table->foreignId('requested_by_id')
                ->constrained('users')
                ->onDelete('cascade');
                
            // Responsable: El usuario asignado para cumplir la tarea (opcional al inicio)
            $table->foreignId('responsible_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
                
            // Área: El área de la compañía asignada (ej. Gestión Humana)
            $table->foreignId('area_id')
                ->nullable()
                ->constrained('areas')
                ->onDelete('set null');
                
            $table->date('start_date')->nullable();
            $table->date('scheduled_end_date')->nullable();
            $table->date('actual_end_date')->nullable(); // Se llena automáticamente cuando el estado pasa a "Completada"
            
            $table->timestamps();
        });

        // 2. Tabla para observaciones de dailys
        Schema::create('task_observations', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('task_id')
                ->constrained('tasks')
                ->onDelete('cascade'); // Si borramos la tarea, se borran sus post-its asociados automáticamente
                
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade'); // Quién hizo la observación en el daily
                
            $table->text('observation');
            $table->timestamps();
        });

        // 3. Tabla para el registro de auditoría (Audit Logs)
        Schema::create('task_audit_logs', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('task_id')
                ->constrained('tasks')
                ->onDelete('cascade');
                
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade'); // Quién realizó el cambio
                
            $table->string('action'); // 'created', 'updated', 'deleted'
            
            // Guardamos las diferencias en formato JSON.
            // Ventaja: No necesitamos columnas fijas para cada campo auditado. Nos da flexibilidad absoluta
            // si en el futuro agregamos o quitamos campos de la tabla tasks.
            // Estructura esperada: {"status": {"old": "Por hacer", "new": "En progreso"}}
            $table->json('changes')->nullable(); 
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Se borran en orden inverso a la creación para respetar la integridad referencial de llaves foráneas
        Schema::dropIfExists('task_audit_logs');
        Schema::dropIfExists('task_observations');
        Schema::dropIfExists('tasks');
    }
};
