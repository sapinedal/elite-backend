<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las restricciones por carpeta pasan a definirse por rol (Spatie) en lugar de por área.
     * Una carpeta con reglas queda restringida: solo los roles listados acceden a ella y sus subcarpetas.
     */
    public function up(): void
    {
        Schema::create('document_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('folder_path')->comment('Ruta relativa de la carpeta restringida en el bucket');
            $table->boolean('can_read')->default(true);
            $table->boolean('can_upload')->default(false);
            $table->boolean('can_create_folder')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();

            $table->unique(['role_id', 'folder_path']);
            $table->index('folder_path');
        });

        Schema::dropIfExists('document_area_permissions');
    }

    public function down(): void
    {
        Schema::create('document_area_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->string('folder_path')->default('*');
            $table->boolean('can_read')->default(true);
            $table->boolean('can_upload')->default(false);
            $table->boolean('can_create_folder')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();

            $table->unique(['area_id', 'folder_path']);
        });

        Schema::dropIfExists('document_role_permissions');
    }
};
