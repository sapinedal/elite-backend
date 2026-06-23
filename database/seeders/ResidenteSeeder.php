<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ResidenteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $residentes = [
            [
                'name' => 'Residente Estructura',
                'email' => 'residenteobrasanmiguel@inverconstruccion.com',
                'role' => 'Residente estructura',
            ],
            [
                'name' => 'Residente Acabados',
                'email' => 'residenteacabados@inverconstruccion.com',
                'role' => 'Residente acabados',
            ],
            [
                'name' => 'Director Obra',
                'email' => 'dirobrasanmiguel@inverconstruccion.com',
                'role' => 'Director Obra',
            ],
            [
                'name' => 'Residente Admin',
                'email' => 'residentedeobra@inverconstruccion.com',
                'role' => 'Residente admin',
            ],
            [
                'name' => 'Supervisor Obra 1',
                'email' => 'druiz.eqp@gmail.com',
                'role' => 'Supervisores obra',
            ],
            [
                'name' => 'Supervisor Obra 2',
                'email' => 'gaguilar.eqp@gmail.com',
                'role' => 'Supervisores obra',
            ],
            [
                'name' => 'Gerente de Proyectos',
                'email' => 'gerentedeproyectos@inverconstruccion.com',
                'role' => 'Gerente de proyectos',
            ],
        ];

        foreach ($residentes as $residente) {
            DB::table('residentes')->updateOrInsert(
                ['email' => $residente['email']],
                [
                    'name' => $residente['name'],
                    'role' => $residente['role'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
