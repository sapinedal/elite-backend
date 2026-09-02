<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Http\Modules\Users\Models\User;
use App\Http\Modules\Plantillas\Models\KPI;
use App\Http\Modules\Configuracion\Models\Area;
use App\Http\Modules\Configuracion\Models\Position;

class ProcesosGestionDocumentalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Asegurar el Área de Procesos y Gestión Documental
        $area = Area::firstOrCreate(
            ['name' => 'Procesos y Gestión Documental'],
            ['description' => 'Área encargada de control documental, archivo comercial y TH, soporte técnico financiero y cumplimiento normativo']
        );

        // 2. Cargos pertenecientes al área
        $positions = [
            'Líder de Procesos y Gestión Documental',
            'Analista de Control Documental y Archivo',
            'Auxiliar de Gestión Documental',
        ];

        $createdPositions = [];
        foreach ($positions as $posName) {
            $createdPositions[$posName] = Position::firstOrCreate([
                'name' => $posName,
                'area_id' => $area->id
            ]);
        }

        // 3. Usuario principal / Líder del área
        $userPayload = [
            'first_name' => 'LÍDER',
            'last_name' => 'PROCESOS Y GESTIÓN DOCUMENTAL',
            'name' => 'LÍDER PROCESOS Y GESTIÓN DOCUMENTAL',
            'document' => '1000000003',
            'position_id' => $createdPositions['Líder de Procesos y Gestión Documental']->id,
            'area_id' => $area->id,
            'email' => 'gestion.documental@elite.com',
            'password' => bcrypt('Elite123'),
        ];

        $user = User::updateOrCreate(['email' => $userPayload['email']], $userPayload);

        // 4. Definición de los KPIs extraídos del formato de Evaluación de Desempeño
        $kpis = [
            // 1. Archivo Comercial (Físico y Digital)
            [
                'name' => 'Nivel de Cumplimiento en Archivo Documental Comercial (Físico y Digital)',
                'description' => 'Mide el grado de cumplimiento en el archivo correcto, completo y oportuno de la documentación comercial dentro de las 24 horas posteriores a su generación o firma.',
                'formula' => '(Documentos_Archivados_Correctamente / Total_Documentos_Generados) * 100',
                'target' => 100,
                'unit' => '%',
                'stage' => 'Control Documental Comercial',
                'weight' => 10,
                'incidence' => 20,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Archivo Documental Comercial',
                        'definition' => 'Documentación comercial archivada dentro de las 24 horas.',
                        'formula' => '(Documentos_Archivados_Correctamente / Total_Documentos_Generados) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Documentos_Archivados_Correctamente', 'value' => 100],
                            ['name' => 'Total_Documentos_Generados', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 999, 'qualification' => '100% archivado correctamente', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Aceptable', 'min_value' => 85, 'max_value' => 99.99, 'qualification' => 'Buen avance con rezagos menores', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 84.99, 'qualification' => 'Incumplimiento en archivo comercial', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],

            // 2. Carga a Sistemas
            [
                'name' => 'Nivel de Oportunidad en la Carga de Documentación a Sistemas',
                'description' => 'Evalúa el grado de oportunidad y avance en la carga y actualización de la documentación comercial en los sistemas de la compañía según cronogramas por torre.',
                'formula' => '(Documentos_Cargados_En_Periodo / Total_Documentos_Pendientes) * 100',
                'target' => 100,
                'unit' => '%',
                'stage' => 'Control Documental Comercial',
                'weight' => 10,
                'incidence' => 20,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Carga de documentación a sistemas',
                        'definition' => 'Avance en carga de documentos según cronograma por torre.',
                        'formula' => '(Documentos_Cargados_En_Periodo / Total_Documentos_Pendientes) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Documentos_Cargados_En_Periodo', 'value' => 100],
                            ['name' => 'Total_Documentos_Pendientes', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 999, 'qualification' => '100% al día sin rezagos', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 99.99, 'qualification' => 'Rezagos en carga a sistemas', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],

            // 3. Vinculación TH
            [
                'name' => 'Nivel de Cumplimiento Documental en Vinculación',
                'description' => 'Mide el porcentaje de colaboradores que cuentan con su expediente completo y correctamente archivado dentro de los 5 días hábiles posteriores a la firma.',
                'formula' => '(Expedientes_Completos / Total_Colaboradores_Vinculados) * 100',
                'target' => 100,
                'unit' => '%',
                'stage' => 'Control Documental Talento Humano',
                'weight' => 10,
                'incidence' => 20,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Expedientes completos en vinculación',
                        'definition' => 'Expedientes de personal completados dentro de los 5 días hábiles post-contrato.',
                        'formula' => '(Expedientes_Completos / Total_Colaboradores_Vinculados) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Expedientes_Completos', 'value' => 100],
                            ['name' => 'Total_Colaboradores_Vinculados', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 999, 'qualification' => '100% completados en tiempo', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Aceptable', 'min_value' => 85, 'max_value' => 99.99, 'qualification' => 'Desempeño aceptable', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 84.99, 'qualification' => 'Documentación incompleta en vinculación', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],

            // 4. Desvinculación TH
            [
                'name' => 'Nivel de Cumplimiento del Proceso de Desvinculación',
                'description' => 'Garantizar que ante renuncia o terminación laboral, el proceso de desvinculación se ejecute de manera completa, oportuna y conforme a la normatividad.',
                'formula' => '(Procesos_Desvinculacion_Completos / Total_Desvinculaciones) * 100',
                'target' => 100,
                'unit' => '%',
                'stage' => 'Control Documental Talento Humano',
                'weight' => 10,
                'incidence' => 20,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Cumplimiento del proceso de desvinculación',
                        'definition' => 'Documentos obligatorios de desvinculación generados y archivados (100%).',
                        'formula' => '(Procesos_Desvinculacion_Completos / Total_Desvinculaciones) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Procesos_Desvinculacion_Completos', 'value' => 1],
                            ['name' => 'Total_Desvinculaciones', 'value' => 1],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 999, 'qualification' => '100% cumplido conforme a ley', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 99.99, 'qualification' => 'Documentos de retiro faltantes', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],

            // 5. Bases de Datos Trámites
            [
                'name' => 'Nivel de Puntualidad en la Actualización de Bases de Datos de Trámites',
                'description' => 'Mide la constancia y puntualidad en el registro diario de información de trámites y escrituración en el dashboard alterno.',
                'formula' => '(Registros_Actualizados_Oportunamente / Total_Registros) * 100',
                'target' => 100,
                'unit' => '%',
                'stage' => 'Control Documental Trámites y Escrituración',
                'weight' => 20,
                'incidence' => 20,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Actualización diaria de bases de datos trámites',
                        'definition' => 'Registros de trámites y escrituración 100% al día en dashboard.',
                        'formula' => '(Registros_Actualizados_Oportunamente / Total_Registros) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Registros_Actualizados_Oportunamente', 'value' => 100],
                            ['name' => 'Total_Registros', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 999, 'qualification' => '100% información conciliada y al día', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 99.99, 'qualification' => 'Inconsistencias o rezagos en registros', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],

            // 6. Soportes de Pago
            [
                'name' => 'Nivel de Oportunidad en el Envío de Soportes de Pago',
                'description' => 'Garantizar el envío oportuno y completo de soportes de pago a las áreas correspondientes dentro de las 24 horas siguientes a la ejecución del pago.',
                'formula' => '(Soportes_Enviados_A_Tiempo / Total_Pagos_Realizados) * 100',
                'target' => 100,
                'unit' => '%',
                'stage' => 'Apoyo Presupuestal y Control Financiero - Área Técnica',
                'weight' => 10,
                'incidence' => 20,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Envío oportuno de soportes de pago',
                        'definition' => 'Soportes de pago de área técnica enviados dentro de 24 horas.',
                        'formula' => '(Soportes_Enviados_A_Tiempo / Total_Pagos_Realizados) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Soportes_Enviados_A_Tiempo', 'value' => 100],
                            ['name' => 'Total_Pagos_Realizados', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 999, 'qualification' => '100% de soportes enviados a tiempo', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 99.99, 'qualification' => 'Retrasos en envío de soportes', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],

            // 7. Matrices de Pagos
            [
                'name' => 'Nivel de Puntualidad en la Alimentación de Matrices de Pagos',
                'description' => 'Garantizar la actualización oportuna y completa de las matrices de pago del área técnica, asegurando cumplimiento normativo y trazabilidad financiera.',
                'formula' => '(Total_Pagos_Asentados / Total_Pagos_Realizados) * 100',
                'target' => 100,
                'unit' => '%',
                'stage' => 'Apoyo Presupuestal y Control Financiero - Área Técnica',
                'weight' => 10,
                'incidence' => 20,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Alimentación de matrices de pagos',
                        'definition' => '100% de los pagos ejecutados asentados en la matriz.',
                        'formula' => '(Total_Pagos_Asentados / Total_Pagos_Realizados) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Total_Pagos_Asentados', 'value' => 100],
                            ['name' => 'Total_Pagos_Realizados', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 999, 'qualification' => '100% matrices al día', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 99.99, 'qualification' => 'Matrices incompletas', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],

            // 8. Requerimientos / Radicaciones
            [
                'name' => 'Tiempo Promedio de Respuesta a Requerimientos de Entidades, Autoridades y Radicaciones',
                'description' => 'Mide la oportunidad en la atención y respuesta a requerimientos de entidades externas, radicaciones y trámites de licenciamiento.',
                'formula' => 'Sum_Dias_Respuesta / Total_Requerimientos_Atendidos',
                'target' => 1,
                'unit' => 'Días',
                'stage' => 'Cumplimiento Normativo y Soporte a la Operación',
                'weight' => 10,
                'incidence' => 20,
                'lower_is_better' => true,
                'indicators' => [
                    [
                        'name' => 'Tiempo de respuesta a requerimientos',
                        'definition' => 'Días promedio de respuesta a radicaciones/licenciamiento.',
                        'formula' => 'Sum_Dias_Respuesta / Total_Requerimientos_Atendidos',
                        'unit' => 'Días',
                        'parameters' => [
                            ['name' => 'Sum_Dias_Respuesta', 'value' => 1],
                            ['name' => 'Total_Requerimientos_Atendidos', 'value' => 1],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 0, 'max_value' => 2.5, 'qualification' => 'Respuesta oportuna (<= 2.5 días hábiles)', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Deficiente', 'min_value' => 2.51, 'max_value' => 999, 'qualification' => 'Excede tiempo límite de respuesta', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],

            // 9. Seguimiento Semanal de Tareas
            [
                'name' => 'Porcentaje de Tareas Asignadas con Seguimiento Semanal (Propias y Externas)',
                'description' => 'Garantizar que la totalidad de las tareas asignadas cuenten con seguimiento semanal estructurado, asegurando control de avances y cumplimiento.',
                'formula' => '(Tareas_Con_Seguimiento_Semanal / Total_Tareas_Asignadas) * 100',
                'target' => 100,
                'unit' => '%',
                'stage' => 'Cumplimiento Normativo y Soporte a la Operación',
                'weight' => 10,
                'incidence' => 20,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Tareas asignadas con seguimiento semanal',
                        'definition' => '100% de tareas con seguimiento y registro en plan semanal.',
                        'formula' => '(Tareas_Con_Seguimiento_Semanal / Total_Tareas_Asignadas) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Tareas_Con_Seguimiento_Semanal', 'value' => 100],
                            ['name' => 'Total_Tareas_Asignadas', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 999, 'qualification' => '100% tareas con seguimiento registrado', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 99.99, 'qualification' => 'Tareas sin seguimiento registrado', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],

            // 10. Clima y Ambiente Laboral
            [
                'name' => 'Índice de Colaboración, Actitud y Clima Laboral',
                'description' => 'Evaluar la contribución del colaborador al ambiente laboral a través de su actitud, comportamiento, trabajo en equipo y vocación de servicio.',
                'formula' => 'Puntaje_Evaluacion_360',
                'target' => 5,
                'unit' => 'Puntos',
                'stage' => 'Contribución al Ambiente Laboral',
                'weight' => 0,
                'incidence' => 0,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Evaluación 360° de ambiente laboral',
                        'definition' => 'Calificación obtenida en evaluación 360° (Escala de 1 a 5).',
                        'formula' => 'Puntaje_Evaluacion_360',
                        'unit' => 'Puntos',
                        'parameters' => [
                            ['name' => 'Puntaje_Evaluacion_360', 'value' => 4.82],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 4.5, 'max_value' => 5, 'qualification' => 'Sobresaliente / Excepcional (4.5 - 5.0)', 'color' => 'excellent', 'score' => 100],
                            ['level' => 'Aceptable', 'min_value' => 3.5, 'max_value' => 4.49, 'qualification' => 'Aceptable (3.5 - 4.4)', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Bajo', 'min_value' => 0, 'max_value' => 3.49, 'qualification' => 'Requiere mejora (< 3.5)', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
        ];

        // 5. Guardar los KPIs asociados al usuario líder de Procesos y Gestión Documental
        foreach ($kpis as $kpiData) {
            $user->kpis()->updateOrCreate(
                ['name' => $kpiData['name']],
                $kpiData
            );
        }
    }
}
