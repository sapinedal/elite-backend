<?php

namespace Database\Seeders;

use App\Http\Modules\Configuracion\Models\Area;
use App\Http\Modules\Configuracion\Models\Position;
use App\Http\Modules\Users\Models\User;
use App\Http\Modules\Plantillas\Models\KPI;
use Illuminate\Database\Seeder;

class TransversalAreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Asegurar el Área Transversal / Gestión Documental
        $area = Area::whereRaw('LOWER(name) IN (?, ?, ?, ?)', ['transversal', 'procesos y gestión documental', 'gestión documental', 'contratación y gestión documental'])->first()
            ?? Area::firstOrCreate(
                ['name' => 'Transversal'],
                ['description' => 'Área transversal encargada de contratación, gestión documental, soporte financiero a obra y cumplimiento normativo']
            );

        // 2. Cargos pertenecientes al área
        $positions = [
            'Líder de contratación y gestión documental',
            'Líder de Procesos y Gestión Documental',
            'Analista de Control Documental y Archivo',
            'Auxiliar de Gestión Documental',
        ];

        $createdPositions = [];
        foreach ($positions as $posName) {
            $createdPositions[$posName] = Position::where('area_id', $area->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($posName)])
                ->first()
                ?? Position::whereRaw('LOWER(name) = ?', [mb_strtolower($posName)])->first()
                ?? Position::firstOrCreate([
                    'name' => $posName,
                    'area_id' => $area->id
                ]);
        }

        // =========================================================================
        // USUARIOS DEL ÁREA TRANSVERSAL
        // =========================================================================
        $colaboradores = [
            [
                'first_name' => 'SANTIAGO',
                'last_name' => 'SANCHEZ VILLA',
                'name' => 'SANTIAGO SANCHEZ VILLA',
                'document' => '1152470931',
                'position_name' => 'Líder de contratación y gestión documental',
                'email' => 'ssanchez@cybproject.com',
            ],
        ];

        $instantiatedUsers = [];
        foreach ($colaboradores as $colab) {
            $user = User::withTrashed()
                ->where(function ($q) use ($colab) {
                    if (!empty($colab['document'])) {
                        $q->where('document', $colab['document']);
                    }
                    $q->orWhereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower(trim($colab['email']))]);
                })
                ->first();

            $position = $createdPositions[$colab['position_name']] ?? $createdPositions['Líder de contratación y gestión documental'];

            if (!$user) {
                $user = User::create([
                    'first_name' => mb_strtoupper($colab['first_name'], 'UTF-8'),
                    'last_name' => mb_strtoupper($colab['last_name'], 'UTF-8'),
                    'name' => mb_strtoupper($colab['name'], 'UTF-8'),
                    'document' => $colab['document'],
                    'position_id' => $position->id,
                    'area_id' => $area->id,
                    'email' => trim($colab['email']),
                    'password' => bcrypt('Elite123'),
                ]);
            } else {
                if ($user->trashed()) {
                    $user->restore();
                }
                $user->update([
                    'first_name' => mb_strtoupper($colab['first_name'], 'UTF-8'),
                    'last_name' => mb_strtoupper($colab['last_name'], 'UTF-8'),
                    'name' => mb_strtoupper($colab['name'], 'UTF-8'),
                    'document' => $user->document ?: $colab['document'],
                    'position_id' => $position->id,
                    'area_id' => $area->id,
                    'email' => $user->email ?: trim($colab['email']),
                ]);
            }

            $instantiatedUsers[$colab['document']] = $user;
        }

        // =========================================================================
        // KPIS: LÍDER DE CONTRATACIÓN Y GESTIÓN DOCUMENTAL (CC: 1152470931 - Santiago Sanchez Villa)
        // =========================================================================
        $userSantiago = $instantiatedUsers['1152470931'] ?? null;
        if ($userSantiago) {
            $kpisSantiago = [
                // BLOQUE A: Control Documental y Archivo Comercial (20%)
                [
                    'name' => 'A. Control Documental y Archivo Comercial',
                    'description' => 'Mide el nivel de cumplimiento en el archivo correcto y oportuno de la documentación comercial (carpetas, promesas, otrosíes dentro de las 24 horas) y la oportunidad en la carga de documentación a los sistemas según cronograma por torre.',
                    'formula' => 'Promedio de cumplimiento en archivo documental comercial y oportunidad en carga a sistemas',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'A. Control Documental y Archivo Comercial',
                    'weight' => 20,
                    'incidence' => 20,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Nivel de Cumplimiento en Archivo Documental Comercial (Físico y Digital)',
                            'definition' => 'Porcentaje de documentos comerciales (carpetas clientes, otrosíes, promesas, actas de entrega, parqueaderos y Croma) archivados correctamente dentro de las 24 horas posteriores a su firma o generación. Meta: 100%.',
                            'formula' => '(Documentos_Archivados_Correctamente / Total_Documentos_Generados) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Documentos_Archivados_Correctamente', 'value' => 0],
                                ['name' => 'Total_Documentos_Generados', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 95, 'max_value' => 1000, 'qualification' => '≥ 95% — Archivo comercial completo, oportuno y trazable en físico y digital', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 85, 'max_value' => 94.99, 'qualification' => '85% – 94.99% — Cumplimiento adecuado con rezagos menores en proceso de depuración', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 84.99, 'qualification' => '< 85% — Rezagos críticos o carpetas sin validar', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Nivel de Oportunidad en la Carga de Documentación a Sistemas',
                            'definition' => 'Grado de avance y puntualidad en la digitalización y carga de documentación a los sistemas conforme a cronogramas (T1 en sep, T2 en oct, otrosí al día). Meta: 100%.',
                            'formula' => '(Documentos_Cargados_En_Periodo / Total_Documentos_Pendientes_Inicio) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Documentos_Cargados_En_Periodo', 'value' => 0],
                                ['name' => 'Total_Documentos_Pendientes_Inicio', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 95, 'max_value' => 1000, 'qualification' => '≥ 95% — Carga al día sin rezagos conforme a cronograma', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 80, 'max_value' => 94.99, 'qualification' => '80% – 94.99% — Carga progresiva con frentes priorizados', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — Retrasos en actualización de sistemas', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],

                // BLOQUE B: Control Documental y Archivo Talento Humano (20%)
                [
                    'name' => 'B. Control Documental y Archivo Talento Humano',
                    'description' => 'Mide el porcentaje de expedientes laborales completos en procesos de vinculación (≤ 3 días post-firma) y la completitud del 100% en los documentos obligatorios de desvinculación laboral.',
                    'formula' => 'Promedio de cumplimiento documental en vinculación y procesos de desvinculación laboral',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'B. Control Documental y Archivo Talento Humano',
                    'weight' => 20,
                    'incidence' => 20,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Nivel de Cumplimiento Documental en Vinculación',
                            'definition' => 'Porcentaje de colaboradores activos con expediente completo (contrato, afiliaciones EPS/ARL/Caja, exámenes médicos, inducción, etc.) archivado dentro de los 3 días hábiles posteriores a la firma. Meta: 100%.',
                            'formula' => '(Expedientes_Completos_Vinculacion / Total_Colaboradores_Vinculados) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Expedientes_Completos_Vinculacion', 'value' => 0],
                                ['name' => 'Total_Colaboradores_Vinculados', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 90, 'max_value' => 1000, 'qualification' => '≥ 90% — Expedientes completos con soporte legal y normativo integral', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 80, 'max_value' => 89.99, 'qualification' => '80% – 89.99% — Expedientes con soportes menores pendientes en normalización', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — Riesgo laboral por expedientes incompletos', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Nivel de Cumplimiento del Proceso de Desvinculación Laboral',
                            'definition' => 'Porcentaje de procesos de retiro con documentación completa (carta renuncia/despido, liquidación, cert. laboral, paz y salvo, entrega de cargo, examen egreso) cerrados dentro de 3 días hábiles. Meta: 100%.',
                            'formula' => '(Procesos_Desvinculacion_Completos / Total_Desvinculaciones_Periodo) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Procesos_Desvinculacion_Completos', 'value' => 0],
                                ['name' => 'Total_Desvinculaciones_Periodo', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Desvinculación cerrada y blindada legalmente en plazo', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 85, 'max_value' => 99.99, 'qualification' => '85% – 99.99% — Trámite oportuno con examen médico de egreso en trámite', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 84.99, 'qualification' => '< 85% — Documentos de retiro pendientes fuera de término legal', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],

                // BLOQUE C: Control Documental y Archivo Trámites y Escrituración (20%)
                [
                    'name' => 'C. Control Documental y Archivo Trámites y Escrituración',
                    'description' => 'Mide la constancia, puntualidad y conciliación diaria de los registros de trámites y escrituración en el dashboard alterno (100% conciliación de bases de datos por torre).',
                    'formula' => '(Registros_Actualizados_Oportunamente / Total_Registros_Periodo) * 100',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'C. Control Documental y Archivo Trámites y Escrituración',
                    'weight' => 20,
                    'incidence' => 20,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Nivel de Puntualidad en la Actualización de Bases de Datos de Trámites',
                            'definition' => 'Actualización diaria y conciliación sin discrepancias entre el Dashboard de Inver, Trámites, Fiduciaria y Multifox para todas las torres. Meta: 100%.',
                            'formula' => '(Registros_Actualizados_Oportunamente / Total_Registros_Periodo) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Registros_Actualizados_Oportunamente', 'value' => 0],
                                ['name' => 'Total_Registros_Periodo', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Conciliación total de datos y actualización diaria impecable', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Actualización adecuada con ajustes menores en conciliación', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Descuadres o rezagos en bases de datos de trámites', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],

                // BLOQUE D: Apoyo Presupuestal y Control Financiero - Área Técnica (20%)
                [
                    'name' => 'D. Apoyo Presupuestal y Control Financiero - Área Técnica',
                    'description' => 'Mide la oportunidad en el envío de soportes de pago a las áreas correspondientes dentro de las 24 horas posteriores al pago (100%) y la puntualidad en la alimentación de matrices de pago de obra (100%).',
                    'formula' => 'Promedio de oportunidad en envío de soportes y alimentación de matrices de pago',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'D. Apoyo Presupuestal y Control Financiero - Área Técnica',
                    'weight' => 20,
                    'incidence' => 20,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Nivel de Oportunidad en el Envío de Soportes de Pago',
                            'definition' => 'Porcentaje de soportes de pago del área técnica remitidos a las áreas contable y de tesorería dentro de las 24 horas siguientes a la ejecución del pago. Meta: 100%.',
                            'formula' => '(Soportes_Pago_Enviados_ATiempo / Total_Pagos_Realizados) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Soportes_Pago_Enviados_ATiempo', 'value' => 0],
                                ['name' => 'Total_Pagos_Realizados', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Totalidad de soportes enviados en ≤ 24 horas', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Envío regular con demoras puntuales justificadas', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Retrasos que afectan la trazabilidad financiera', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Nivel de Puntualidad en la Alimentación de Matrices de Pagos',
                            'definition' => 'Porcentaje de pagos ejecutados en el área técnica debidamente asentados en las matrices de control presupuestal de obra. Meta: 100%.',
                            'formula' => '(Total_Pagos_Asentados_Matrices / Total_Pagos_Realizados) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Total_Pagos_Asentados_Matrices', 'value' => 0],
                                ['name' => 'Total_Pagos_Realizados', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Matrices de pagos de obra 100% alimentadas y al día', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Registro oportuno con rezago leve', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Desactualización en matrices de control presupuestal', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],

                // BLOQUE E: Cumplimiento Normativo, Soporte Operativo y Clima Laboral (20%)
                [
                    'name' => 'E. Cumplimiento Normativo, Soporte Operativo y Clima Laboral',
                    'description' => 'Mide la celeridad en la atención a requerimientos de entidades externas y radicaciones (≤ 24h / 15 días), seguimiento semanal documentado de tareas (100%) e índice de clima laboral 360° (≥ 4.5 pts).',
                    'formula' => 'Promedio de tiempo de respuesta a requerimientos, seguimiento semanal de tareas y evaluación 360',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'E. Cumplimiento Normativo, Soporte Operativo y Clima Laboral',
                    'weight' => 20,
                    'incidence' => 20,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Tiempo Promedio de Respuesta a Requerimientos de Entidades y Radicaciones',
                            'definition' => 'Tiempo promedio en días para atender radicaciones, trámites de licenciamiento, pólizas, siniestros y requerimientos de entidades. Meta: ≤ 2.0 días hábiles.',
                            'formula' => 'Tiempo_Promedio_Respuesta_Dias',
                            'unit' => 'días hábiles',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Tiempo_Promedio_Respuesta_Dias', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 2.0, 'qualification' => '≤ 2.0 días hábiles — Respuesta ágil, oportuna y conforme a normatividad', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 2.01, 'max_value' => 3.0, 'qualification' => '2.1 – 3.0 días hábiles — Trámite en curso dentro de márgenes operativos', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 3.01, 'max_value' => 1000, 'qualification' => '> 3.0 días hábiles — Retrasos que comprometen trámites normativos', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Porcentaje de Tareas Asignadas con Seguimiento Semanal',
                            'definition' => 'Porcentaje de tareas asignadas (propias y externas) con seguimiento semanal registrado en el dashboard. Meta: 100%.',
                            'formula' => '(Tareas_Con_Seguimiento_Semanal / Total_Tareas_Asignadas) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Tareas_Con_Seguimiento_Semanal', 'value' => 0],
                                ['name' => 'Total_Tareas_Asignadas', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '≥ 100% — Monitoreo proactivo y trazabilidad semanal completa', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 70, 'max_value' => 99.99, 'qualification' => '70% – 99.99% — Seguimiento adecuado con actividades pendientes de control', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 69.99, 'qualification' => '< 70% — Falta de registro formal de seguimiento semanal', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Índice de Colaboración, Actitud y Clima Laboral (Evaluación 360°)',
                            'definition' => 'Evaluación de trabajo en equipo, actitud, vocación de servicio, profesionalismo y liderazgo (escala de 1 a 5). Meta: ≥ 4.5 pts.',
                            'formula' => 'Puntaje_Evaluacion_360',
                            'unit' => 'pts',
                            'parameters' => [
                                ['name' => 'Puntaje_Evaluacion_360', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Sobresaliente (Verde)', 'min_value' => 4.5, 'max_value' => 5.0, 'qualification' => '≥ 4.5 pts — Desempeño excepcional, referente positivo y gran vocación de servicio', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 3.5, 'max_value' => 4.49, 'qualification' => '3.5 – 4.49 pts — Desempeño sólido con oportunidades de mayor liderazgo', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 3.49, 'qualification' => '< 3.5 pts — Oportunidades en comunicación y trabajo en equipo', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],
            ];

            foreach ($kpisSantiago as $kpiData) {
                $userSantiago->kpis()->updateOrCreate(
                    ['name' => $kpiData['name']],
                    $kpiData
                );
            }
        }
    }
}
