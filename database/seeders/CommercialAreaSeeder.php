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
                                'unit' => 'Citas',
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
                            [
                                'name' => 'Conversiones a Visitas (Volumen)',
                                'definition' => 'Conversión leads a visitas-Agendan y asisten a sala de ventas o recorrido virtual/presencial',
                                'formula' => '+Visitas_Mes_Actual/Visitas_Mes_Anterior*100',
                                'fixed_goal' => 0,
                                'conditional_goals' => [
                                    ['level' => 'Excelente', 'min_value' => 30, 'max_value' => 999, 'qualification' => 'Meta alcanzada o superada', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 15, 'max_value' => 29, 'qualification' => 'Buen desempeño, requiere seguimiento', 'color' => 'acceptable', 'score' => 60],
                                    ['level' => 'En riesgo', 'min_value' => 0, 'max_value' => 14.99, 'qualification' => 'Bajo cumplimiento, tendencia descendente', 'color' => 'at_risk', 'score' => 10],
                                ],
                                'parameters' => [
                                    ['name' => 'Visitas_Mes_Anterior', 'value' => 0],
                                    ['name' => 'Visitas_Mes_Actual', 'value' => 0],
                                ],
                            ],
                            [
                                'name' => 'Tasa de Conversión a Visitas (%)',
                                'definition' => 'Conversión leads a visitas (%)',
                                'formula' => '((Numero_Vistas_Efectivas/Total_Leads_Calificados)*100)',
                                'fixed_goal' => 0,
                                'conditional_goals' => [
                                    ['level' => 'Excelente', 'min_value' => 30, 'max_value' => 999, 'qualification' => 'Meta alcanzada o superada', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 15, 'max_value' => 29, 'qualification' => 'Buen desempeño, requiere seguimiento', 'color' => 'acceptable', 'score' => 90],
                                    ['level' => 'En riesgo', 'min_value' => 0, 'max_value' => 14.99, 'qualification' => 'Bajo cumplimiento, tendencia descendente', 'color' => 'at_risk', 'score' => 70],
                                ],
                                'parameters' => [
                                    ['name' => 'Numero_Vistas_Efectivas', 'value' => 0],
                                    ['name' => 'Total_Leads_Calificados', 'value' => 0],
                                ],
                            ],
                            [
                                'name' => 'Tasa de Conversión a Ventas (%)',
                                'definition' => 'Conversion de visitas a ventas   (%)',
                                'formula' => '((Numero_Ventas_Digitales/Total_Visitas)*100)',
                                'fixed_goal' => 0,
                                'conditional_goals' => [
                                    ['level' => 'Excelente', 'min_value' => 3, 'max_value' => 999, 'qualification' => 'Meta alcanzada o superada', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 1, 'max_value' => 2.99, 'qualification' => 'Buen desempeño, requiere seguimiento', 'color' => 'acceptable', 'score' => 90],
                                    ['level' => 'En riesgo', 'min_value' => 0, 'max_value' => 0.99, 'qualification' => 'Bajo cumplimiento, tendencia descendente', 'color' => 'at_risk', 'score' => 70],
                                ],
                                'parameters' => [
                                    ['name' => 'Numero_Ventas_Digitales', 'value' => 0],
                                    ['name' => 'Total_Visitas', 'value' => 0],
                                ],
                            ],
                            [
                                'name' => 'Tasa de Conversión Global de ventas (%)',
                                'definition' => 'Mide la efectividad total del proceso comercial para convertir todos los leads generados (de cualquier canal) en ventas efectivas.',
                                'formula' => '((Numero_Ventas_Digitales_Confirmadas/Total_Leeds_Generados)*100)',
                                'fixed_goal' => 0,
                                'conditional_goals' => [
                                    ['level' => 'Excelente', 'min_value' => 3, 'max_value' => 999, 'qualification' => 'Meta alcanzada o superada', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 80, 'max_value' => 99, 'qualification' => 'Buen desempeño, requiere seguimiento', 'color' => 'acceptable', 'score' => 80],
                                    ['level' => 'En riesgo', 'min_value' => 60, 'max_value' => 79, 'qualification' => 'Bajo cumplimiento, tendencia descendente', 'color' => 'at_risk', 'score' => 0],
                                ],
                                'parameters' => [
                                    ['name' => 'Numero_Ventas_Digitales_Confirmadas', 'value' => 0],
                                    ['name' => 'Total_Leeds_Generados', 'value' => 0],
                                ],
                            ],
                            [
                                'name' => 'Participación de Ventas por Canales Digitales',
                                'definition' => 'Mide el porcentaje de ventas totales del proyecto que se originaron desde canales digitales (campañas pagas, redes sociales, formularios web, CRM, etc.). Permite identificar la efectividad del canal digital como fuente de cierres y su aporte a los resultados comerciales generales.',
                                'formula' => '((Ventas_Digitales/Ventas_Totales)*100)',
                                'fixed_goal' => 0,
                                'conditional_goals' => [
                                    ['level' => 'Excelente', 'min_value' => 50, 'max_value' => 999, 'qualification' => 'Meta alcanzada o superada', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 30, 'max_value' => 49, 'qualification' => 'Buen desempeño, requiere seguimiento', 'color' => 'acceptable', 'score' => 80],
                                    ['level' => 'En riesgo', 'min_value' => 0, 'max_value' => 29.99, 'qualification' => 'Bajo cumplimiento, tendencia descendente', 'color' => 'at_risk', 'score' => 0],
                                ],
                                'parameters' => [
                                    ['name' => 'Ventas_Digitales', 'value' => 0],
                                    ['name' => 'Ventas_Totales', 'value' => 0],
                                ],
                            ],
                            [
                                'name' => 'Tasa de contacto inicial',
                                'definition' => 'Mide qué porcentaje de los leads recibidos fueron contactados efectivamente. Refleja la agilidad del equipo en el primer acercamiento.',
                                'formula' => '((Leads_Gestionados/Leads_Totales)*100)',
                                'fixed_goal' => 0,
                                'conditional_goals' => [
                                    ['level' => 'Excelente', 'min_value' => 85, 'max_value' => 999, 'qualification' => 'Meta alcanzada o superada', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 70, 'max_value' => 84.99, 'qualification' => 'Buen desempeño, requiere seguimiento', 'color' => 'acceptable', 'score' => 80],
                                    ['level' => 'En riesgo', 'min_value' => 0, 'max_value' => 70, 'qualification' => 'Bajo cumplimiento, tendencia descendente', 'color' => 'at_risk', 'score' => 0],
                                ],
                                'parameters' => [
                                    ['name' => 'Leads_Gestionados', 'value' => 0],
                                    ['name' => 'Leads_Totales', 'value' => 0],
                                ],
                            ],
                            [
                                'name' => 'Leads perdidos',
                                'definition' => 'Mide el porcentaje de leads que no avanzan en el embudo de ventas y se pierden antes de concretar una oportunidad o cierre, identificando las principales causas para tomar acciones correctivas en la gestión comercial y en la estrategia de generación de leads.',
                                'formula' => '((No_Contesta_Seguimiento + Imposible_Contacto + Perdio_Interes + Causa_Desconocida + Presupuesto + Duplicado + Aplaza_Compra + Furioso + Busca_Otro_Sector + Ya_Compro + No_Le_Gusta_Producto + Cercano_Al_Rio + Busca_Otra_Ciudad + Futuros_Proyectos + Busca_Renta + No_Tiene_Caja_Compensación + Prueba + Castigo_DataCredito + Reporte_Centrales_Riesgo)/Total_Leeds * 100)',
                                'unit' => '%',
                                'parameters' => [
                                    ['name' => 'No_Contesta_Seguimiento', 'value' => 0],
                                    ['name' => 'Imposible_Contacto', 'value' => 0],
                                    ['name' => 'Perdio_Interes', 'value' => 0],
                                    ['name' => 'Causa_Desconocida', 'value' => 0],
                                    ['name' => 'Presupuesto', 'value' => 0],
                                    ['name' => 'Duplicado', 'value' => 0],
                                    ['name' => 'Aplaza_Compra', 'value' => 0],
                                    ['name' => 'Furioso', 'value' => 0],
                                    ['name' => 'Busca_Otro_Sector', 'value' => 0],
                                    ['name' => 'Ya_Compro', 'value' => 0],
                                    ['name' => 'No_Le_Gusta_Producto', 'value' => 0],
                                    ['name' => 'Cercano_Al_Rio', 'value' => 0],
                                    ['name' => 'Busca_Otra_Ciudad', 'value' => 0],
                                    ['name' => 'Futuros_Proyectos', 'value' => 0],
                                    ['name' => 'Busca_Renta', 'value' => 0],
                                    ['name' => 'No_Tiene_Caja_Compensación', 'value' => 0],
                                    ['name' => 'Prueba', 'value' => 0],
                                    ['name' => 'Castigo_DataCredito', 'value' => 0],
                                    ['name' => 'Reporte_Centrales_Riesgo', 'value' => 0],
                                    ['name' => 'Total_Leeds', 'value' => 1],
                                ],
                                'conditional_goals' => [
                                    ['level' => 'Excelente', 'min_value' => 0, 'max_value' => 25, 'qualification' => 'Bajo nivel de pérdida', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 26, 'max_value' => 45, 'qualification' => 'Pérdida moderada', 'color' => 'acceptable', 'score' => 80],
                                    ['level' => 'En riesgo', 'min_value' => 46, 'max_value' => 100, 'qualification' => 'Fuga crítica de prospectos', 'color' => 'deficient', 'score' => 0],
                                ],
                            ],
                            [
                                'name' => 'Participación de Mercado',
                                'definition' => 'Compara la cuota de mercado de la empresa frente a la competencia.',
                                'formula' => '(Ventas_Empresa / Ventas_Totales_Mercado) * 100',
                                'unit' => '%',
                                'parameters' => [
                                    ['name' => 'Ventas_Empresa', 'value' => 0],
                                    ['name' => 'Ventas_Totales_Mercado', 'value' => 1],
                                ],
                                'tablaDetalle' => [
                                    'headers' => ['PROYECTO', 'VENTAS'],
                                    'rows' => [
                                        ['CIUDADELA SAN MIGUEL', ''],
                                        ['CIUDAD JARDIN', ''],
                                        ['TIERRA DULCE (EME)', ''],
                                        ['FONTANAR (BIENES Y BIENES)', ''],
                                        ['SONORA MONTANA (CONINSA)', ''],
                                        ['MAZZÚ (CONTEX)', ''],
                                        ['FERRATORE (CENTROSUR)', ''],
                                        ['ANACONAS', ''],
                                        ['AZZURI (NUEVO LANZAMIENTO)', ''],
                                    ]
                                ],
                                'conditional_goals' => [
                                    ['level' => 'Excelente', 'min_value' => 15, 'max_value' => 999, 'qualification' => 'Alta competitividad y liderazgo', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 10, 'max_value' => 14.99, 'qualification' => 'En línea con el promedio del mercado', 'color' => 'acceptable', 'score' => 90],
                                    ['level' => 'En riesgo', 'min_value' => 7, 'max_value' => 9.99, 'qualification' => 'Disminución de posicionamiento', 'color' => 'at_risk', 'score' => 70],
                                    ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 6.99, 'qualification' => 'Bajo desempeño frente a la competencia', 'color' => 'deficient', 'score' => 0],
                                ],
                            ],
                            [
                                'name' => 'Número de Alianzas Estratégicas Formalizadas',
                                'definition' => 'Mide la generación de sinergias y alianzas útiles.',
                                'formula' => '(Numero_Alianzas_Formalizadas / Meta_Trimestral) * 100',
                                'unit' => '%',
                                'parameters' => [
                                    ['name' => 'Numero_Alianzas_Formalizadas', 'value' => 0],
                                    ['name' => 'Meta_Trimestral', 'value' => 6],
                                ],
                                'conditional_goals' => [
                                    ['level' => 'Óptimo', 'min_value' => 100, 'max_value' => 999, 'qualification' => 'Meta cumplida o superada', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 70, 'max_value' => 99, 'qualification' => 'Avance favorable, en curso', 'color' => 'acceptable', 'score' => 80],
                                    ['level' => 'Bajo', 'min_value' => 0, 'max_value' => 69, 'qualification' => 'Avance insuficiente', 'color' => 'at_risk', 'score' => 0],
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
                        'indicators' => [
                            [
                                'name' => 'Cumplimiento de Meta Individual del Equipo',
                                'definition' => 'Promedio de cumplimiento de metas individuales de asesores.',
                                'formula' => '((Ventas_Ingrid/Meta_Ingrid) + (Ventas_Natalia/Meta_Natalia) + (Ventas_Paola/Meta_Paola)) / 3 * 100',
                                'unit' => '%',
                                'parameters' => [
                                    ['name' => 'Meta_Ingrid', 'value' => 0],
                                    ['name' => 'Ventas_Ingrid', 'value' => 0],
                                    ['name' => 'Meta_Natalia', 'value' => 0],
                                    ['name' => 'Ventas_Natalia', 'value' => 0],
                                    ['name' => 'Meta_Paola', 'value' => 0],
                                    ['name' => 'Ventas_Paola', 'value' => 0],
                                ],
                                'tablaDetalle' => [
                                    'headers' => ['ASESOR', 'VENTAS', 'META', '% CUMPL'],
                                    'rows' => [
                                        ['Ingrid', '', '', ''],
                                        ['Natalia', '', '', ''],
                                        ['Paola', '', '', ''],
                                    ]
                                ],
                                'conditional_goals' => [
                                    ['level' => 'Óptimo', 'min_value' => 90, 'max_value' => 999, 'qualification' => 'Meta cumplida', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 70, 'max_value' => 89.99, 'qualification' => 'Avance moderado', 'color' => 'acceptable', 'score' => 80],
                                    ['level' => 'Bajo', 'min_value' => 0, 'max_value' => 69.99, 'qualification' => 'Desempeño bajo', 'color' => 'at_risk', 'score' => 0],
                                ],
                            ],
                            [
                                'name' => 'Rotación del Personal Comercial',
                                'definition' => 'Mide la estabilidad y permanencia del equipo.',
                                'formula' => '(Numero_Bajas_Año / Promedio_Personal_Comercial) * 100',
                                'unit' => '%',
                                'parameters' => [
                                    ['name' => 'Numero_Bajas_Año', 'value' => 0],
                                    ['name' => 'Promedio_Personal_Comercial', 'value' => 4],
                                ],
                                'conditional_goals' => [
                                    ['level' => 'Excelente', 'min_value' => 0, 'max_value' => 15, 'qualification' => 'Meta alcanzada o superada (< 15%)', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 15.01, 'max_value' => 25, 'qualification' => 'Rotación moderada, requiere seguimiento', 'color' => 'acceptable', 'score' => 80],
                                    ['level' => 'Bajo', 'min_value' => 25.01, 'max_value' => 100, 'qualification' => 'Alta rotación, impacto en estabilidad', 'color' => 'at_risk', 'score' => 0],
                                ],
                            ],
                            [
                                'name' => 'Contribución al Ambiente Laboral',
                                'definition' => 'Evaluación de colaboración, actitud y liderazgo (Evaluación 360° / Feedback Interno).',
                                'formula' => 'Evaluacion_360',
                                'unit' => 'pts (1-5)',
                                'parameters' => [
                                    ['name' => 'Evaluacion_360', 'value' => 0],
                                ],
                                'conditional_goals' => [
                                    ['level' => 'Excelente', 'min_value' => 4.5, 'max_value' => 5, 'qualification' => 'Liderazgo inspirador y excelente ambiente', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 3.5, 'max_value' => 4.49, 'qualification' => 'Buen desempeño, requiere mejorar habilidades blandas', 'color' => 'acceptable', 'score' => 90],
                                    ['level' => 'Bajo', 'min_value' => 0, 'max_value' => 3.49, 'qualification' => 'Impacto negativo en el equipo, requiere intervención', 'color' => 'at_risk', 'score' => 0],
                                ],
                            ],
                            [
                                'name' => 'Índice de Productividad por Asesor',
                                'definition' => 'Mide ventas por asesor frente a leads asignados o tiempo invertido.',
                                'formula' => '((Ventas_Ingrid/Leads_Ingrid) + (Ventas_Natalia/Leads_Natalia) + (Ventas_Paola/Leads_Paola)) / 3 * 100',
                                'unit' => '%',
                                'parameters' => [
                                    ['name' => 'Ventas_Ingrid', 'value' => 0],
                                    ['name' => 'Leads_Ingrid', 'value' => 1],
                                    ['name' => 'Ventas_Natalia', 'value' => 0],
                                    ['name' => 'Leads_Natalia', 'value' => 1],
                                    ['name' => 'Ventas_Paola', 'value' => 0],
                                    ['name' => 'Leads_Paola', 'value' => 1],
                                ],
                                'tablaDetalle' => [
                                    'headers' => ['ASESORA', 'VENTAS DIGITALES', 'LEADS', '% VENTAS'],
                                    'rows' => [
                                        ['Ingrid', '', '', ''],
                                        ['Natalia', '', '', ''],
                                        ['Paola', '', '', ''],
                                    ]
                                ],
                                'conditional_goals' => [
                                    ['level' => 'Óptimo', 'min_value' => 2.5, 'max_value' => 999, 'qualification' => 'Excelente productividad por asesor', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 1.8, 'max_value' => 2.49, 'qualification' => 'Productividad dentro del rango esperado', 'color' => 'acceptable', 'score' => 90],
                                    ['level' => 'Bajo', 'min_value' => 0, 'max_value' => 1.79, 'qualification' => 'Productividad por debajo del objetivo', 'color' => 'at_risk', 'score' => 0],
                                ],
                            ],
                        ],
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
                                'name' => 'Precisión y Actualización del CRM',
                                'definition' => 'Mide la calidad, integridad y actualidad de los negocios registrados en el CRM (Traslados y Promesas).',
                                'formula' => '(Negocios_Completos / Total_Negocios_CRM) * 100',
                                'unit' => '%',
                                'parameters' => [
                                    ['name' => 'Negocios_Completos', 'value' => 0],
                                    ['name' => 'Total_Negocios_CRM', 'value' => 1],
                                ],
                                'tablaDetalle' => [
                                    'headers' => ['ASESOR', 'TRASLADOS REALIZADOS', 'FIRMAS PROMESAS', '% EFECTIVIDAD'],
                                    'rows' => [
                                        ['Ingrid', '', '', ''],
                                        ['Natalia', '', '', ''],
                                        ['Paola', '', '', ''],
                                    ]
                                ],
                                'conditional_goals' => [
                                    ['level' => 'Óptimo', 'min_value' => 98, 'max_value' => 100, 'qualification' => 'Cumple o supera la meta (Datos íntegros)', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 90, 'max_value' => 97.99, 'qualification' => 'En rango de mejora, faltan datos puntuales', 'color' => 'acceptable', 'score' => 80],
                                    ['level' => 'Bajo', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => 'No cumple con la meta (Información desactualizada)', 'color' => 'at_risk', 'score' => 0],
                                ],
                            ],
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
                            [
                                'name' => 'Porcentaje de Ventas por Canal',
                                'definition' => 'Distribución de ventas por origen del cliente (Digital, Referidos, Tradicional).',
                                'formula' => '((Venta_Digital_Real/Venta_Digital_Meta) + (Referidos_Real/Referidos_Meta) + (Tradicional_Real/Tradicional_Meta)) / 3 * 100',
                                'unit' => '%',
                                'parameters' => [
                                    ['name' => 'Venta_Digital_Meta', 'value' => 5],
                                    ['name' => 'Venta_Digital_Real', 'value' => 0],
                                    ['name' => 'Referidos_Meta', 'value' => 3],
                                    ['name' => 'Referidos_Real', 'value' => 0],
                                    ['name' => 'Tradicional_Meta', 'value' => 2],
                                    ['name' => 'Tradicional_Real', 'value' => 0],
                                ],
                                'tablaDetalle' => [
                                    'headers' => ['CANAL', 'META', 'REAL', '% CUMPL'],
                                    'rows' => [
                                        ['Venta Digital', '', '', ''],
                                        ['Referidos', '', '', ''],
                                        ['Tradicional', '', '', ''],
                                    ]
                                ],
                                'conditional_goals' => [
                                    ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 999, 'qualification' => 'Meta alcanzada o superada', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 80, 'max_value' => 99.99, 'qualification' => 'Cumplimiento parcial satisfactorio', 'color' => 'excellent', 'score' => 80],
                                    ['level' => 'Bajo', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => 'Bajo cumplimiento por canales', 'color' => 'at_risk', 'score' => 0],
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
                        'indicators' => [
                            [
                                'name' => '% Documentación Comercial Archivada Correctamente',
                                'definition' => 'Organización y cumplimiento en archivo y disponibilidad.',
                                'formula' => '((Numero_Documentos_Completos/Total_Documentos_Generados)*100)',
                                'fixed_goal' => 0,
                                'conditional_goals' => [
                                    ['level' => 'Excelente', 'min_value' => 98, 'max_value' => 999, 'qualification' => 'Meta alcanzada o superada', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 90, 'max_value' => 97, 'qualification' => 'Buen desempeño, requiere seguimiento', 'color' => 'acceptable', 'score' => 90],
                                    ['level' => 'En riesgo', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => 'Bajo cumplimiento, tendencia descendente', 'color' => 'at_risk', 'score' => 0],
                                ],
                                'parameters' => [
                                    ['name' => 'Total_Documentos_Generados', 'value' => 0],
                                    ['name' => 'Numero_Documentos_Completos', 'value' => 0],
                                ],
                            ],
                            [
                                'name' => 'Mide la oportunidad y cumplimiento en la entrega de los informes de ventas semanales dentro de los plazos establecidos.',
                                'definition' => 'Envio de informe el día viernes de cada semana',
                                'formula' => '((Numero_Informes_Enviados/Viernes_Del_Mes)*100)',
                                'fixed_goal' => 100,
                                'conditional_goals' => [
                                    ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 999, 'qualification' => 'Meta alcanzada o superada', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Aceptable', 'min_value' => 80, 'max_value' => 99, 'qualification' => 'Buen desempeño, requiere seguimiento', 'color' => 'acceptable', 'score' => 90],
                                    ['level' => 'En riesgo', 'min_value' => 60, 'max_value' => 79, 'qualification' => 'Bajo cumplimiento, tendencia descendente', 'color' => 'at_risk', 'score' => 70],
                                    ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 59, 'qualification' => 'Incumplimiento crítico', 'color' => 'deficient', 'score' => 0],
                                ],
                                'parameters' => [
                                    ['name' => 'Viernes_Del_Mes', 'value' => 4],
                                    ['name' => 'Numero_Informes_Enviados', 'value' => 0],
                                ],
                            ],
                            [
                                'name' => 'Entrega de Informe General del Área - Cierre de Mes',
                                'definition' => 'Envio de informe general del área del cierre de mes dentro de los plazos establecidos.',
                                'formula' => '(Informes_Enviados / 1) * 100',
                                'unit' => '%',
                                'parameters' => [
                                    ['name' => 'Informes_Enviados', 'value' => 0],
                                    ['name' => 'Fecha_Envio', 'value' => ''],
                                ],
                                'conditional_goals' => [
                                    ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 100, 'qualification' => 'Informe enviado a tiempo', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 99.99, 'qualification' => 'Informe no enviado o fuera de plazo', 'color' => 'deficient', 'score' => 0],
                                ],
                            ],
                            [
                                'name' => 'Tiempo Promedio de Respuesta a Derechos de Petición/Desistimientos',
                                'definition' => 'Cumplimiento de tiempos legales y manual de procedimientos.',
                                'formula' => 'Pendiente por definir',
                                'unit' => '%',
                                'parameters' => [],
                                'conditional_goals' => [
                                    ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 100, 'qualification' => '100% en tiempo establecido', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Bajo', 'min_value' => 0, 'max_value' => 99.99, 'qualification' => 'Incumplimiento de tiempos legales', 'color' => 'at_risk', 'score' => 0],
                                ],
                            ],
                            [
                                'name' => 'Nº de Fallas Operativas o Errores Procesales Reportados y Solucionados',
                                'definition' => 'Proactividad en la identificación y resolución de problemas.',
                                'formula' => 'Pendiente por definir',
                                'unit' => '%',
                                'parameters' => [],
                                'conditional_goals' => [
                                    ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 100, 'qualification' => '100% de las fallas solucionadas', 'color' => 'excellent', 'score' => 100],
                                    ['level' => 'Bajo', 'min_value' => 0, 'max_value' => 99.99, 'qualification' => 'Fallas pendientes por solucionar', 'color' => 'at_risk', 'score' => 0],
                                ],
                            ],
                        ],
                    ],
                ];

                foreach ($kpis as $kpiData) {
                    $user->kpis()->updateOrCreate(['name' => $kpiData['name']], $kpiData);
                }
            }
        }
    }
}
