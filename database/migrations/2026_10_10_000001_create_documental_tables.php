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
        // Tabla de permisos por área para carpetas / rutas de DigitalOcean Spaces / S3
        Schema::create('document_area_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->string('folder_path')->default('*')->comment('Ruta relativa en el bucket o * para alcance global del área');
            $table->boolean('can_read')->default(true);
            $table->boolean('can_upload')->default(false);
            $table->boolean('can_create_folder')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();

            $table->unique(['area_id', 'folder_path']);
        });

        // Tabla de auditoría / registro de actividades sobre documentos y carpetas
        Schema::create('document_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50)->comment('upload, create_folder, delete, rename, download, etc.');
            $table->text('path');
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_activity_logs');
        Schema::dropIfExists('document_area_permissions');
    }
};
