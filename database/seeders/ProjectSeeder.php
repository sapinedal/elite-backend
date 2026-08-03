<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projects = [
            [
                'code' => 'vis',
                'name' => 'Ciudadela San Miguel',
                'subtitle' => 'VIS - 2,200 aptos (Torre 2)',
                'description' => 'Macroproyecto de Vivienda de Interés Social compuesto por Torres de Apartamentos y Urbanismo.',
                'total_budget' => 22454184688,
                'is_active' => true,
            ],
            [
                'code' => 'serena',
                'name' => 'Serena del Mar',
                'subtitle' => 'Renta Corta & Mar',
                'description' => 'Proyecto exclusivo de apartamentos para renta corta y turismo de playa.',
                'total_budget' => 35000000000,
                'is_active' => true,
            ],
            [
                'code' => 'jerico',
                'name' => 'Jericó',
                'subtitle' => 'Parcelación & Naturaleza',
                'description' => 'Desarrollo campestre de parcelas y espacios ecológicos sustentables.',
                'total_budget' => 18000000000,
                'is_active' => true,
            ],
            [
                'code' => 'comercial',
                'name' => 'Plaza Comercial',
                'subtitle' => 'Locales & Retail',
                'description' => 'Centro de servicios comerciales, locales y retail estratégico.',
                'total_budget' => 12000000000,
                'is_active' => true,
            ],
        ];

        foreach ($projects as $project) {
            DB::table('projects')->updateOrInsert(
                ['code' => $project['code']],
                array_merge($project, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // Asignar los contratos existentes de INVER al proyecto Ciudadela San Miguel (code: vis)
        $visProject = DB::table('projects')->where('code', 'vis')->first();
        if ($visProject) {
            DB::table('contracts')->update(['project_id' => $visProject->id]);
        }
    }
}
