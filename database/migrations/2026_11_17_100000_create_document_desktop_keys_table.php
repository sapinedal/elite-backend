<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Claves de escritorio: credencial propia de cada usuario para conectar Gestión Documental
 * como unidad de red (WebDAV). Solo se guarda el hash; la clave se muestra una vez al crearla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_desktop_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('token_hash', 64)->unique();
            $table->string('last_four', 4);
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_desktop_keys');
    }
};
