<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlaces públicos permanentes de Gestión Documental: una URL fija (con token) que no caduca.
 * Cada acceso genera al momento una URL firmada corta de Spaces, así el enlace sigue funcionando
 * indefinidamente hasta que se revoque o se elimine el archivo. Si el archivo se renombra o mueve,
 * el enlace sigue apuntándolo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_share_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->text('path');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_share_links');
    }
};
