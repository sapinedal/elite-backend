<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contexto multiproyecto en Documental.
     * El bucket está organizado por área (comercial/, tecnica/…) y los proyectos son subcarpetas dentro de cada área,
     * así que en vez de mover archivos se etiqueta qué carpetas pertenecen a qué proyecto. Una carpeta etiquetada
     * (y todo su contenido) solo se ve con ese proyecto activo; las carpetas sin etiqueta son transversales.
     */
    public function up(): void
    {
        Schema::create('document_project_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('folder_path', 1024)->unique()->comment('Ruta relativa de la carpeta en el bucket');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('document_activity_logs', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('user_id')->constrained('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_activity_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::dropIfExists('document_project_folders');
    }
};
