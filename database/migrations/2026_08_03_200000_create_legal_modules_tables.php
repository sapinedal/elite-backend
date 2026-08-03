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
        Schema::create('promise_audits', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number');
            $table->string('client_name')->nullable();
            $table->string('status')->default('Aprobado');
            $table->integer('risk_score')->default(0);
            $table->mediumText('raw_text')->nullable();
            $table->json('ai_analysis')->nullable();
            $table->timestamps();
        });

        Schema::create('descargos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->nullable()->constrained('ftra_contractors')->onDelete('set null');
            $table->string('contractor_name_raw')->nullable();
            $table->date('hearing_date')->nullable();
            $table->text('observations')->nullable();
            $table->string('status')->default('Pendiente');
            $table->timestamps();
        });

        Schema::create('desistimientos', function (Blueprint $table) {
            $table->id();
            $table->string('client_name');
            $table->string('apartment');
            $table->string('refund_status')->default('Pendiente');
            $table->decimal('amount', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('desistimientos');
        Schema::dropIfExists('descargos');
        Schema::dropIfExists('promise_audits');
    }
};
