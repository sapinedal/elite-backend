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
            $table->longText('contractor_signature')->nullable();
            $table->longText('resident_signature')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ftra_records', function (Blueprint $table) {
            $table->dropColumn(['contractor_signature', 'resident_signature']);
        });
    }
};
