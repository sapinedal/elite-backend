<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Http\Modules\Users\Models\User;
use App\Http\Modules\Plantillas\Models\KPI;
use App\Http\Modules\Configuracion\Models\Area;
use App\Http\Modules\Configuracion\Models\Position;

class CommercialAreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Aseguramos que el área comercial exista
        $comercialArea = Area::firstOrCreate(
            ['name' => 'Comercial'],
            ['description' => 'Área encargada de las ventas y estrategia comercial']
        );

        $users = [
            [
                'first_name' => 'SARA ELENA',
                'last_name' => 'MORENO OROZCO',
                'name' => 'SARA ELENA MORENO OROZCO',
                'document' => '53081362',
                'position_name' => 'Directora comercial',
                'email' => 'sara.moreno@elite.com',
                'password' => bcrypt('Elite123'),
            ],
            [
                'first_name' => 'INGRID PAOLA',
                'last_name' => 'OSPICIO PACHECO',
                'name' => 'INGRID PAOLA OSPICIO PACHECO',
                'document' => '15443145',
                'position_name' => 'Líder de sala',
                'email' => 'ingrid.ospicio@elite.com',
                'password' => bcrypt('Elite123'),
            ],
            [
                'first_name' => 'PAOLA ANDREA',
                'last_name' => 'ARENAS GAVIRIA',
                'name' => 'PAOLA ANDREA ARENAS GAVIRIA',
                'document' => '16472142',
                'position_name' => 'Asesora comercial',
                'email' => 'paola.arenas@elite.com',
                'password' => bcrypt('Elite123'),
            ],
            [
                'first_name' => 'NATALIA ANDREA',
                'last_name' => 'POSADA RAVE',
                'name' => 'NATALIA ANDREA POSADA RAVE',
                'document' => '32151064',
                'position_name' => 'Asesora comercial',
                'email' => 'natalia.posada@elite.com',
                'password' => bcrypt('Elite123'),
            ],
        ];

        foreach ($users as $userData) {
            // Resolvemos el cargo dinámicamente
            $position = Position::firstOrCreate([
                'name' => $userData['position_name'],
                'area_id' => $comercialArea->id
            ]);

            // Preparamos los datos para el usuario (reemplazamos position_name por los IDs)
            $userPayload = $userData;
            unset($userPayload['position_name']);
            $userPayload['area_id'] = $comercialArea->id;
            $userPayload['position_id'] = $position->id;

            $user = User::updateOrCreate(['email' => $userPayload['email']], $userPayload);

            // Si es Sara, agregar sus KPIs
            if ($user->email === 'sara.moreno@elite.com') {
                $kpis = [
                    [
                        'name' => 'Estrategia y Planificación Comercial',
                        'description' => 'Gestión estratégica del área comercial. Mide el cumplimiento de metas de ventas e inversión comercial.',
                        'formula' => 'Promedio de indicadores de ventas e inversión',
                        'target' => 100,
                        'unit' => '%',
                        'stage' => 'A. Estrategia y Planificación Comercial',
                        'weight' => 30,
                        'lower_is_better' => false,
                        'indicators' => [
                            [
                                'name' => 'Cumplimiento de la Meta Global de Ventas',
                                'definition' => 'Mide el porcentaje de cumplimiento de las metas de ventas mensual, trimestral y anual.',
                                'formula' => '(Ventas_Realizadas / Meta_de_Ventas) * 100',
                                'fixed_goal' => 10,
                                'parameters' => [
                                    ['name' => 'Ventas_Realizadas', 'value' => 0],
                                    ['name' => 'Meta_de_Ventas', 'value' => 0],
                                ],
                                'conditional_goals' => [
                                    ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 999, 'qualification' => 'Meta alcanzada o superada', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 80, 'max_value' => 99, 'qualification' => 'Buen desempeño, requiere seguimiento', 'color' => 'acceptable', 'score' => 90],
                                    ['level' => 'En riesgo', 'min_value' => 60, 'max_value' => 79, 'qualification' => 'Bajo cumplimiento, tendencia descendente', 'color' => 'at_risk', 'score' => 70],
                                    ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 59, 'qualification' => 'Incumplimiento crítico', 'color' => 'deficient', 'score' => 0],
                                ],
                            ],
                            [
                                'name' => 'Total Invertido (Meta + Google Ads)',
                                'definition' => 'Mide la ejecución presupuestal combinada de pauta digital en Meta y Google Ads.',
                                'formula' => '((Inversion_Meta + Inversion_Ads) / (Presupuesto_Meta + Presupuesto_Ads)) * 100',
                                'parameters' => [
                                    ['name' => 'Inversion_Meta', 'value' => 0],
                                    ['name' => 'Presupuesto_Meta', 'value' => 1000000],
                                    ['name' => 'Inversion_Ads', 'value' => 0],
                                    ['name' => 'Presupuesto_Ads', 'value' => 500000],
                                ],
                                'conditional_goals' => [
                                    ['level' => 'Sobreejecución', 'min_value' => 101, 'max_value' => 999, 'qualification' => 'Gasto por encima del presupuesto combinado', 'color' => 'at_risk', 'score' => 80],
                                    ['level' => 'Óptimo', 'min_value' => 90, 'max_value' => 100, 'qualification' => 'Gasto controlado y eficiente', 'color' => 'optimal', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 80, 'max_value' => 89, 'qualification' => 'Subutilización moderada', 'color' => 'acceptable', 'score' => 90],
                                    ['level' => 'Inadecuado', 'min_value' => 0, 'max_value' => 79, 'qualification' => 'Subutilización significativa', 'color' => 'inadequate', 'score' => 0],
                                ],
                            ],
                            [
                                'name' => 'Costo por Lead (CPL) Promedio',
                                'definition' => 'Evalúa la eficiencia de las campañas digitales para generar leads calificados.',
                                'formula' => 'Costo_Total_Digital / Numero_Leads_Calificados',
                                'unit' => '$',
                                'parameters' => [
                                    ['name' => 'Costo_Total_Digital', 'value' => 0],
                                    ['name' => 'Numero_Leads_Calificados', 'value' => 1],
                                ],
                                'conditional_goals' => [
                                    ['level' => 'Óptimo', 'min_value' => 0, 'max_value' => 3000, 'qualification' => 'Eficiencia sobresaliente', 'color' => 'optimal', 'score' => 100],
                                    ['level' => 'Bueno', 'min_value' => 3001, 'max_value' => 7000, 'qualification' => 'Rendimiento adecuado', 'color' => 'acceptable', 'score' => 90],
                                    ['level' => 'Aceptable', 'min_value' => 7001, 'max_value' => 10000, 'qualification' => 'Meta cumplida, optimizable', 'color' => 'at_risk', 'score' => 80],
                                    ['level' => 'Deficiente', 'min_value' => 10001, 'max_value' => 9999999, 'qualification' => 'Meta no cumplida', 'color' => 'deficient', 'score' => 0],
                                ],
                            ],
                            [
                                'name' => 'Cumplimiento de Meta de Leads Generados',
                                'definition' => 'Mide el grado de cumplimiento de la meta establecida de generación de leads mensuales a través de las campañas digitales.',
                                'formula' => '(Leads_Generados / Meta_Leads) * 100',
                                'unit' => '%',
                                'parameters' => [
                                    ['name' => 'Leads_Generados', 'value' => 0],
                                    ['name' => 'Meta_Leads', 'value' => 1500],
                                ],
                                'conditional_goals' => [
                                    ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 1000, 'qualification' => 'Meta alcanzada o superada', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 90, 'max_value' => 99, 'qualification' => 'Rendimiento adecuado, cerca de la meta', 'color' => 'acceptable', 'score' => 90],
                                    ['level' => 'En riesgo', 'min_value' => 80, 'max_value' => 89, 'qualification' => 'Bajo la meta, requiere seguimiento', 'color' => 'at_risk', 'score' => 70],
                                    ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 79, 'qualification' => 'Incumplimiento crítico', 'color' => 'deficient', 'score' => 0],
                                ],
                            ],
                            [
                                'name' => 'Leads Calificados (Calidad de Perfilado)',
                                'definition' => 'Mide el porcentaje de leads que cumplen con el perfil del cliente objetivo (capacidad económica, interés, etapa de decisión).',
                                'formula' => '(Leads_Calificados / Total_Leads_Generados) * 100',
                                'unit' => '%',
                                'parameters' => [
                                    ['name' => 'Leads_Calificados', 'value' => 0],
                                    ['name' => 'Total_Leads_Generados', 'value' => 1],
                                ],
                                'conditional_goals' => [
                                    ['level' => 'Óptimo', 'min_value' => 41, 'max_value' => 1000, 'qualification' => 'Supera ampliamente la meta', 'color' => 'optimal', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 25, 'max_value' => 40, 'qualification' => 'Dentro del rango esperado', 'color' => 'acceptable', 'score' => 90],
                                    ['level' => 'Bajo', 'min_value' => 0, 'max_value' => 24, 'qualification' => 'No cumple con el objetivo', 'color' => 'deficient', 'score' => 0],
                                ],
                            ],
                            [
                                'name' => 'Conversiones a Citas (Volumen)',
                                'definition' => 'Número total de leads con los que se logró comunicación directa y agendamiento de cita.',
                                'formula' => 'Citas_Agendadas',
                                'unit' => ' Citas',
                                'parameters' => [
                                    ['name' => 'Citas_Agendadas', 'value' => 0],
                                ],
                                'conditional_goals' => [
                                    ['level' => 'Excelente', 'min_value' => 150, 'max_value' => 10000, 'qualification' => 'Gran volumen de agendamiento', 'color' => 'optimal', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 80, 'max_value' => 149, 'qualification' => 'Volumen dentro de lo esperado', 'color' => 'acceptable', 'score' => 90],
                                    ['level' => 'Bajo', 'min_value' => 0, 'max_value' => 79, 'qualification' => 'Bajo volumen de contacto efectivo', 'color' => 'deficient', 'score' => 0],
                                ],
                            ],
                            [
                                'name' => 'Tasa de Conversión a Citas (%)',
                                'definition' => 'Proporción de leads calificados que terminan en una cita agendada.',
                                'formula' => '(Citas_Agendadas / Leads_Calificados) * 100',
                                'unit' => '%',
                                'parameters' => [
                                    ['name' => 'Citas_Agendadas', 'value' => 0],
                                    ['name' => 'Leads_Calificados', 'value' => 1],
                                ],
                                'conditional_goals' => [
                                    ['level' => 'Óptimo', 'min_value' => 60, 'max_value' => 1000, 'qualification' => 'Cumple o supera la meta', 'color' => 'optimal', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 40, 'max_value' => 59, 'qualification' => 'Desempeño moderado', 'color' => 'acceptable', 'score' => 80],
                                    ['level' => 'Bajo', 'min_value' => 0, 'max_value' => 39, 'qualification' => 'No cumple con la meta', 'color' => 'deficient', 'score' => 10],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Liderazgo y Gestión del Equipo Comercial',
                        'description' => 'Gestión y desarrollo del equipo de ventas',
                        'formula' => 'Cumplimiento de objetivos del equipo',
                        'target' => 100,
                        'unit' => '%',
                        'stage' => 'B. Liderazgo y Gestión del Equipo Comercial',
                        'weight' => 30,
                        'lower_is_better' => false,
                    ],
                    [
                        'name' => 'Gestión Operativa y Control de Ventas',
                        'description' => 'Control de procesos operativos de venta',
                        'formula' => 'Eficiencia en cierres y prospección',
                        'target' => 100,
                        'unit' => '%',
                        'stage' => 'C. Gestión Operativa y Control de Ventas',
                        'weight' => 30,
                        'lower_is_better' => false,
                        'indicators' => [
                            [
                                'name' => 'Tiempo Promedio de Cierre de Negocios',
                                'definition' => 'Eficiencia en cierre desde primer contacto hasta firma.',
                                'formula' => 'Tiempo promedio (días)= Σ Días de cierre / Nº negocios cerrados',
                                'unit' => 'días',
                                'fixed_goal' => 30,
                                'parameters' => [],
                                'tablaDetalle' => [
                                    'headers' => ['VENTAS', 'FECHA INICIAL', 'CIERRE', 'DIAS'],
                                    'rows' => [['', '', '', '']]
                                ],
                                'conditional_goals' => [
                                    ['level' => 'Excelente', 'min_value' => 0, 'max_value' => 30, 'qualification' => 'Meta alcanzada o superada (≤ 30 días)', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 30.01, 'max_value' => 45, 'qualification' => 'Buen desempeño, dentro del rango aceptable', 'color' => 'acceptable', 'score' => 80],
                                    ['level' => 'Deficiente', 'min_value' => 45.01, 'max_value' => 9999, 'qualification' => 'Incumplimiento crítico (> 45 días)', 'color' => 'deficient', 'score' => 0],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Gestión Documental y Procesos Legales/Internos',
                        'description' => 'Cumplimiento de trámites legales e internos',
                        'formula' => 'Correcto diligenciamiento de promesas y contratos',
                        'target' => 100,
                        'unit' => '%',
                        'stage' => 'D. Gestión Documental y Procesos Legales/Internos',
                        'weight' => 10,
                        'lower_is_better' => false,
                    ],
                ];

                foreach ($kpis as $kpiData) {
                    $user->kpis()->updateOrCreate(['name' => $kpiData['name']], $kpiData);
                }
            }
        }
    }
}
