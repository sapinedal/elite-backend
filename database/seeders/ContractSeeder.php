<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContractSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $contracts = [
            // TORRE 2
            [
                'nro' => '1 T2',
                'contractor_name_raw' => 'EMELECT GROUP S.A.S.',
                'type' => 'Mano de Obra',
                'category' => 'torre2',
                'object' => 'Redes Eléctricas e Iluminación Torre 2',
                'amount' => 320000000,
                'status' => 'Vigente',
                'drive_link' => 'https://drive.google.com/drive/folders/1YIUcRPuGpLlfvX_XSymzjekEy4jAqlwD',
                'policy' => [
                    'policy_number' => 'Seguros del Estado 994821',
                    'insurance_company' => 'Seguros del Estado',
                    'insured_value' => 320000000,
                    'end_date' => '2026-11-30'
                ]
            ],
            [
                'nro' => '2 T2',
                'contractor_name_raw' => 'YANCELLY ASTRID MONSALVE YEPES',
                'type' => 'Mano de Obra',
                'category' => 'torre2',
                'object' => 'Mampostería, Estructura y Acabados Torre 2',
                'amount' => 485500000,
                'status' => 'Vigente',
                'drive_link' => 'https://drive.google.com/drive/folders/1YIUcRPuGpLlfvX_XSymzjekEy4jAqlwD',
                'policy' => [
                    'policy_number' => 'Suramericana 4182910',
                    'insurance_company' => 'Suramericana',
                    'insured_value' => 485500000,
                    'end_date' => '2027-01-15'
                ]
            ],
            [
                'nro' => '3 T2',
                'contractor_name_raw' => 'IHC S.A.S',
                'type' => 'Suministro e Instalación',
                'category' => 'torre2',
                'object' => 'Redes Hidrosanitarias y Gas Torre 2',
                'amount' => 245000000,
                'status' => 'Vigente',
                'drive_link' => 'https://drive.google.com/drive/folders/1YIUcRPuGpLlfvX_XSymzjekEy4jAqlwD',
                'policy' => [
                    'policy_number' => 'Seguros Mundial M-98214',
                    'insurance_company' => 'Seguros Mundial',
                    'insured_value' => 245000000,
                    'end_date' => '2027-02-28'
                ]
            ],
            [
                'nro' => '4 T2',
                'contractor_name_raw' => 'IASS S.A.S',
                'type' => 'Suministro e Instalación',
                'category' => 'torre2',
                'object' => 'Suministro e Instalación Subestación Eléctrica',
                'amount' => 189000000,
                'status' => 'Vigente',
                'drive_link' => 'https://drive.google.com/drive/folders/1YIUcRPuGpLlfvX_XSymzjekEy4jAqlwD',
                'policy' => [
                    'policy_number' => 'Suramericana 439182',
                    'insurance_company' => 'Suramericana',
                    'insured_value' => 189000000,
                    'end_date' => '2026-12-10'
                ]
            ],
            [
                'nro' => '5 T2',
                'contractor_name_raw' => 'VENTANERÍA Y ALUMINIOS SAN MIGUEL',
                'type' => 'Suministro e Instalación',
                'category' => 'torre2',
                'object' => 'Suministro e Instalación Ventanería Torre 2',
                'amount' => 168000000,
                'status' => 'Por Vencer',
                'drive_link' => 'https://drive.google.com/drive/folders/1YIUcRPuGpLlfvX_XSymzjekEy4jAqlwD',
                'policy' => [
                    'policy_number' => 'Seguros del Estado 881923',
                    'insurance_company' => 'Seguros del Estado',
                    'insured_value' => 168000000,
                    'end_date' => '2026-08-14'
                ]
            ],
            [
                'nro' => '6 a 16 T2',
                'contractor_name_raw' => 'Carpintería, Ascensores, Impermeabilización',
                'type' => 'Varios Acabados',
                'category' => 'torre2',
                'object' => 'Acabados, Pintura, Puertas y Aparatos Sanitarios',
                'amount' => 890000000,
                'status' => 'Vigente',
                'drive_link' => 'https://drive.google.com/drive/folders/1YIUcRPuGpLlfvX_XSymzjekEy4jAqlwD',
                'policy' => [
                    'policy_number' => 'Varios 992011',
                    'insurance_company' => 'Aseguradoras Varias',
                    'insured_value' => 890000000,
                    'end_date' => '2027-03-31'
                ]
            ],

            // URBANISMO Y ZONAS COMUNES
            [
                'nro' => '1 URB',
                'contractor_name_raw' => 'INNOVAGAS S.A.S',
                'type' => 'Redes Externas',
                'category' => 'urbanismo',
                'object' => 'Redes de Gas Urbanismo y Exteriores',
                'amount' => 150000000,
                'status' => 'En Trámite',
                'drive_link' => 'https://drive.google.com/drive/folders/1YIUcRPuGpLlfvX_XSymzjekEy4jAqlwD',
                'policy' => null
            ],
            [
                'nro' => '2 URB',
                'contractor_name_raw' => 'EQUIPOS Y VIAS',
                'type' => 'Obra Civil',
                'category' => 'urbanismo',
                'object' => 'Construcción de Vías y Andenes Urbanismo',
                'amount' => 448657205,
                'status' => 'Vigente',
                'drive_link' => 'https://drive.google.com/drive/folders/1YIUcRPuGpLlfvX_XSymzjekEy4jAqlwD',
                'policy' => [
                    'policy_number' => 'Suramericana 4282970',
                    'insurance_company' => 'Suramericana',
                    'insured_value' => 448657205,
                    'end_date' => '2027-04-30'
                ]
            ],
            [
                'nro' => '3 URB',
                'contractor_name_raw' => 'HIDRODINAMICA Y ESTRUCTURAS S.A.S',
                'type' => 'Redes Hidrosanitarias',
                'category' => 'urbanismo',
                'object' => 'Redes de Acueducto y Alcantarillado',
                'amount' => 384000000,
                'status' => 'Vigente',
                'drive_link' => 'https://drive.google.com/drive/folders/1YIUcRPuGpLlfvX_XSymzjekEy4jAqlwD',
                'policy' => [
                    'policy_number' => 'Seguros Mundial M-100266629',
                    'insurance_company' => 'Seguros Mundial',
                    'insured_value' => 384000000,
                    'end_date' => '2027-05-15'
                ]
            ],
            [
                'nro' => '4 URB',
                'contractor_name_raw' => 'AS INGENIERIA S.A.S',
                'type' => 'Obra Civil',
                'category' => 'urbanismo',
                'object' => 'Construcción Portería Principal',
                'amount' => 169243775,
                'status' => 'Vigente',
                'drive_link' => 'https://drive.google.com/drive/folders/1YIUcRPuGpLlfvX_XSymzjekEy4jAqlwD',
                'policy' => [
                    'policy_number' => 'Suramericana 4363231',
                    'insurance_company' => 'Suramericana',
                    'insured_value' => 169243775,
                    'end_date' => '2026-11-20'
                ]
            ],
            [
                'nro' => '5 URB',
                'contractor_name_raw' => 'IASS S.A.S',
                'type' => 'Redes Eléctricas',
                'category' => 'urbanismo',
                'object' => 'Redes Eléctricas Exteriores Urbanismo',
                'amount' => 116194864,
                'status' => 'Vigente',
                'drive_link' => 'https://drive.google.com/drive/folders/1YIUcRPuGpLlfvX_XSymzjekEy4jAqlwD',
                'policy' => [
                    'policy_number' => 'Seguros Mundial M-100249799',
                    'insurance_company' => 'Seguros Mundial',
                    'insured_value' => 116194864,
                    'end_date' => '2026-12-31'
                ]
            ],
            [
                'nro' => '6 URB',
                'contractor_name_raw' => 'IHC S.A.S',
                'type' => 'Red Contraincendio',
                'category' => 'urbanismo',
                'object' => 'Red Contraincendio Urbanismo',
                'amount' => 76941827,
                'status' => 'Vigente',
                'drive_link' => 'https://drive.google.com/drive/folders/1YIUcRPuGpLlfvX_XSymzjekEy4jAqlwD',
                'policy' => [
                    'policy_number' => 'Suramericana 4371437',
                    'insurance_company' => 'Suramericana',
                    'insured_value' => 76941827,
                    'end_date' => '2027-01-30'
                ]
            ],
            [
                'nro' => '7.1 URB',
                'contractor_name_raw' => 'IHM IGNACIO GÓMEZ S.A.S',
                'type' => 'Equipos Especiales',
                'category' => 'urbanismo',
                'object' => 'Equipos Presión Diferencial y Eyector',
                'amount' => 3391676,
                'status' => 'Vigente',
                'drive_link' => 'https://drive.google.com/drive/folders/1YIUcRPuGpLlfvX_XSymzjekEy4jAqlwD',
                'policy' => [
                    'policy_number' => 'Amparo Cumplimiento 1029',
                    'insurance_company' => 'Suramericana',
                    'insured_value' => 3391676,
                    'end_date' => '2026-10-31'
                ]
            ],
            [
                'nro' => '8 URB',
                'contractor_name_raw' => 'IHM IGNACIO GÓMEZ S.A.S',
                'type' => 'Equipos Especiales',
                'category' => 'urbanismo',
                'object' => 'Bomba RCI Red Contraincendio',
                'amount' => 11115552,
                'status' => 'Vigente',
                'drive_link' => 'https://drive.google.com/drive/folders/1YIUcRPuGpLlfvX_XSymzjekEy4jAqlwD',
                'policy' => [
                    'policy_number' => 'Amparo Cumplimiento 1030',
                    'insurance_company' => 'Suramericana',
                    'insured_value' => 11115552,
                    'end_date' => '2026-10-31'
                ]
            ],
            [
                'nro' => '9 URB',
                'contractor_name_raw' => 'INDUCOVER',
                'type' => 'Impermeabilización',
                'category' => 'urbanismo',
                'object' => 'Impermeabilización Tanque de Agua',
                'amount' => 30488337,
                'status' => 'Vigente',
                'drive_link' => 'https://drive.google.com/drive/folders/1YIUcRPuGpLlfvX_XSymzjekEy4jAqlwD',
                'policy' => [
                    'policy_number' => 'Garantía 5 Años - 88192',
                    'insurance_company' => 'Seguros del Estado',
                    'insured_value' => 30488337,
                    'end_date' => '2031-06-30'
                ]
            ],
            [
                'nro' => '10 URB',
                'contractor_name_raw' => 'CONSTRUCCIONES QUINTANA',
                'type' => 'Obra Civil',
                'category' => 'urbanismo',
                'object' => 'Muro de Contención M1 / M4',
                'amount' => 20724845,
                'status' => 'Vigente',
                'drive_link' => 'https://drive.google.com/drive/folders/1YIUcRPuGpLlfvX_XSymzjekEy4jAqlwD',
                'policy' => [
                    'policy_number' => 'Suramericana 429108',
                    'insurance_company' => 'Suramericana',
                    'insured_value' => 20724845,
                    'end_date' => '2026-12-15'
                ]
            ]
        ];

        foreach ($contracts as $item) {
            $contractor = DB::table('ftra_contractors')
                ->where('name', 'LIKE', '%' . explode(' ', $item['contractor_name_raw'])[0] . '%')
                ->first();

            $contractId = DB::table('contracts')->insertGetId([
                'nro' => $item['nro'],
                'contractor_id' => $contractor ? $contractor->id : null,
                'contractor_name_raw' => $item['contractor_name_raw'],
                'type' => $item['type'],
                'category' => $item['category'],
                'object' => $item['object'],
                'amount' => $item['amount'],
                'status' => $item['status'],
                'drive_link' => $item['drive_link'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($item['policy']) {
                DB::table('policies')->insert([
                    'contract_id' => $contractId,
                    'policy_number' => $item['policy']['policy_number'],
                    'insurance_company' => $item['policy']['insurance_company'],
                    'insured_value' => $item['policy']['insured_value'],
                    'start_date' => '2026-01-01',
                    'end_date' => $item['policy']['end_date'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
