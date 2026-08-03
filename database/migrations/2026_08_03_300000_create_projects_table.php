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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // vis, serena, jerico, comercial
            $table->string('name');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->decimal('total_budget', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Relacionar contratos con proyectos
        if (Schema::hasTable('contracts') && !Schema::hasColumn('contracts', 'project_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->foreignId('project_id')->nullable()->constrained('projects')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('contracts') && Schema::hasColumn('contracts', 'project_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
                $table->dropColumn('project_id');
            });
        }
        Schema::dropIfExists('projects');
    }
};
