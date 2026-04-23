<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AreaPositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $areas = [
            'Comercial' => ['Analista de Ventas', 'Gerente de Cuenta', 'Director Comercial'],
            'Operaciones' => ['Coordinador Logístico', 'Auxiliar de Bodega', 'Líder de Operaciones'],
            'Tecnología' => ['Desarrollador Fullstack', 'Analista QA', 'Arquitecto de Software'],
            'Administrativo' => ['Contador', 'Auxiliar Administrativo', 'Gerente General'],
        ];

        foreach ($areas as $areaName => $positions) {
            $area = \App\Http\Modules\Configuracion\Models\Area::create([
                'name' => $areaName,
                'description' => "Área de $areaName"
            ]);

            foreach ($positions as $posName) {
                \App\Http\Modules\Configuracion\Models\Position::create([
                    'name' => $posName,
                    'area_id' => $area->id
                ]);
            }
        }
    }
}
