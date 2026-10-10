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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('nro'); // ej: mo_2_t2
            $table->foreignId('contractor_id')->nullable()->constrained('ftra_contractors')->onDelete('set null');
            $table->string('contractor_name_raw')->nullable();
            $table->string('type'); // Mano de Obra, Suministro, etc.
            $table->string('category'); // mano_obra, urbanismo
            $table->text('object')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('status')->default('Vigente');
            $table->string('drive_link', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->string('policy_number');
            $table->string('insurance_company');
            $table->decimal('insured_value', 18, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('policies');
        Schema::dropIfExists('contracts');
    }
};
