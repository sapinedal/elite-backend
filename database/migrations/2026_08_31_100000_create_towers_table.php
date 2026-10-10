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
        if (!Schema::hasTable('towers')) {
            Schema::create('towers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
                $table->string('name'); // ej: Torre 1, Torre 2, Urbanismo
                $table->string('code')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table) {
                if (!Schema::hasColumn('contracts', 'project_id')) {
                    $table->foreignId('project_id')->nullable()->constrained('projects')->onDelete('set null');
                }
                if (!Schema::hasColumn('contracts', 'tower_id')) {
                    $table->foreignId('tower_id')->nullable()->constrained('towers')->onDelete('set null');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table) {
                if (Schema::hasColumn('contracts', 'tower_id')) {
                    $table->dropForeign(['tower_id']);
                    $table->dropColumn('tower_id');
                }
            });
        }
        Schema::dropIfExists('towers');
    }
};
