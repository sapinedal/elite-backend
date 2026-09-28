<?php

namespace Database\Seeders;

use App\Http\Modules\Configuracion\Models\Area;
use App\Http\Modules\Configuracion\Models\Position;
use App\Http\Modules\Users\Models\User;
use App\Http\Modules\Plantillas\Models\KPI;
use Illuminate\Database\Seeder;

class JuridicaAreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Asegurar el Área Jurídica sin duplicar si ya existe
        $juridicaArea = Area::whereRaw('LOWER(name) IN (?, ?, ?)', ['jurídica', 'juridica', 'área jurídica'])->first()
            ?? Area::firstOrCreate(
                ['name' => 'Jurídica'],
                ['description' => 'Área encargada de la asesoría legal, cumplimiento normativo, gestión contractual y societaria']
            );

        // 2. Cargos pertenecientes al Área Jurídica
        $positions = [
            'Abogada Junior',
            'Director Jurídico',
        ];

        $createdPositions = [];
        foreach ($positions as $posName) {
            $createdPositions[$posName] = Position::where('area_id', $juridicaArea->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($posName)])
                ->first()
                ?? Position::whereRaw('LOWER(name) = ?', [mb_strtolower($posName)])->first()
                ?? Position::firstOrCreate([
                    'name' => $posName,
                    'area_id' => $juridicaArea->id
                ]);
        }

        // =========================================================================
        // USUARIOS DEL ÁREA JURÍDICA
        // =========================================================================
        $colaboradores = [
            [
                'first_name' => 'GINNA MARCELA',
                'last_name' => 'QUINTANA LEON',
                'name' => 'GINNA MARCELA QUINTANA LEON',
                'document' => '1065915898',
                'position_name' => 'Abogada Junior',
                'email' => 'juridica@inverconstruccion.com',
            ],
        ];

        $instantiatedUsers = [];
        foreach ($colaboradores as $colab) {
            $user = User::where('document', $colab['document'])
                ->orWhereRaw('LOWER(email) = ?', [mb_strtolower($colab['email'])])
                ->first();

            $position = $createdPositions[$colab['position_name']] ?? $createdPositions['Abogada Junior'];

            if (!$user) {
                $user = User::create([
                    'first_name' => mb_strtoupper($colab['first_name'], 'UTF-8'),
                    'last_name' => mb_strtoupper($colab['last_name'], 'UTF-8'),
                    'name' => mb_strtoupper($colab['name'], 'UTF-8'),
                    'document' => $colab['document'],
                    'position_id' => $position->id,
                    'area_id' => $juridicaArea->id,
                    'email' => $colab['email'],
                    'password' => bcrypt('Elite123'),
                ]);
            } else {
                $user->update([
                    'first_name' => mb_strtoupper($colab['first_name'], 'UTF-8'),
                    'last_name' => mb_strtoupper($colab['last_name'], 'UTF-8'),
                    'name' => mb_strtoupper($colab['name'], 'UTF-8'),
                    'document' => $user->document ?: $colab['document'],
                    'position_id' => $position->id,
                    'area_id' => $juridicaArea->id,
                    'email' => $user->email ?: $colab['email'],
                ]);
            }

            $instantiatedUsers[$colab['document']] = $user;
        }

        // =========================================================================
        // KPIS: ABOGADA JUNIOR (CC: 1065915898 - Ginna Marcela Quintana Leon)
        // =========================================================================
        $userMarcela = $instantiatedUsers['1065915898'] ?? null;
        if ($userMarcela) {
            $kpisMarcela = [
                // BLOQUE A: Cumplimiento Normativo y Asesoría Legal (25%)
                [
                    'name' => 'A. Cumplimiento Normativo y Asesoría Legal',
                    'description' => 'Mide la oportunidad en la respuesta a consultas internas (≤ 2 días hábiles), atención de requerimientos legales a tiempo (100%), cero hallazgos normativos y reporte preventivo de vencimientos de licencias y pólizas (100%).',
                    'formula' => 'Promedio de respuestas a consultas, requerimientos legales, cero sanciones y alertas de vencimientos',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'A. Cumplimiento Normativo y Asesoría Legal',
                    'weight' => 25,
                    'incidence' => 25,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Tiempo Promedio de Respuesta a Consultas Internas',
                            'definition' => 'Tiempo promedio en días hábiles para atender consultas legales interáreas del día a día por fuera del dashboard. Meta: ≤ 2 días hábiles (90% a tiempo).',
                            'formula' => 'Tiempo_Promedio_Respuesta_Consultas_Dias',
                            'unit' => 'días hábiles',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Tiempo_Promedio_Respuesta_Consultas_Dias', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 2.0, 'qualification' => '≤ 2 días hábiles (≥ 90% a tiempo) — Respuesta ágil y oportuna', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Riesgo Moderado (Amarillo)', 'min_value' => 2.01, 'max_value' => 3.0, 'qualification' => '2.1 – 3 días hábiles (70% – 89%) — Cumplimiento parcial con riesgo', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 3.01, 'max_value' => 1000, 'qualification' => '> 3 días hábiles (< 70%) — No cumple con tiempos de respuesta', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Porcentaje de Requerimientos Legales Atendidos Dentro del Plazo',
                            'definition' => 'Porcentaje de requerimientos y tareas legales atendidos dentro de los plazos comprometidos en el cuadro de control. Meta: 100%.',
                            'formula' => '(Requerimientos_Legales_Atendidos_ATiempo / Total_Requerimientos_Legales) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Requerimientos_Legales_Atendidos_ATiempo', 'value' => 0],
                                ['name' => 'Total_Requerimientos_Legales', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Totalidad de requerimientos legales atendidos a tiempo', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Cumplimiento adecuado con rezagos mínimos', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Incumplimiento en atención de compromisos legales', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Número de Hallazgos/Sanciones por Incumplimiento Normativo',
                            'definition' => 'Control de procesos legales y regulatorios asegurando cero sanciones, multas o glosas normativas. Meta: 0.',
                            'formula' => 'Numero_Hallazgos_Sanciones_Normativas',
                            'unit' => 'hallazgos',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Numero_Hallazgos_Sanciones_Normativas', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 0.0, 'qualification' => '0 hallazgos — Control legal integral y ausencia de sanciones', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 1.0, 'max_value' => 1.0, 'qualification' => '1 hallazgo leve — Riesgo bajo y corregible', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 1.01, 'max_value' => 1000, 'qualification' => '≥ 2 hallazgos o 1 sanción — Riesgo legal alto', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Porcentaje de Vencimientos Reportados con Anticipación',
                            'definition' => 'Seguimiento y reporte preventivo de vencimientos de licencias urbanísticas/construcción y pólizas de obra con al menos 30 días de anticipación (alertas a 80 días). Meta: 100%.',
                            'formula' => '(Vencimientos_Reportados_Con_Anticipacion / Total_Vencimientos_Programados) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Vencimientos_Reportados_Con_Anticipacion', 'value' => 0],
                                ['name' => 'Total_Vencimientos_Programados', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Todas las licencias y pólizas con alerta oportuna activa', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Riesgo Moderado (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Falta alguna alerta puntual', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Riesgo alto de vencimiento sin alerta previa', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],

                // BLOQUE B: Gestión Contractual y Societaria (40%)
                [
                    'name' => 'B. Gestión Contractual y Societaria',
                    'description' => 'Mide la celeridad en la elaboración y coordinación de promesas y otrosíes (≤ 5 días hábiles), atención oportuna de derechos de petición dentro del término legal (15 días) y gestión de cambios societarios.',
                    'formula' => 'Promedio de elaboración de contratos, derechos de petición y trámites societarios',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'B. Gestión Contractual y Societaria',
                    'weight' => 40,
                    'incidence' => 40,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Tiempo Promedio de Elaboración/Coordinación de Contratos',
                            'definition' => 'Porcentaje de contratos de promesa, otrosíes y minutas elaborados dentro del plazo objetivo de ≤ 5 días hábiles. Meta: ≥ 90%.',
                            'formula' => '(Contratos_Otrosies_Elaborados_ATiempo / Total_Contratos_Otrosies_Solicitados) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Contratos_Otrosies_Elaborados_ATiempo', 'value' => 0],
                                ['name' => 'Total_Contratos_Otrosies_Solicitados', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 90, 'max_value' => 1000, 'qualification' => '≥ 90% en ≤ 5 días hábiles — Elaboración ágil y controlada', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 70, 'max_value' => 89.99, 'qualification' => '70% – 89.99% — Cumplimiento parcial con observaciones', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 69.99, 'qualification' => '< 70% — Retrasos críticos en elaboración contractual', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Porcentaje de Respuesta a Derechos de Petición y Reclamaciones a Tiempo',
                            'definition' => 'Respuestas a derechos de petición de clientes, entes de control y reclamaciones emitidas dentro del término legal (15 días hábiles). Meta: 100%.',
                            'formula' => '(Derechos_Peticion_Respondidos_ATiempo / Total_Derechos_Peticion_Recibidos) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Derechos_Peticion_Respondidos_ATiempo', 'value' => 0],
                                ['name' => 'Total_Derechos_Peticion_Recibidos', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Todas las respuestas emitidas dentro del término legal', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Trámite oportuno con prórroga justificada', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Extemporaneidad o riesgo de tutelas/sanciones', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Tiempo Promedio de Gestión de Cambios Societarios',
                            'definition' => 'Tiempo promedio desde la aprobación interna hasta la formalización y registro de reformas o cambios societarios. Meta: ≤ 20 días hábiles.',
                            'formula' => 'Tiempo_Promedio_Cambios_Societarios_Dias',
                            'unit' => 'días hábiles',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Tiempo_Promedio_Cambios_Societarios_Dias', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 20.0, 'qualification' => '≤ 20 días hábiles — Gestión societaria oportuna y formalizada', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 20.01, 'max_value' => 30.0, 'qualification' => '21 – 30 días hábiles — Trámite en curso ante entidades', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 30.01, 'max_value' => 1000, 'qualification' => '> 30 días hábiles — Retrasos que afectan estructura corporativa', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],

                // BLOQUE C: Control Documental y Monitoreo (35%)
                [
                    'name' => 'C. Control Documental y Monitoreo',
                    'description' => 'Mide el cumplimiento de tareas del área jurídica en el Dashboard (≥ 80%), trazabilidad y seguimiento semanal en Dailies (100%), completitud y soporte de expedientes en Drive (≥ 90%), clima laboral (360°) y cero hallazgos en auditorías.',
                    'formula' => 'Promedio de tareas dashboard, seguimiento weekly, completitud documental, evaluación 360 y auditorías',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'C. Control Documental y Monitoreo',
                    'weight' => 35,
                    'incidence' => 35,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Cumplimiento de Tareas en el Dashboard',
                            'definition' => 'Porcentaje de tareas jurídicas finalizadas y registradas a tiempo en el dashboard semanal de seguimiento. Meta: ≥ 80%.',
                            'formula' => '(Tareas_Dashboard_Completadas / Total_Tareas_Dashboard) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Tareas_Dashboard_Completadas', 'value' => 0],
                                ['name' => 'Total_Tareas_Dashboard', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 80, 'max_value' => 1000, 'qualification' => '≥ 80% — Disciplina en dashboard y ejecución estable', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 70, 'max_value' => 79.99, 'qualification' => '70% – 79.99% — Cumplimiento parcial con tareas en curso', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 69.99, 'qualification' => '< 70% — Tareas vencidas o rezagos en ejecución', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Porcentaje de Tareas Jurídicas con Seguimiento Semanal',
                            'definition' => 'Registro de actualización y trazabilidad semanal en las columnas de Daily del dashboard de tareas. Meta: 100%.',
                            'formula' => '(Tareas_Con_Seguimiento_Semanal / Total_Tareas_Seguimiento_Programado) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Tareas_Con_Seguimiento_Semanal', 'value' => 0],
                                ['name' => 'Total_Tareas_Seguimiento_Programado', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Seguimiento semanal completo y documentado', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Riesgo Leve (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Seguimiento adecuado con omisiones aisladas', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Falta de seguimiento que afecta la toma de decisiones', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Precisión y Completitud de Gestión Documental (Desistimientos / Derechos de Petición)',
                            'definition' => 'Porcentaje de expedientes jurídicos (desistimientos, peticiones, tutelas) con soporte documental completo archivado en Drive. Meta: ≥ 90%.',
                            'formula' => '(Expedientes_Juridicos_Completos_Drive / Total_Expedientes_Juridicos) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Expedientes_Juridicos_Completos_Drive', 'value' => 0],
                                ['name' => 'Total_Expedientes_Juridicos', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 90, 'max_value' => 1000, 'qualification' => '90% – 100% — Documentación completa, organizada y precisa en Drive', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 60, 'max_value' => 89.99, 'qualification' => '60% – 89.99% — Avances adecuados con faltantes por gestionar', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 59.99, 'qualification' => '< 60% — Falta importante de soportes documentales', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Contribución al Ambiente Laboral (Evaluación 360°)',
                            'definition' => 'Evaluación 360° de colaboración, actitud, proyección y confiabilidad operativa (escala de 1 a 5). Meta: ≥ 4.5 pts.',
                            'formula' => 'Puntaje_Evaluacion_360',
                            'unit' => 'pts',
                            'parameters' => [
                                ['name' => 'Puntaje_Evaluacion_360', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Sobresaliente (Verde)', 'min_value' => 4.5, 'max_value' => 5.0, 'qualification' => '≥ 4.5 pts — Altísimo compromiso, excelente actitud y gran confiabilidad', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 3.5, 'max_value' => 4.49, 'qualification' => '3.5 – 4.49 pts — Desempeño sólido con oportunidades de liderazgo', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 3.49, 'qualification' => '< 3.5 pts — Oportunidades de mejora en trabajo en equipo', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Número de Hallazgos en Auditorías sobre Gestión Documental Jurídica',
                            'definition' => 'Verificación y auditoría interna/externa de calidad sobre la gestión documental jurídica. Meta: 0 hallazgos.',
                            'formula' => 'Numero_Hallazgos_Auditoria_Juridica',
                            'unit' => 'hallazgos',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Numero_Hallazgos_Auditoria_Juridica', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 0.0, 'qualification' => '0 hallazgos — Gestión documental impecable y trazable', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 1.0, 'max_value' => 2.0, 'qualification' => '1 – 2 hallazgos menores — Riesgo bajo con correcciones puntuales', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 2.01, 'max_value' => 1000, 'qualification' => '≥ 3 hallazgos — Deficiencias significativas en archivo legal', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],
            ];

            foreach ($kpisMarcela as $kpiData) {
                $userMarcela->kpis()->updateOrCreate(
                    ['name' => $kpiData['name']],
                    $kpiData
                );
            }
        }
    }
}
