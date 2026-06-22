<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContractorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $contractors = [
            ['name' => 'Emelect Group', 'nit' => '901234567-1', 'email' => 'info@emelect.com', 'phone' => '3001234567'],
            ['name' => 'Yancelly Monsalve', 'nit' => '1023456789', 'email' => 'yancelly@example.com', 'phone' => '3119876543'],
            ['name' => 'Coneq', 'nit' => '890123456-2', 'email' => 'contacto@coneq.co', 'phone' => '3151112222'],
            ['name' => 'Vías y Triturados de Antioquia', 'nit' => '900334455-8', 'email' => 'vias.triturados@vta.com', 'phone' => '3128889999'],
            ['name' => 'Industrias de aluminio arquitectónico', 'nit' => '860223344-5', 'email' => 'ventas@indalugroup.com', 'phone' => '3203334444'],
            ['name' => 'Tecnoizajes', 'nit' => '901556677-4', 'email' => 'servicio@tecnoizajes.com', 'phone' => '3187776666'],
            ['name' => 'IHC', 'nit' => '800554433-2', 'email' => 'info@ihc-constructora.com', 'phone' => '3104445555'],
            ['name' => 'IASS', 'nit' => '901667788-3', 'email' => 'iass.ingenieria@iass.com.co', 'phone' => '3175556666'],
            ['name' => 'Innovagas', 'nit' => '900998877-1', 'email' => 'soporte@innovagas.co', 'phone' => '3016667777'],
            ['name' => 'Interlift', 'nit' => '830998811-0', 'email' => 'gerencia@interlift.com.co', 'phone' => '3168884444'],
            ['name' => 'AS Ingeniería', 'nit' => '901223344-9', 'email' => 'proyectos@asingenieria.com', 'phone' => '3142223333'],
            ['name' => 'Equipos y Vias', 'nit' => '890443322-1', 'email' => 'logistica@equiposyvias.com', 'phone' => '3139990000'],
            ['name' => 'Hidrodinámica y Estructuras', 'nit' => '900112233-6', 'email' => 'contacto@hye.com.co', 'phone' => '3057778888'],
            ['name' => 'IHM', 'nit' => '800998877-5', 'email' => 'info@ihm.com', 'phone' => '3008889999'],
            ['name' => 'Inducover', 'nit' => '901889900-7', 'email' => 'ventas@inducover.co', 'phone' => '3193332222'],
            ['name' => 'Construcciones Quintana', 'nit' => '800112255-0', 'email' => 'proyectos@quintanaconstrucciones.com', 'phone' => '3114447777']
        ];

        foreach ($contractors as $contractor) {
            DB::table('ftra_contractors')->updateOrInsert(
                ['name' => $contractor['name']],
                [
                    'nit' => $contractor['nit'],
                    'email' => $contractor['email'],
                    'phone' => $contractor['phone'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }
    }
}
