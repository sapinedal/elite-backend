<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $evaluations = DB::table('evaluations')->whereNotNull('history')->get();

        foreach ($evaluations as $evaluation) {
            $history = json_decode($evaluation->history, true);
            
            if (is_array($history)) {
                foreach ($history as $entry) {
                    DB::table('evaluation_versions')->insert([
                        'evaluation_id' => $evaluation->id,
                        'snapshot' => json_encode([
                            'evaluation' => [
                                'total_score' => $entry['total_score'] ?? 0,
                                'general_analysis' => $entry['general_analysis'] ?? null,
                                'status' => $entry['status'] ?? 'finalizada',
                                'updated_at' => $entry['updated_at'] ?? now()
                            ],
                            'results' => []
                        ]),
                        'status_at_moment' => $entry['status'] ?? 'finalizada',
                        'changed_by' => $evaluation->user_id,
                        'created_at' => $entry['updated_at'] ?? now(),
                        'updated_at' => $entry['updated_at'] ?? now(),
                    ]);
                }
            }
        }

        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropColumn('history');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->json('history')->nullable();
        });
    }
};
