<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Http\Modules\Users\Models\User;
use App\Http\Modules\Plantillas\Models\KPI;
use App\Http\Modules\Configuracion\Models\Area;
use App\Http\Modules\Configuracion\Models\Position;

class TramitesEscrituracionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Asegurar el Área de Trámites y Escrituración
        $tramitesArea = Area::firstOrCreate(
            ['name' => 'Trámites y Escrituración'],
            ['description' => 'Área encargada de la gestión de cierres financieros, trámites crediticios y escrituración de inmuebles']
        );

        // 2. Cargos pertenecientes al área
        $positions = [
            'Líder de Trámites y Escrituración',
            'Analista de Trámites',
            'Auxiliar de Escrituración',
        ];

        $createdPositions = [];
        foreach ($positions as $posName) {
            $createdPositions[$posName] = Position::firstOrCreate([
                'name' => $posName,
                'area_id' => $tramitesArea->id
            ]);
        }

        // 3. Usuario principal / Líder del área
        $userPayload = [
            'first_name' => 'LÍDER',
            'last_name' => 'TRÁMITES Y ESCRITURACIÓN',
            'name' => 'LÍDER TRÁMITES Y ESCRITURACIÓN',
            'document' => '1000000002',
            'position_id' => $createdPositions['Líder de Trámites y Escrituración']->id,
            'area_id' => $tramitesArea->id,
            'email' => 'tramites.lider@elite.com',
            'password' => bcrypt('Elite123'),
        ];

        $user = User::updateOrCreate(['email' => $userPayload['email']], $userPayload);

        // 4. Definición de los KPIs extraídos de la Evaluación de Desempeño
        $kpis = [
            // ETAPA 1: PRE-CONCRECIÓN
            [
                'name' => 'Porcentaje de Cierres Financieros Logrados a Tiempo',
                'description' => 'Mide la eficacia con que se logran aprobación de créditos, subsidios y pagos de cuota inicial en la fecha establecida.',
                'formula' => '(Cierres_Financieros_Exitosos / Cierres_Financieros_Programados) * 100',
                'target' => 100,
                'unit' => '%',
                'stage' => 'Pre-Concreción',
                'weight' => 20,
                'incidence' => 100,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Cierres Financieros Logrados a Tiempo',
                        'definition' => 'Mide la eficacia con que se logran aprobación de créditos, subsidios y pagos de cuota inicial en la fecha establecida.',
                        'formula' => '(Cierres_Financieros_Exitosos / Cierres_Financieros_Programados) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Cierres_Financieros_Exitosos', 'value' => 25],
                            ['name' => 'Cierres_Financieros_Programados', 'value' => 25],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 999, 'qualification' => 'Meta alcanzada (100%)', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Aceptable', 'min_value' => 80, 'max_value' => 99.99, 'qualification' => 'Buen desempeño, requiere seguimiento', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => 'Incumplimiento crítico', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            [
                'name' => 'Gestión sin Cierre Financiero',
                'description' => 'Gestión del 100% de los clientes que no tienen cierre financiero.',
                'formula' => '(Sin_Cierre_Gestionados / Sin_Cierre_Total) * 100',
                'target' => 100,
                'unit' => '%',
                'stage' => 'Pre-Concreción',
                'weight' => 15,
                'incidence' => 100,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Gestión de clientes sin cierre financiero',
                        'definition' => 'Porcentaje de seguimiento y gestión a clientes sin cierre financiero.',
                        'formula' => '(Sin_Cierre_Gestionados / Sin_Cierre_Total) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Sin_Cierre_Gestionados', 'value' => 0],
                            ['name' => 'Sin_Cierre_Total', 'value' => 1],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 999, 'qualification' => '100% gestionado', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 99.99, 'qualification' => 'Pendientes sin gestión', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            [
                'name' => 'Gestión Clientes con Subsidio',
                'description' => 'Gestión del 100% de los clientes con subsidio incorporado en el plan de pagos.',
                'formula' => '(Con_Subsidio_Gestionados / Con_Subsidio_Total) * 100',
                'target' => 100,
                'unit' => '%',
                'stage' => 'Pre-Concreción',
                'weight' => 15,
                'incidence' => 100,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Gestión de clientes con subsidio',
                        'definition' => 'Porcentaje de clientes con subsidio gestionados adecuadamente.',
                        'formula' => '(Con_Subsidio_Gestionados / Con_Subsidio_Total) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Con_Subsidio_Gestionados', 'value' => 0],
                            ['name' => 'Con_Subsidio_Total', 'value' => 1],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 999, 'qualification' => '100% con subsidio gestionado', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 99.99, 'qualification' => 'Pendientes por gestión', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            [
                'name' => 'Tiempo Promedio para Cierre Financiero',
                'description' => 'Evalúa la agilidad en la consecución del cierre financiero desde su asignación.',
                'formula' => 'Sum_Dias_Por_Cliente / Nro_Clientes',
                'target' => 15,
                'unit' => 'Días',
                'stage' => 'Pre-Concreción',
                'weight' => 15,
                'incidence' => 100,
                'lower_is_better' => true,
                'indicators' => [
                    [
                        'name' => 'Días promedio de cierre financiero',
                        'definition' => 'Promedio de días hábiles transcurridos por cliente para lograr el cierre financiero.',
                        'formula' => 'Sum_Dias_Por_Cliente / Nro_Clientes',
                        'unit' => 'Días',
                        'parameters' => [
                            ['name' => 'Sum_Dias_Por_Cliente', 'value' => 15],
                            ['name' => 'Nro_Clientes', 'value' => 1],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 0, 'max_value' => 15, 'qualification' => 'Dentro del tiempo óptimo (Máx. 15 días)', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Aceptable', 'min_value' => 15.01, 'max_value' => 20, 'qualification' => 'Rango aceptable (15 a 20 días)', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 20.01, 'max_value' => 999, 'qualification' => 'Excede el tiempo límite', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            [
                'name' => 'Puntualidad en la Actualización de Base de Datos',
                'description' => 'Mide la constancia en el registro diario de información en dashboard alterno.',
                'formula' => '(Registros_Actualizados / Registros_Totales) * 100',
                'target' => 100,
                'unit' => '%',
                'stage' => 'Pre-Concreción',
                'weight' => 15,
                'incidence' => 100,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Actualización semanal de Dashboard / Base de datos',
                        'definition' => 'Información 100% actualizada al cierre de la semana.',
                        'formula' => '(Registros_Actualizados / Registros_Totales) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Registros_Actualizados', 'value' => 100],
                            ['name' => 'Registros_Totales', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 999, 'qualification' => '100% actualizada', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 99.99, 'qualification' => 'Desactualizada al cierre de semana', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            [
                'name' => 'Contribución al Ambiente Laboral',
                'description' => 'Evaluación de colaborador (en el equipo) medida a través de evaluación 360° / feedback interno.',
                'formula' => 'Puntaje_Evaluacion_360',
                'target' => 5,
                'unit' => 'Puntos',
                'stage' => 'Pre-Concreción',
                'weight' => 20,
                'incidence' => 100,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Evaluación 360°',
                        'definition' => 'Calificación obtenida en la evaluación 360° de ambiente laboral.',
                        'formula' => 'Puntaje_Evaluacion_360',
                        'unit' => 'Puntos',
                        'parameters' => [
                            ['name' => 'Puntaje_Evaluacion_360', 'value' => 5],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 4.5, 'max_value' => 5, 'qualification' => 'Sobresaliente (4.5 - 5.0)', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Aceptable', 'min_value' => 3.5, 'max_value' => 4.49, 'qualification' => 'Satisfactorio (3.5 - 4.4)', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 3.49, 'qualification' => 'Requiere mejora', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],

            // ETAPA 2: ESCRITURACIÓN
            [
                'name' => 'Porcentaje de Entregas con Documentación',
                'description' => 'Cuantifica la calidad de la documentación solicitada a la notaría sin observaciones.',
                'formula' => '(Expedientes_Con_Observaciones / Total_Expedientes) * 100',
                'target' => 2,
                'unit' => '%',
                'stage' => 'Escrituración',
                'weight' => 15,
                'incidence' => 0,
                'lower_is_better' => true,
                'indicators' => [
                    [
                        'name' => 'Expedientes con observaciones',
                        'definition' => 'Porcentaje de expedientes de escrituración devueltos con observaciones por la notaría.',
                        'formula' => '(Expedientes_Con_Observaciones / Total_Expedientes) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Expedientes_Con_Observaciones', 'value' => 0],
                            ['name' => 'Total_Expedientes', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 0, 'max_value' => 2, 'qualification' => 'Menos del 2% de errores (Óptimo)', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Deficiente', 'min_value' => 2.01, 'max_value' => 100, 'qualification' => 'Supera el 2% de observaciones', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            [
                'name' => 'Tiempo Promedio para Aprobación de Créditos',
                'description' => 'Agilidad en la aprobación de créditos desde su radicación bancaria.',
                'formula' => 'Sum_Dias_Por_Tramite / Nro_Tramites',
                'target' => 10,
                'unit' => 'Días',
                'stage' => 'Escrituración',
                'weight' => 15,
                'incidence' => 0,
                'lower_is_better' => true,
                'indicators' => [
                    [
                        'name' => 'Días promedio aprobación crédito',
                        'definition' => 'Días hábiles promedio de respuesta del banco para aprobación de créditos.',
                        'formula' => 'Sum_Dias_Por_Tramite / Nro_Tramites',
                        'unit' => 'Días',
                        'parameters' => [
                            ['name' => 'Sum_Dias_Por_Tramite', 'value' => 7],
                            ['name' => 'Nro_Tramites', 'value' => 1],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 0, 'max_value' => 10, 'qualification' => '7 a 10 días hábiles (Óptimo)', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Deficiente', 'min_value' => 10.01, 'max_value' => 999, 'qualification' => 'Excede los 10 días hábiles', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            [
                'name' => 'Porcentaje de Paz y Salvos Obtenidos a Tiempo',
                'description' => 'Cumplimiento en la gestión oportuna de paz y salvos requeridos para escriturar.',
                'formula' => '(Paz_Y_Salvos_A_Tiempo / Total_Paz_Y_Salvos) * 100',
                'target' => 100,
                'unit' => '%',
                'stage' => 'Escrituración',
                'weight' => 15,
                'incidence' => 0,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Obtención de Paz y Salvos a tiempo',
                        'definition' => 'Porcentaje de paz y salvos obtenidos a tiempo.',
                        'formula' => '(Paz_Y_Salvos_A_Tiempo / Total_Paz_Y_Salvos) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Paz_Y_Salvos_A_Tiempo', 'value' => 100],
                            ['name' => 'Total_Paz_Y_Salvos', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 999, 'qualification' => '100% a tiempo', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 99.99, 'qualification' => 'Retraso en expedición', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            [
                'name' => 'Tiempo Promedio de Prórrogas de Escrituras',
                'description' => 'Agilidad desde el otorgamiento de prórroga hasta la firma de la escritura.',
                'formula' => 'Sum_Dias_Por_Escritura / Nro_Escrituras',
                'target' => 15,
                'unit' => 'Días',
                'stage' => 'Escrituración',
                'weight' => 15,
                'incidence' => 0,
                'lower_is_better' => true,
                'indicators' => [
                    [
                        'name' => 'Tiempo promedio de prórrogas',
                        'definition' => 'Días hábiles promedio por escritura en prórroga.',
                        'formula' => 'Sum_Dias_Por_Escritura / Nro_Escrituras',
                        'unit' => 'Días',
                        'parameters' => [
                            ['name' => 'Sum_Dias_Por_Escritura', 'value' => 10],
                            ['name' => 'Nro_Escrituras', 'value' => 1],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 0, 'max_value' => 15, 'qualification' => '10 a 15 días hábiles (Cumplido)', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Deficiente', 'min_value' => 15.01, 'max_value' => 999, 'qualification' => 'Excede los 15 días hábiles', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            [
                'name' => 'Porcentaje de Archivos Digitales Completos',
                'description' => 'Calidad de resguardo e integración de expediente digital según parámetros.',
                'formula' => '(Archivos_Completos / Total_Expedientes) * 100',
                'target' => 100,
                'unit' => '%',
                'stage' => 'Escrituración',
                'weight' => 10,
                'incidence' => 0,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Archivos digitales completos',
                        'definition' => 'Documentos cargados al 100% en carpeta digital.',
                        'formula' => '(Archivos_Completos / Total_Expedientes) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Archivos_Completos', 'value' => 100],
                            ['name' => 'Total_Expedientes', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 999, 'qualification' => '100% de expedientes completos', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 99.99, 'qualification' => 'Expedientes incompletos', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            [
                'name' => 'Puntualidad en la Entrega de Reportes',
                'description' => 'Cumplimiento oportuno en la entrega de avances de trámites de escrituración.',
                'formula' => '(Reportes_A_Tiempo / Total_Reportes) * 100',
                'target' => 100,
                'unit' => '%',
                'stage' => 'Escrituración',
                'weight' => 15,
                'incidence' => 0,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Entrega de reportes a tiempo',
                        'definition' => 'Reportes semanales/mensuales de avance entregados a tiempo.',
                        'formula' => '(Reportes_A_Tiempo / Total_Reportes) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Reportes_A_Tiempo', 'value' => 100],
                            ['name' => 'Total_Reportes', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 999, 'qualification' => '100% a tiempo', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 99.99, 'qualification' => 'Reportes fuera de tiempo', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            [
                'name' => 'Porcentaje de Tareas Asignadas Cumplidas',
                'description' => 'Disciplina en el seguimiento y cumplimiento de tareas asignadas en trámites/escrituración.',
                'formula' => '(Tareas_Cumplidas / Total_Tareas) * 100',
                'target' => 100,
                'unit' => '%',
                'stage' => 'Escrituración',
                'weight' => 15,
                'incidence' => 0,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Cumplimiento de tareas asignadas',
                        'definition' => 'Porcentaje de tareas del plan de trabajo ejecutadas.',
                        'formula' => '(Tareas_Cumplidas / Total_Tareas) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Tareas_Cumplidas', 'value' => 100],
                            ['name' => 'Total_Tareas', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 999, 'qualification' => '100% cumplido', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 99.99, 'qualification' => 'Tareas pendientes o vencidas', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
        ];

        // 5. Guardar los KPIs asociados al usuario líder
        foreach ($kpis as $kpiData) {
            $user->kpis()->updateOrCreate(
                ['name' => $kpiData['name']],
                $kpiData
            );
        }
    }
}
