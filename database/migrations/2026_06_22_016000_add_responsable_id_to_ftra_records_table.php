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
        Schema::table('ftra_records', function (Blueprint $table) {
            $table->foreignId('responsable_id')
                ->nullable()
                ->after('format_id')
                ->constrained('residentes')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ftra_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsable_id');
        });
    }
};
