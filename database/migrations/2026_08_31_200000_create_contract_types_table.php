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
        if (!Schema::hasTable('contract_types')) {
            Schema::create('contract_types', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('contracts') && !Schema::hasColumn('contracts', 'contract_type_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->foreignId('contract_type_id')->nullable()->constrained('contract_types')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('contracts') && Schema::hasColumn('contracts', 'contract_type_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropForeign(['contract_type_id']);
                $table->dropColumn('contract_type_id');
            });
        }
        Schema::dropIfExists('contract_types');
    }
};
