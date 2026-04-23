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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('document')->constrained();
            $table->foreignId('position_id')->nullable()->after('area_id')->constrained();
            $table->dropColumn(['area', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('area')->nullable()->after('document');
            $table->string('position')->nullable()->after('area');
            $table->dropConstrainedForeignId('area_id');
            $table->dropConstrainedForeignId('position_id');
        });
    }
};
