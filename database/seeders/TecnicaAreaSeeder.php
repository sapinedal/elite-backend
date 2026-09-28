<?php

namespace Database\Seeders;

use App\Http\Modules\Configuracion\Models\Area;
use App\Http\Modules\Configuracion\Models\Position;
use App\Http\Modules\Users\Models\User;
use App\Http\Modules\Plantillas\Models\KPI;
use Illuminate\Database\Seeder;

class TecnicaAreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Asegurar el Área Técnica sin duplicar si ya existe con o sin tilde
        $tecnicaArea = Area::whereRaw('LOWER(name) IN (?, ?)', ['técnica', 'tecnica'])->first()
            ?? Area::firstOrCreate(
                ['name' => 'Técnica'],
                ['description' => 'Área Técnica encargada de supervisión técnica, obras civiles, almacén, materiales y control de inventarios']
            );

        // 2. Cargos pertenecientes al Área Técnica
        $positions = [
            'Director de Obra',
            'Líder de Almacén y Materiales',
            'Residente de Estructura y Urbanismo',
            'Residente de Acabados',
            'Almacenista de Obra',
            'Auxiliar de Almacén',
            'Coordinador Técnico',
        ];

        $createdPositions = [];
        foreach ($positions as $posName) {
            $createdPositions[$posName] = Position::where('area_id', $tecnicaArea->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($posName)])
                ->first()
                ?? Position::whereRaw('LOWER(name) = ?', [mb_strtolower($posName)])->first()
                ?? Position::firstOrCreate([
                    'name' => $posName,
                    'area_id' => $tecnicaArea->id
                ]);
        }

        // =========================================================================
        // USUARIO 1: ALMACENISTA DE OBRA (CC: 8359612 / id 27 - Juan Carlos Esquivel Hoyos)
        // =========================================================================
        $userAlmacen = User::where('document', '8359612')
            ->orWhere('id', 27)
            ->orWhereRaw('LOWER(email) IN (?, ?)', ['almacen@inverconstruccion.com', 'almacen.tecnica@elite.com'])
            ->first();

        $defaultFirstNameAlmacen = 'JUAN CARLOS';
        $defaultLastNameAlmacen = 'ESQUIVEL HOYOS';
        $defaultFullNameAlmacen = 'JUAN CARLOS ESQUIVEL HOYOS';

        if (!$userAlmacen) {
            $userAlmacen = User::create([
                'first_name' => mb_strtoupper($defaultFirstNameAlmacen, 'UTF-8'),
                'last_name' => mb_strtoupper($defaultLastNameAlmacen, 'UTF-8'),
                'name' => mb_strtoupper($defaultFullNameAlmacen, 'UTF-8'),
                'document' => '8359612',
                'position_id' => $createdPositions['Almacenista de Obra']->id,
                'area_id' => $tecnicaArea->id,
                'email' => 'almacen@inverconstruccion.com',
                'password' => bcrypt('Elite123'),
            ]);
        } else {
            $userAlmacen->update([
                'first_name' => mb_strtoupper($defaultFirstNameAlmacen, 'UTF-8'),
                'last_name' => mb_strtoupper($defaultLastNameAlmacen, 'UTF-8'),
                'name' => mb_strtoupper($defaultFullNameAlmacen, 'UTF-8'),
                'document' => $userAlmacen->document ?: '8359612',
                'position_id' => $createdPositions['Almacenista de Obra']->id,
                'area_id' => $userAlmacen->area_id ?: $tecnicaArea->id,
                'email' => ($userAlmacen->email && $userAlmacen->email !== 'almacen.tecnica@elite.com') ? $userAlmacen->email : 'almacen@inverconstruccion.com',
            ]);
        }

        $kpisAlmacen = [
            // BLOQUE A: Gestión de Materiales y Equipos (40%)
            [
                'name' => 'A. Gestión de Materiales y Equipos',
                'description' => 'Mide la precisión en la recepción de materiales, la agilidad en los tiempos de despacho y la rotación eficiente de inventario en almacén.',
                'formula' => 'Promedio ponderado de recepción, despacho y rotación de insumos',
                'target' => 100,
                'unit' => '%',
                'stage' => 'A. Gestión de Materiales y Equipos',
                'weight' => 40,
                'incidence' => 40,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Porcentaje de Conformidad en la Recepción de Materiales',
                        'definition' => 'Medir la precisión y control en la recepción de materiales, verificando que la entrega física cumpla con cantidad, calidad y especificaciones del pedido y documentación (facturas/remisiones).',
                        'formula' => '(Recepciones_Sin_Discrepancias / Total_Recepciones) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Recepciones_Sin_Discrepancias', 'value' => 0],
                            ['name' => 'Total_Recepciones', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo', 'min_value' => 99, 'max_value' => 1000, 'qualification' => '≥ 99% — Meta cumplida, desempeño óptimo', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'En riesgo', 'min_value' => 95, 'max_value' => 98.99, 'qualification' => '95% – 98.99% — Bajo riesgo, requiere mejora', 'color' => 'at_risk', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 94.99, 'qualification' => '< 95% — Meta incumplida, impacto directo en calidad y costos', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Tiempo Promedio de Atención en Ventanilla (Despacho)',
                        'definition' => 'Controlar el tiempo que toma atender una solicitud de entrega de material a contratistas/personal de obra en almacén.',
                        'formula' => 'Tiempo_Promedio_Atencion_Minutos',
                        'unit' => 'min',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Tiempo_Promedio_Atencion_Minutos', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo', 'min_value' => 0, 'max_value' => 15.0, 'qualification' => '≤ 15 min — Excelente nivel de servicio y agilidad en entrega', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable', 'min_value' => 15.01, 'max_value' => 25.0, 'qualification' => '15.1 – 25 min — Dentro de tolerancia operativa', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 25.01, 'max_value' => 1000, 'qualification' => '> 25 min — Cuello de botella en almacén', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Rotación de Inventarios (Despacho vs Entradas)',
                        'definition' => 'Evalúa la eficiencia en el flujo de materiales, garantizando que los insumos no permanezcan estancados en almacén.',
                        'formula' => '(Valor_Materiales_Despachados / Valor_Total_Entradas_Inventario) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Valor_Materiales_Despachados', 'value' => 0],
                            ['name' => 'Valor_Total_Entradas_Inventario', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo', 'min_value' => 90, 'max_value' => 1000, 'qualification' => '≥ 90% — Alta rotación, inventario eficiente', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable', 'min_value' => 75, 'max_value' => 89.99, 'qualification' => '75% – 89.99% — Rotación moderada', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 74.99, 'qualification' => '< 75% — Baja rotación, riesgo de sobrestock o estancamiento', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            // BLOQUE B: Control de Inventarios y Documentación (30%)
            [
                'name' => 'B. Control de Inventarios y Documentación',
                'description' => 'Garantiza la exactitud entre inventario físico y sistema (IDI), la rapidez en el registro de movimientos, el archivo de soportes y la realización oportuna de inventarios cíclicos.',
                'formula' => 'Promedio de precisión IDI, oportunidad de registro, archivo y conteos a tiempo',
                'target' => 100,
                'unit' => '%',
                'stage' => 'B. Control de Inventarios y Documentación',
                'weight' => 30,
                'incidence' => 30,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Precisión del Inventario (IDI - Índice de Diferencias)',
                        'definition' => 'Exactitud entre el inventario físico y el registrado en Multifox. Meta: IDI < 1% del valor total del inventario.',
                        'formula' => '(Valor_Absoluto_Diferencias / Valor_Total_Inventario) * 100',
                        'unit' => '%',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Valor_Absoluto_Diferencias', 'value' => 0],
                            ['name' => 'Valor_Total_Inventario', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo', 'min_value' => 0, 'max_value' => 1.0, 'qualification' => 'IDI < 1% — Exactitud sobresaliente', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable', 'min_value' => 1.01, 'max_value' => 2.5, 'qualification' => 'IDI 1% – 2.5% — Desviación moderada bajo control', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 2.51, 'max_value' => 1000, 'qualification' => 'IDI > 2.5% — Desviación alta física vs. sistema', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Oportunidad en el Registro de Movimientos en el Sistema',
                        'definition' => 'Tiempo transcurrido entre el movimiento físico (entrada/salida) y su registro en Multifox.',
                        'formula' => 'Tiempo_Promedio_Registro_Horas',
                        'unit' => 'horas',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Tiempo_Promedio_Registro_Horas', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo', 'min_value' => 0, 'max_value' => 4.0, 'qualification' => '≤ 4 horas — Registro en tiempo real', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable', 'min_value' => 4.01, 'max_value' => 8.0, 'qualification' => '4.1 – 8 horas — Registro dentro de la jornada', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 8.01, 'max_value' => 1000, 'qualification' => '> 8 horas — Retraso en actualización del sistema', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Cumplimiento del Archivo y Control Documental',
                        'definition' => 'Porcentaje de remisiones, facturas, actas y vales debidamente firmados, organizados y archivados.',
                        'formula' => '(Documentos_Completos_Archivados / Total_Documentos_Generados) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Documentos_Completos_Archivados', 'value' => 0],
                            ['name' => 'Total_Documentos_Generados', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Trazabilidad y archivo completo', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Parcial', 'min_value' => 95, 'max_value' => 99.99, 'qualification' => '95% – 99.99% — Faltantes menores en regularización', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 94.99, 'qualification' => '< 95% — Descontrol documental y riesgo de auditoría', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Inventarios Cíclicos Realizados a Tiempo',
                        'definition' => 'Cumplimiento del cronograma de conteos cíclicos semanales/quincenales de familias críticas de materiales.',
                        'formula' => '(Conteos_Realizados_ATiempo / Total_Conteos_Programados) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Conteos_Realizados_ATiempo', 'value' => 0],
                            ['name' => 'Total_Conteos_Programados', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Cumplimiento total del cronograma', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Parcial', 'min_value' => 80, 'max_value' => 99.99, 'qualification' => '80% – 99.99% — Cumplimiento parcial con justificación', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — Incumplimiento del plan de control', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            // BLOQUE C: Mantenimiento y Seguridad (15%)
            [
                'name' => 'C. Mantenimiento y Seguridad',
                'description' => 'Asegura la operatividad de herramientas y equipos menores, junto con altos estándares de orden, aseo y seguridad (5S/SST) en almacén.',
                'formula' => 'Promedio de disponibilidad de equipos y calificación de auditoría 5S/SST',
                'target' => 100,
                'unit' => '%',
                'stage' => 'C. Mantenimiento y Seguridad',
                'weight' => 15,
                'incidence' => 15,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Estado y Disponibilidad de Herramientas y Equipos Menores',
                        'definition' => 'Porcentaje de herramientas y equipos menores en estado operativo y disponibles para préstamo en obra.',
                        'formula' => '(Herramientas_Operativas / Total_Herramientas_Inventario) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Herramientas_Operativas', 'value' => 0],
                            ['name' => 'Total_Herramientas_Inventario', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo', 'min_value' => 95, 'max_value' => 1000, 'qualification' => '≥ 95% — Equipos en óptimas condiciones', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable', 'min_value' => 85, 'max_value' => 94.99, 'qualification' => '85% – 94.99% — Requiere mantenimiento preventivo', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 84.99, 'qualification' => '< 85% — Afectación a la operación de obra', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Orden, Aseo y Seguridad en el Almacén (Auditorías 5S / SST)',
                        'definition' => 'Calificación obtenida en las inspecciones periódicas de orden, aseo, señalización y almacenamiento seguro.',
                        'formula' => 'Calificacion_Inspeccion_5S',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Calificacion_Inspeccion_5S', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo', 'min_value' => 90, 'max_value' => 1000, 'qualification' => '≥ 90% — Almacén ordenado, seguro y señalizado', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable', 'min_value' => 80, 'max_value' => 89.99, 'qualification' => '80% – 89.99% — Oportunidades menores de mejora', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — Riesgo de accidentes o desorden crítico', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            // BLOQUE D: Eficiencia en Procesos (15%)
            [
                'name' => 'D. Eficiencia en Procesos',
                'description' => 'Mide la rapidez en respuesta a requerimientos, control de mermas, eficiencia en devoluciones, nivel de servicio a cuadrillas y mejoras implementadas.',
                'formula' => 'Promedio de tiempo de respuesta, mermas, devoluciones, servicio y mejoras',
                'target' => 100,
                'unit' => '%',
                'stage' => 'D. Eficiencia en Procesos',
                'weight' => 15,
                'incidence' => 15,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Tiempo Promedio de Respuesta a Requerimientos de Obra',
                        'definition' => 'Tiempo que toma al almacén procesar y alistar un pedido interno desde su solicitud.',
                        'formula' => 'Tiempo_Promedio_Respuesta_Horas',
                        'unit' => 'horas',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Tiempo_Promedio_Respuesta_Horas', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo', 'min_value' => 0, 'max_value' => 2.0, 'qualification' => '≤ 2 horas — Atención inmediata', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable', 'min_value' => 2.01, 'max_value' => 4.0, 'qualification' => '2.1 – 4 horas — Respuesta oportuna', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 4.01, 'max_value' => 1000, 'qualification' => '> 4 horas — Retraso que impacta cuadrillas', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Porcentaje de Mermas y Pérdidas de Materiales',
                        'definition' => 'Valor monetario de materiales deteriorados, vencidos o extraviados dentro del almacén respecto al valor bajo custodia.',
                        'formula' => '(Valor_Mermas_Perdidas / Valor_Total_Materiales_Custodia) * 100',
                        'unit' => '%',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Valor_Mermas_Perdidas', 'value' => 0],
                            ['name' => 'Valor_Total_Materiales_Custodia', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo', 'min_value' => 0, 'max_value' => 0.5, 'qualification' => '< 0.5% — Custodia excelente sin pérdidas', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Tolerable', 'min_value' => 0.51, 'max_value' => 1.5, 'qualification' => '0.5% – 1.5% — Merma operativa normal', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 1.51, 'max_value' => 1000, 'qualification' => '> 1.5% — Pérdida alta, requiere investigación', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Eficiencia en Devoluciones y Sobrantes de Obra',
                        'definition' => 'Porcentaje de materiales sobrantes reintegrados correctamente al sistema dentro de las 48 horas de recibidos.',
                        'formula' => '(Devoluciones_Reintegradas_Sistema / Total_Devoluciones_Recibidas) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Devoluciones_Reintegradas_Sistema', 'value' => 0],
                            ['name' => 'Total_Devoluciones_Recibidas', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo', 'min_value' => 95, 'max_value' => 1000, 'qualification' => '≥ 95% — Reintegro oportuno al stock', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable', 'min_value' => 80, 'max_value' => 94.99, 'qualification' => '80% – 94.99% — Acumulación temporal de sobrantes', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — Materiales sin ingresar al sistema', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Nivel de Servicio a Contratistas y Cuadrillas',
                        'definition' => 'Mide la satisfacción y efectividad en la entrega de materiales según la programación diaria de obra.',
                        'formula' => '(Despachos_Completos_ATiempo / Total_Solicitudes_Despacho) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Despachos_Completos_ATiempo', 'value' => 0],
                            ['name' => 'Total_Solicitudes_Despacho', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo', 'min_value' => 95, 'max_value' => 1000, 'qualification' => '≥ 95% — Servicio eficiente y sin demoras', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable', 'min_value' => 85, 'max_value' => 94.99, 'qualification' => '85% – 94.99% — Demoras puntuales no críticas', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 84.99, 'qualification' => '< 85% — Desatención a contratistas', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Implementación de Mejoras en Almacén',
                        'definition' => 'Iniciativas y mejoras implementadas para optimizar el almacenamiento, rotulado, flujo de materiales o control documental.',
                        'formula' => '(Mejoras_Implementadas / Mejoras_Propuestas_Aprobadas) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Mejoras_Implementadas', 'value' => 0],
                            ['name' => 'Mejoras_Propuestas_Aprobadas', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Innovación y optimización continua', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'En Proceso', 'min_value' => 50, 'max_value' => 99.99, 'qualification' => '50% – 99.99% — Mejoras en desarrollo', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Sin Avance', 'min_value' => 0, 'max_value' => 49.99, 'qualification' => '< 50% — Sin iniciativas de mejora', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ]
        ];

        foreach ($kpisAlmacen as $kpiData) {
            $userAlmacen->kpis()->updateOrCreate(
                ['name' => $kpiData['name']],
                $kpiData
            );
        }

        // =========================================================================
        // USUARIO 2: RESIDENTE DE ESTRUCTURA Y URBANISMO (CC: 1017186775 - Santiago Prieto)
        // =========================================================================
        $userResidente = User::where('document', '1017186775')
            ->orWhereRaw('LOWER(email) = ?', ['santiago.prieto@elite.com'])
            ->first();

        $defaultFirstNameResidente = 'SANTIAGO';
        $defaultLastNameResidente = 'PRIETO PINTO';
        $defaultFullNameResidente = 'SANTIAGO PRIETO PINTO';

        if (!$userResidente) {
            $userResidente = User::create([
                'first_name' => mb_strtoupper($defaultFirstNameResidente, 'UTF-8'),
                'last_name' => mb_strtoupper($defaultLastNameResidente, 'UTF-8'),
                'name' => mb_strtoupper($defaultFullNameResidente, 'UTF-8'),
                'document' => '1017186775',
                'position_id' => $createdPositions['Residente de Estructura y Urbanismo']->id,
                'area_id' => $tecnicaArea->id,
                'email' => 'santiago.prieto@elite.com',
                'password' => bcrypt('Elite123'),
            ]);
        } else {
            $userResidente->update([
                'first_name' => mb_strtoupper($userResidente->first_name ?: $defaultFirstNameResidente, 'UTF-8'),
                'last_name' => mb_strtoupper($userResidente->last_name ?: $defaultLastNameResidente, 'UTF-8'),
                'name' => mb_strtoupper($userResidente->name ?: $defaultFullNameResidente, 'UTF-8'),
                'document' => $userResidente->document ?: '1017186775',
                'position_id' => $userResidente->position_id ?: $createdPositions['Residente de Estructura y Urbanismo']->id,
                'area_id' => $userResidente->area_id ?: $tecnicaArea->id,
            ]);
        }

        $kpisResidente = [
            // BLOQUE A: Supervisión Técnica y Ejecución de Estructura y urbanismo (40%)
            [
                'name' => 'A. Supervisión Técnica y Ejecución de Estructura y urbanismo',
                'description' => 'Mide la adherencia al programa de avance físico de obra gruesa y urbanismo, el control de errores críticos antes de supervisión externa y la eficiencia en el cubicaje de concreto y acero.',
                'formula' => 'Promedio de cumplimiento de cronograma, cero errores críticos y cubicación en rango',
                'target' => 95,
                'unit' => '%',
                'stage' => 'A. Supervisión Técnica y Ejecución de Estructura y urbanismo',
                'weight' => 40,
                'incidence' => 40,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Porcentaje de Cumplimiento del Programa Mensual de Estructura y Urbanismo',
                        'definition' => 'Mide el avance real ejecutado frente a lo planificado en el cronograma maestro para actividades de estructura y urbanismo.',
                        'formula' => '(Avance_Fisico_Real_Acumulado / Avance_Fisico_Programado_Acumulado) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Avance_Fisico_Real_Acumulado', 'value' => 0],
                            ['name' => 'Avance_Fisico_Programado_Acumulado', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Verde)', 'min_value' => 95, 'max_value' => 1000, 'qualification' => '≥ 95% — Cumplimiento total del programa', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Riesgo Moderado (Amarillo)', 'min_value' => 90, 'max_value' => 94.99, 'qualification' => '90% – 94.99% — Riesgo moderado, requiere seguimiento', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Incumplimiento Crítico (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Incumplimiento crítico, plan de recuperación inmediato', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Cero Errores Críticos Previos a Supervisión Externa',
                        'definition' => 'Verificación y liberación interna rigurosa de armados de acero, formaletería, dovelas y vaciados antes de la inspección de interventoría externa.',
                        'formula' => 'Numero_Errores_Criticos_Identificados',
                        'unit' => 'errores',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Numero_Errores_Criticos_Identificados', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Cumple (Verde)', 'min_value' => 0, 'max_value' => 0.0, 'qualification' => '0 errores — Detección oportuna antes de fundición', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Alerta (Amarillo)', 'min_value' => 1, 'max_value' => 2.0, 'qualification' => '1 – 2 hallazgos menores subsanados', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Crítico (Rojo)', 'min_value' => 2.01, 'max_value' => 1000, 'qualification' => '> 2 errores críticos en supervisión', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Eficiencia en el Uso de Materiales (Cubicación vs Planos)',
                        'definition' => 'Mide la eficiencia en el uso de concreto y acero. Desviación entre cubicaje real y cubicaje de diseño (Meta: Desviación ≤ 5%).',
                        'formula' => '((Cubicaje_Real - Cubicaje_Diseno) / Cubicaje_Diseno) * 100',
                        'unit' => '%',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Cubicaje_Real', 'value' => 0],
                            ['name' => 'Cubicaje_Diseno', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Cumple (Verde)', 'min_value' => 0, 'max_value' => 5.0, 'qualification' => '0% – 5% — Dentro del rango permitido, cubicaje controlado', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'En Riesgo (Amarillo)', 'min_value' => 5.01, 'max_value' => 10.0, 'qualification' => '5.01% – 10% — En riesgo, requiere revisión y ajuste en vaciados', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Incumplimiento (Rojo)', 'min_value' => 10.01, 'max_value' => 1000, 'qualification' => '> 10% — Incumplimiento, alto desperdicio o desvío', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            // BLOQUE B: Control de Calidad y Cumplimiento Normativo (30%)
            [
                'name' => 'B. Control de Calidad y Cumplimiento Normativo',
                'description' => 'Asegura que los elementos estructurales cumplan con la norma NSR-10, liberación previa de armados, cierre de no conformidades y resistencias de laboratorio.',
                'formula' => 'Promedio de porcentaje de liberación conforme, cierre a tiempo de NC y ensayos f\'c',
                'target' => 100,
                'unit' => '%',
                'stage' => 'B. Control de Calidad y Cumplimiento Normativo',
                'weight' => 30,
                'incidence' => 30,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Porcentaje de Liberación de Elementos Estructurales',
                        'definition' => 'Elementos estructurales (zapatas, muros, losas, columnas, tanques) con protocolo de liberación firmado antes de cada fundición.',
                        'formula' => '(Elementos_Liberados_Conforme / Total_Elementos_Inspeccionados) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Elementos_Liberados_Conforme', 'value' => 0],
                            ['name' => 'Total_Elementos_Inspeccionados', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Liberación total conforme a especificaciones', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Parcial (Amarillo)', 'min_value' => 95, 'max_value' => 99.99, 'qualification' => '95% – 99.99% — Liberación con observaciones menores', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 94.99, 'qualification' => '< 95% — No conformidades estructurales recurrentes', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Tiempo de Respuesta y Cierre de No Conformidades Estructurales',
                        'definition' => 'Días calendario promedio transcurridos entre la notificación de un hallazgo técnico y su solución aprobada por cálculo/interventoría.',
                        'formula' => 'Tiempo_Promedio_Cierre_Dias',
                        'unit' => 'días',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Tiempo_Promedio_Cierre_Dias', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 3.0, 'qualification' => '≤ 3 días — Cierre inmediato de hallazgos', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable (Amarillo)', 'min_value' => 3.01, 'max_value' => 5.0, 'qualification' => '3.1 – 5 días — Cierre dentro del plazo límite', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Crítico (Rojo)', 'min_value' => 5.01, 'max_value' => 1000, 'qualification' => '> 5 días — Retraso que compromete frentes sucesores', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Ensayos y Resistencia de Concretos (Laboratorio)',
                        'definition' => 'Porcentaje de cilindros de concreto ensayados a 7, 14 y 28 días que cumplen o superan la resistencia f\'c especificada en el diseño.',
                        'formula' => '(Muestras_Cumplen_Resistencia / Total_Muestras_Ensayadas) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Muestras_Cumplen_Resistencia', 'value' => 0],
                            ['name' => 'Total_Muestras_Ensayadas', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Todas las muestras alcanzan o superan f\'c de diseño', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Alerta (Amarillo)', 'min_value' => 95, 'max_value' => 99.99, 'qualification' => '95% – 99.99% — Ensayos bajo seguimiento patológico', 'color' => 'acceptable', 'score' => 70],
                            ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 94.99, 'qualification' => '< 95% — Bajas resistencias críticas', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            // BLOQUE C: Gestión de Información y Coordinación (15%)
            [
                'name' => 'C. Gestión de Información y Coordinación',
                'description' => 'Mide la puntualidad en reportes técnicos, resolución ágil de solicitudes en Dashboard, archivo de planos As-Built en Drive y clima laboral.',
                'formula' => 'Promedio de atención Dashboard, informes semanales, evaluación 360 y As-Built',
                'target' => 100,
                'unit' => '%',
                'stage' => 'C. Gestión de Información y Coordinación',
                'weight' => 15,
                'incidence' => 15,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Resolución Oportuna de Consultas Técnicas en Dashboard',
                        'definition' => 'Tiempo de respuesta a requerimientos técnicos, RFI y dudas de contratistas registradas en el sistema.',
                        'formula' => 'Tiempo_Resolucion_Consultas_Horas',
                        'unit' => 'horas',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Tiempo_Resolucion_Consultas_Horas', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 24.0, 'qualification' => '≤ 24 horas — Respuesta ágil y oportuna', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable (Amarillo)', 'min_value' => 24.01, 'max_value' => 48.0, 'qualification' => '24.1 – 48 horas — Respuesta dentro del tiempo estándar', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Crítico (Rojo)', 'min_value' => 48.01, 'max_value' => 1000, 'qualification' => '> 48 horas — Retraso en definiciones técnicas', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Puntualidad en la Entrega de Informes de Avance Semanal',
                        'definition' => 'Cumplimiento en fecha y formato establecido para los informes semanales de vaciados, avance físico y consumos de obra.',
                        'formula' => '(Informes_Entregados_ATiempo / Total_Informes_Programados) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Informes_Entregados_ATiempo', 'value' => 0],
                            ['name' => 'Total_Informes_Programados', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Entregas puntuales en todos los cortes', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Parcial (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Retraso menor justificado', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Incumplimiento (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Incumplimiento en reporte de obra', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Contribución al Clima Laboral y Coordinación de Equipos (Evaluación 360°)',
                        'definition' => 'Evaluación de competencias de liderazgo, trabajo en equipo, comunicación con cuadrillas y contratistas.',
                        'formula' => 'Puntuacion_Evaluacion_360',
                        'unit' => 'pts',
                        'parameters' => [
                            ['name' => 'Puntuacion_Evaluacion_360', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Sobresaliente (Verde)', 'min_value' => 4.5, 'max_value' => 5.0, 'qualification' => '≥ 4.5 pts — Liderazgo y coordinación sobresaliente', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable (Amarillo)', 'min_value' => 3.5, 'max_value' => 4.49, 'qualification' => '3.5 – 4.49 pts — Desempeño adecuado con oportunidades de mejora', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 3.49, 'qualification' => '< 3.5 pts — Oportunidad de fortalecimiento en liderazgo', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Gestión y Archivo de Planos As-Built y Modificaciones en Drive',
                        'definition' => 'Actualización y cargue oportuno en Google Drive de modificaciones aprobadas en planos y detalles constructivos.',
                        'formula' => '(Planos_Actualizados_Drive / Total_Modificaciones_Obra) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Planos_Actualizados_Drive', 'value' => 0],
                            ['name' => 'Total_Modificaciones_Obra', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Planos As-Built actualizados semanalmente en Drive', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Parcial (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Actualización parcial', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Desorganización o documentación desactualizada', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            // BLOQUE D: Control Presupuestal y Administrativo (15%)
            [
                'name' => 'D. Control Presupuestal y Administrativo',
                'description' => 'Control de costos y proyección financiera en la fase de estructura y urbanismo. Meta: Desviación ≤ 2% y margen de P&G positivo.',
                'formula' => '((Costo_Real - Presupuesto_Aprobado) / Presupuesto_Aprobado) * 100',
                'target' => 100,
                'unit' => '%',
                'stage' => 'D. Control Presupuestal y Administrativo',
                'weight' => 15,
                'incidence' => 15,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Desviación Presupuestal Acumulada Fase Estructura y urbanismo',
                        'definition' => 'Control de costos y proyección financiera en la fase de estructura y urbanismo. Meta: Desviación ≤ 2% con P&G positivo.',
                        'formula' => '((Costo_Real - Presupuesto_Aprobado) / Presupuesto_Aprobado) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Presupuesto_Aprobado_Estructura', 'value' => 0],
                            ['name' => 'Costo_Real_Proyectado', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 2.0, 'qualification' => '0% – 2% — Control presupuestal óptimo, dentro de la meta y P&G positivo', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Alerta (Amarillo)', 'min_value' => 2.01, 'max_value' => 5.0, 'qualification' => '> 2% – 5% — Desviación moderada bajo control', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Crítico (Rojo)', 'min_value' => 5.01, 'max_value' => 1000, 'qualification' => '> 5% — Sobrecostos en estructura/urbanismo', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ]
        ];

        foreach ($kpisResidente as $kpiData) {
            $userResidente->kpis()->updateOrCreate(
                ['name' => $kpiData['name']],
                $kpiData
            );
        }

        // =========================================================================
        // USUARIO 3: DIRECTOR DE OBRA (CC: 98632277 - Jorge Elias Pemberty Zapata)
        // =========================================================================
        $userDirector = User::where('document', '98632277')
            ->orWhereRaw('LOWER(email) = ?', ['dirobrasanmiguel@inverconstruccion.com'])
            ->first();

        $defaultFirstNameDirector = 'JORGE ELIAS';
        $defaultLastNameDirector = 'PEMBERTY ZAPATA';
        $defaultFullNameDirector = 'JORGE ELIAS PEMBERTY ZAPATA';

        if (!$userDirector) {
            $userDirector = User::create([
                'first_name' => mb_strtoupper($defaultFirstNameDirector, 'UTF-8'),
                'last_name' => mb_strtoupper($defaultLastNameDirector, 'UTF-8'),
                'name' => mb_strtoupper($defaultFullNameDirector, 'UTF-8'),
                'document' => '98632277',
                'position_id' => $createdPositions['Director de Obra']->id,
                'area_id' => $tecnicaArea->id,
                'email' => 'dirobrasanmiguel@inverconstruccion.com',
                'password' => bcrypt('Elite123'),
            ]);
        } else {
            $userDirector->update([
                'first_name' => mb_strtoupper($userDirector->first_name ?: $defaultFirstNameDirector, 'UTF-8'),
                'last_name' => mb_strtoupper($userDirector->last_name ?: $defaultLastNameDirector, 'UTF-8'),
                'name' => mb_strtoupper($userDirector->name ?: $defaultFullNameDirector, 'UTF-8'),
                'document' => $userDirector->document ?: '98632277',
                'position_id' => $userDirector->position_id ?: $createdPositions['Director de Obra']->id,
                'area_id' => $userDirector->area_id ?: $tecnicaArea->id,
            ]);
        }

        $kpisDirector = [
            // BLOQUE A: Cumplimiento de Plazo y Programación (50%)
            [
                'name' => 'A. Cumplimiento de Plazo y Programación',
                'description' => 'Mide el avance físico acumulado de la obra frente a la curva S programada, el cumplimiento de hitos críticos y la resolución oportuna de desvíos técnicos en campo.',
                'formula' => 'Promedio ponderado de Curva S, hitos clave y tiempo de resolución de desvíos',
                'target' => 100,
                'unit' => '%',
                'stage' => 'A. Cumplimiento de Plazo y Programación',
                'weight' => 50,
                'incidence' => 50,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Porcentaje de Avance Físico vs. Programado (Curva S)',
                        'definition' => 'Mide el progreso real de la obra frente al cronograma planificado maestro. Meta: Dentro de ±5% del avance programado.',
                        'formula' => '(Avance_Fisico_Real_Acumulado / Avance_Fisico_Programado_Acumulado) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Avance_Fisico_Real_Acumulado', 'value' => 0],
                            ['name' => 'Avance_Fisico_Programado_Acumulado', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Verde (Cumple)', 'min_value' => 95, 'max_value' => 1000, 'qualification' => '≥ 95% — Dentro de parámetros aceptables (desviación ≤ ±5%)', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Amarillo (Alerta)', 'min_value' => 90, 'max_value' => 94.99, 'qualification' => '90% – 94.9% — Ligeramente por debajo, requiere seguimiento', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Rojo (Incumplimiento)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Incumplimiento significativo, retraso que afecta hitos', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Cumplimiento de Hitos Clave',
                        'definition' => 'Porcentaje de hitos mayores (Fundaciones, Estructura, Acabados y Urbanismo) entregados en la fecha planificada o antes.',
                        'formula' => '(Hitos_Entregados_ATiempo / Total_Hitos_Programados) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Hitos_Entregados_ATiempo', 'value' => 0],
                            ['name' => 'Total_Hitos_Programados', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Verde', 'min_value' => 90, 'max_value' => 100, 'qualification' => '90 – 100% — A tiempo o hasta -3 semanas de atraso (tolerancia técnica)', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Naranja', 'min_value' => 70, 'max_value' => 89.99, 'qualification' => '70 – 89% — Atraso moderado (-4 a -8 sem), requiere plan de impulso', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Rojo', 'min_value' => 0, 'max_value' => 69.99, 'qualification' => '< 70% — Más de -9 semanas de atraso, impacto en ruta crítica', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Tiempo Promedio de Resolución de Desvíos',
                        'definition' => 'Días promedio requeridos para corregir y cerrar desviaciones y contingencias críticas en obra. Meta: ≤ 5 días hábiles.',
                        'formula' => 'Dias_Promedio_Resolucion_Desvios',
                        'unit' => 'días hábiles',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Dias_Promedio_Resolucion_Desvios', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 5.0, 'qualification' => '≤ 5 días hábiles — Solución en el mes del hallazgo', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Parcial (Amarillo)', 'min_value' => 5.01, 'max_value' => 10.0, 'qualification' => '5.1 – 10 días hábiles — Gestión técnica en proceso', 'color' => 'acceptable', 'score' => 75],
                            ['level' => 'Crítico (Rojo)', 'min_value' => 10.01, 'max_value' => 1000, 'qualification' => '> 10 días hábiles — Desvío crítico abierto sin cierre', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            // BLOQUE B: Control Presupuestal y Rentabilidad (20%)
            [
                'name' => 'B. Control Presupuestal y Rentabilidad',
                'description' => 'Control de costos acumulados frente al presupuesto aprobado en todos los frentes del proyecto, rentabilidad bruta proyectada y productividad de mano de obra.',
                'formula' => 'Promedio ponderado de desviación presupuestal, margen P&G e índice de productividad',
                'target' => 100,
                'unit' => '%',
                'stage' => 'B. Control Presupuestal y Rentabilidad',
                'weight' => 20,
                'incidence' => 20,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Desviación Presupuestal del Proyecto (Torre 1, Torre 2 y Urbanismo)',
                        'definition' => 'Mide la variación entre el costo real acumulado/proyectado y el presupuesto aprobado. Meta: Desviación ≤ 2%.',
                        'formula' => '((Costo_Real_Acumulado - Presupuesto_Aprobado) / Presupuesto_Aprobado) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Presupuesto_Aprobado_Global', 'value' => 0],
                            ['name' => 'Costo_Real_Proyectado_Global', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Verde', 'min_value' => 0, 'max_value' => 2.0, 'qualification' => '0% – 2% — Control presupuestal óptimo dentro de la meta', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Amarillo', 'min_value' => 2.01, 'max_value' => 5.0, 'qualification' => '> 2% – 5% — Alerta: desviación moderada bajo seguimiento', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Rojo', 'min_value' => 5.01, 'max_value' => 1000, 'qualification' => '> 5% — Desviación crítica que requiere acción inmediata', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Rentabilidad Bruta de la Obra (Margen de Contribución)',
                        'definition' => 'Mide la conservación del margen de contribución proyectado y P&G positivo en las líneas analizadas del proyecto.',
                        'formula' => 'Margen_Contribucion_Real_PG',
                        'unit' => '$',
                        'parameters' => [
                            ['name' => 'Margen_Contribucion_Real_PG', 'value' => 0],
                            ['name' => 'Margen_Proyectado_Esperado', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 999999999999, 'qualification' => '≥ margen proyectado — P&G positivo y margen de rentabilidad saludable', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'En Riesgo (Amarillo)', 'min_value' => -100000000, 'max_value' => -0.01, 'qualification' => 'Margen positivo pero inferior a la meta', 'color' => 'acceptable', 'score' => 75],
                            ['level' => 'Deficiente (Rojo)', 'min_value' => -999999999999, 'max_value' => -100000001, 'qualification' => 'P&G negativo con sobrecostos acumulados', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Índice de Productividad de Mano de Obra',
                        'definition' => 'Eficiencia en el uso de mano de obra y porcentaje de actividades con incumplimiento atribuible al rendimiento del contratista.',
                        'formula' => '(Actividades_Incumplidas_Rendimiento / Total_Actividades_Programadas) * 100',
                        'unit' => '%',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Actividades_Incumplidas_Rendimiento', 'value' => 0],
                            ['name' => 'Total_Actividades_Programadas', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Cumple (Verde)', 'min_value' => 0, 'max_value' => 20.0, 'qualification' => '≤ 20% — Productividad de mano de obra controlada', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Riesgo Moderado (Amarillo)', 'min_value' => 20.01, 'max_value' => 30.0, 'qualification' => '21% – 30% — Rendimientos por debajo de lo esperado', 'color' => 'acceptable', 'score' => 71],
                            ['level' => 'Crítico (Rojo)', 'min_value' => 30.01, 'max_value' => 1000, 'qualification' => '> 30% — Afectación severa por bajo rendimiento de cuadrillas', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            // BLOQUE C: Calidad y Seguridad (10%)
            [
                'name' => 'C. Calidad y Seguridad',
                'description' => 'Monitoreo de no conformidades críticas de calidad, índice de frecuencia de accidentalidad laboral (IF) y cumplimiento en auditorías SG-SST.',
                'formula' => 'Promedio de no conformidades resueltas, índice de frecuencia IF y auditoría SST',
                'target' => 100,
                'unit' => '%',
                'stage' => 'C. Calidad y Seguridad',
                'weight' => 10,
                'incidence' => 10,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'No Conformidades Críticas de Calidad',
                        'definition' => 'Defectos graves de calidad detectados en obra al cierre de cada etapa constructiva.',
                        'formula' => 'Numero_No_Conformidades_Criticas',
                        'unit' => 'NC',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Numero_No_Conformidades_Criticas', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Cumplimiento Total (Verde)', 'min_value' => 0, 'max_value' => 0.0, 'qualification' => '0 NC — Sin no conformidades críticas al cierre de etapa', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Riesgo Moderado (Amarillo)', 'min_value' => 1.0, 'max_value' => 5.0, 'qualification' => '1 – 5% / 1 NC — Requiere seguimiento y cierre técnico', 'color' => 'acceptable', 'score' => 63],
                            ['level' => 'Crítico (Rojo)', 'min_value' => 5.01, 'max_value' => 1000, 'qualification' => '> 5% / > 1 NC crítica sin resolver', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Índice de Frecuencia de Accidentes (IF)',
                        'definition' => 'Frecuencia de accidentes laborales de contratistas y personal propio frente al promedio sectorial de referencia.',
                        'formula' => 'Accidentes_Registrados_Periodo',
                        'unit' => 'accidentes',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Accidentes_Registrados_Periodo', 'value' => 0],
                            ['name' => 'Promedio_Sector_Referencia', 'value' => 7.49],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Cumplimiento Total (Verde)', 'min_value' => 0, 'max_value' => 0.0, 'qualification' => '0 accidentes — Cumplimiento total, meta cero accidentes', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Riesgo Moderado (Amarillo)', 'min_value' => 0.1, 'max_value' => 4.0, 'qualification' => '0.1 – 4.0 (1-2 eventos) — Por debajo del sector (7.49), requiere refuerzos', 'color' => 'acceptable', 'score' => 90],
                            ['level' => 'Crítico (Rojo)', 'min_value' => 4.01, 'max_value' => 1000, 'qualification' => '> 4.0 — Por encima del estándar sectorial', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Puntuación Auditorías SG-SST',
                        'definition' => 'Porcentaje de cumplimiento normativo obtenido en la última auditoría del Sistema de Gestión de Seguridad y Salud en el Trabajo.',
                        'formula' => 'Calificacion_Auditoria_SST',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Calificacion_Auditoria_SST', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Cumplimiento Alto (Verde)', 'min_value' => 90, 'max_value' => 1000, 'qualification' => '≥ 90% — Supera o iguala estándar normativo del sector', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Cumplimiento Parcial (Amarillo)', 'min_value' => 80, 'max_value' => 89.99, 'qualification' => '80% – 89% — Requiere ajustes en evidencias y campo', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Incumplimiento (Rojo)', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — No conforme con lineamientos de SST', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            // BLOQUE D: Gestión de Recursos y Relaciones (20%)
            [
                'name' => 'D. Gestión de Recursos y Relaciones',
                'description' => 'Optimización en consumo de materiales (desperdicio ≤ 5%), liderazgo y clima laboral (360°), puntualidad en informes de cierre y tareas semanales.',
                'formula' => 'Promedio de control de desperdicio, evaluación 360, informes a tiempo y tareas Dashboard/Drive',
                'target' => 100,
                'unit' => '%',
                'stage' => 'D. Gestión de Recursos y Relaciones',
                'weight' => 20,
                'incidence' => 20,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Uso Eficiente de Recursos (Desperdicio de Materiales)',
                        'definition' => 'Promedio general de desperdicio real de materiales e insumos críticos de obra frente al estándar presupuestado (≤ 5%).',
                        'formula' => 'Promedio_Desperdicio_Real_Materiales',
                        'unit' => '%',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Promedio_Desperdicio_Real_Materiales', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 5.0, 'qualification' => '≤ 5% — Eficiencia en consumo y ahorro presupuestal', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Alerta (Amarillo)', 'min_value' => 5.01, 'max_value' => 10.0, 'qualification' => '5.1% – 10% — Desviación moderada en insumos clave', 'color' => 'acceptable', 'score' => 75],
                            ['level' => 'Crítico (Rojo)', 'min_value' => 10.01, 'max_value' => 1000, 'qualification' => '> 10% — Pérdida de control en consumo de materiales', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Contribución al Ambiente Laboral (Evaluación 360° / Feedback Interno)',
                        'definition' => 'Evaluación integral de competencias de liderazgo directivo, toma de decisiones bajo presión, articulación de frentes y soporte al equipo.',
                        'formula' => 'Puntaje_Evaluacion_360',
                        'unit' => 'pts',
                        'parameters' => [
                            ['name' => 'Puntaje_Evaluacion_360', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Sobresaliente (Verde)', 'min_value' => 4.5, 'max_value' => 5.0, 'qualification' => '≥ 4.5 pts — Liderazgo integral y apoyo al equipo', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable (Amarillo)', 'min_value' => 3.5, 'max_value' => 4.49, 'qualification' => '3.5 – 4.49 pts — Cumplimiento técnico con oportunidad en cercanía y escucha', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 3.49, 'qualification' => '< 3.5 pts — Desalineación en clima laboral', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Puntualidad en Presentación de Informes de Cierre',
                        'definition' => 'Cumplimiento en fecha y formato establecido para la entrega de informes técnicos y financieros mensuales de cierre de obra.',
                        'formula' => '(Informes_Cierre_ATiempo / Total_Informes_Cierre_Requeridos) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Informes_Cierre_ATiempo', 'value' => 0],
                            ['name' => 'Total_Informes_Cierre_Requeridos', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Cumplimiento Total (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Informes de cierre presentados en fecha y formato', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Cumplimiento Parcial (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99% — Entrega parcial o con desfase menor', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Incumplimiento (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Incumplimiento en reporte de cierre', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Tareas con Seguimiento Registrado en Dashboard y Drive',
                        'definition' => 'Porcentaje de tareas programadas semanalmente que cuentan con actualización de estado y seguimiento documentado.',
                        'formula' => '(Tareas_Con_Seguimiento_Registrado / Total_Tareas_Programadas) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Tareas_Con_Seguimiento_Registrado', 'value' => 0],
                            ['name' => 'Total_Tareas_Programadas', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Cumplimiento Total (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Todas las tareas con seguimiento registrado', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Parcial (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Cumplimiento parcial en actualización', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Incumplimiento (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Tareas sin seguimiento registrado', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ]
        ];

        foreach ($kpisDirector as $kpiData) {
            $userDirector->kpis()->updateOrCreate(
                ['name' => $kpiData['name']],
                $kpiData
            );
        }

        // =========================================================================
        // USUARIO 4: RESIDENTE DE ACABADOS (CC: 1128470626 - Julian Andres Posada Morales)
        // =========================================================================
        $userAcabados = User::where('document', '1128470626')
            ->orWhereRaw('LOWER(email) = ?', ['residenteacabados@inverconstruccion.com'])
            ->first();

        $defaultFirstNameAcabados = 'JULIAN ANDRES';
        $defaultLastNameAcabados = 'POSADA MORALES';
        $defaultFullNameAcabados = 'JULIAN ANDRES POSADA MORALES';

        if (!$userAcabados) {
            $userAcabados = User::create([
                'first_name' => mb_strtoupper($defaultFirstNameAcabados, 'UTF-8'),
                'last_name' => mb_strtoupper($defaultLastNameAcabados, 'UTF-8'),
                'name' => mb_strtoupper($defaultFullNameAcabados, 'UTF-8'),
                'document' => '1128470626',
                'position_id' => $createdPositions['Residente de Acabados']->id,
                'area_id' => $tecnicaArea->id,
                'email' => 'residenteacabados@inverconstruccion.com',
                'password' => bcrypt('Elite123'),
            ]);
        } else {
            $userAcabados->update([
                'first_name' => mb_strtoupper($userAcabados->first_name ?: $defaultFirstNameAcabados, 'UTF-8'),
                'last_name' => mb_strtoupper($userAcabados->last_name ?: $defaultLastNameAcabados, 'UTF-8'),
                'name' => mb_strtoupper($userAcabados->name ?: $defaultFullNameAcabados, 'UTF-8'),
                'document' => $userAcabados->document ?: '1128470626',
                'position_id' => $userAcabados->position_id ?: $createdPositions['Residente de Acabados']->id,
                'area_id' => $userAcabados->area_id ?: $tecnicaArea->id,
            ]);
        }

        $kpisAcabados = [
            // BLOQUE A: Cumplimiento de Plazo y Programación (30%)
            [
                'name' => 'A. Cumplimiento de Plazo y Programación',
                'description' => 'Mide el avance físico real de la fase de acabados frente a la programación oficial y el cumplimiento de entrega de unidades y zonas comunes al área comercial.',
                'formula' => 'Promedio ponderado de avance físico y entregas a tiempo de unidades',
                'target' => 95,
                'unit' => '%',
                'stage' => 'A. Cumplimiento de Plazo y Programación',
                'weight' => 30,
                'incidence' => 30,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Porcentaje de Avance Físico de Acabados vs. Programado',
                        'definition' => 'Compara el progreso real acumulado de actividades de acabados con el programado en el cronograma. Meta: Dentro de ±5% del avance programado.',
                        'formula' => '(Avance_Fisico_Real_Acumulado / Avance_Fisico_Programado_Acumulado) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Avance_Fisico_Real_Acumulado', 'value' => 0],
                            ['name' => 'Avance_Fisico_Programado_Acumulado', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Verde)', 'min_value' => 90, 'max_value' => 1000, 'qualification' => 'Cumplimiento ≥ 90% — Proyecto bajo control y en parámetros aceptables', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'En Riesgo (Amarillo)', 'min_value' => 70, 'max_value' => 89.99, 'qualification' => 'Cumplimiento 70% – 89% — Requiere ajustes inmediatos en frentes de trabajo', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 69.99, 'qualification' => 'Cumplimiento < 70% — Plan de acción obligatorio por atraso crítico', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Cumplimiento de Hitos de Entrega de Unidades/Zonas Comunes (Acabados)',
                        'definition' => 'Mide la puntualidad en finalización y entrega formal de apartamentos y zonas comunes al área comercial y propietarios. Meta: 90% de entregas en o antes de la fecha programada.',
                        'formula' => '(Unidades_Entregadas_ATiempo / Total_Unidades_Programadas) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Unidades_Entregadas_ATiempo', 'value' => 0],
                            ['name' => 'Total_Unidades_Programadas', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Verde)', 'min_value' => 90, 'max_value' => 1000, 'qualification' => '≥ 90% — Entregas a tiempo y controladas', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'En Riesgo (Amarillo)', 'min_value' => 75, 'max_value' => 89.99, 'qualification' => '75% – 89% — Desviaciones menores con capacidad de ajuste semanal', 'color' => 'acceptable', 'score' => 75],
                            ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 74.99, 'qualification' => '< 75% — Afectación a la promesa comercial y entregas', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            // BLOQUE B: Control de Calidad y No Conformidades (30%)
            [
                'name' => 'B. Control de Calidad y No Conformidades',
                'description' => 'Garantiza la entrega de acabados sin defectos, control estricto de reprocesos y atención inmediata a observaciones de entrega.',
                'formula' => 'Cumplimiento ponderado de no conformidades, control de reprocesos y resolución de observaciones',
                'target' => 100,
                'unit' => '%',
                'stage' => 'B. Control de Calidad y No Conformidades',
                'weight' => 30,
                'incidence' => 30,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Número de No Conformidades de Calidad en Acabados',
                        'definition' => 'Cuantifica defectos graves detectados interna y externamente en acabados. Meta: < 2 no conformidades internas/mes y 0 críticas externas.',
                        'formula' => 'Numero_No_Conformidades_Mes',
                        'unit' => 'NC',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Numero_No_Conformidades_Mes', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 1.0, 'qualification' => '≤ 1 NC — Control adecuado sin reprocesos formales ni fallas reportadas', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable (Amarillo)', 'min_value' => 1.01, 'max_value' => 2.0, 'qualification' => '2 NC — Riesgo moderado con plan de acción correctivo', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Crítico (Rojo)', 'min_value' => 2.01, 'max_value' => 1000, 'qualification' => '> 2 NC internas o críticas externas', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Porcentaje de Reprocesos en Acabados',
                        'definition' => 'Evalúa la eficiencia y calidad en la primera ejecución de acabados. Meta: < 3% del costo total de la actividad.',
                        'formula' => '(Costo_Reprocesos / Costo_Total_Acabados) * 100',
                        'unit' => '%',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Costo_Reprocesos', 'value' => 0],
                            ['name' => 'Costo_Total_Acabados', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 3.0, 'qualification' => '< 3% — Calidad óptima en primera ejecución sin sobrecostos significativos', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Moderado (Amarillo)', 'min_value' => 3.01, 'max_value' => 5.0, 'qualification' => '3% – 5% — Requiere supervisión en frentes puntuales', 'color' => 'acceptable', 'score' => 75],
                            ['level' => 'Crítico (Rojo)', 'min_value' => 5.01, 'max_value' => 1000, 'qualification' => '> 5% — Alto sobrecosto por reprocesos', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Tiempo y Cumplimiento de Respuesta a Observaciones en Entregas de Obra al Área Comercial',
                        'definition' => 'Capacidad de respuesta y efectividad en la subsanación de observaciones técnicas detectadas durante la entrega de apartamentos al área comercial. Meta: ≥ 95%.',
                        'formula' => '(Observaciones_Subsanadas / Observaciones_Detectadas) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Observaciones_Subsanadas', 'value' => 0],
                            ['name' => 'Observaciones_Detectadas', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Verde)', 'min_value' => 95, 'max_value' => 1000, 'qualification' => '≥ 95% — Respuesta oportuna, entregas controladas sin reclamos comerciales', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable (Amarillo)', 'min_value' => 85, 'max_value' => 94.99, 'qualification' => '85% – 94.99% — Se corrige la mayoría, pero con rezagos menores', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 84.99, 'qualification' => '< 85% — Alto riesgo de reclamos y reprocesos post-entrega', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            // BLOQUE C: Control Presupuestal y Uso de Recursos (20%)
            [
                'name' => 'C. Control Presupuestal y Uso de Recursos',
                'description' => 'Monitoreo de la inversión de acabados frente al presupuesto aprobado y control estricto del porcentaje de desperdicio de materiales clave.',
                'formula' => 'Cumplimiento presupuestal de acabados y control de desperdicio de insumos',
                'target' => 100,
                'unit' => '%',
                'stage' => 'C. Control Presupuestal y Uso de Recursos',
                'weight' => 20,
                'incidence' => 20,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Desviación Presupuestal de la Fase de Acabados',
                        'definition' => 'Diferencia acumulada entre el costo real/proyectado y el presupuesto aprobado en todos los capítulos de acabados. Meta: Desviación < 2%.',
                        'formula' => '((Costo_Real_Acumulado - Presupuesto_Aprobado) / Presupuesto_Aprobado) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Presupuesto_Aprobado_Acabados', 'value' => 0],
                            ['name' => 'Costo_Real_Acabados', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 2.0, 'qualification' => '≤ 2% — Control presupuestal óptimo con P&G positivo', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'En Alerta (Amarillo)', 'min_value' => 2.01, 'max_value' => 5.0, 'qualification' => '> 2% – 5% — Desviación moderada bajo seguimiento', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Crítico (Rojo)', 'min_value' => 5.01, 'max_value' => 1000, 'qualification' => '> 5% — Sobrecostos que afectan la rentabilidad', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Porcentaje de Desperdicios de Materiales de Acabados',
                        'definition' => 'Eficiencia en el consumo de materiales clave de acabados. Meta: Desperdicio < 5% del valor total.',
                        'formula' => '(Valor_Desperdicios / Valor_Total_Materiales) * 100',
                        'unit' => '%',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Valor_Desperdicios', 'value' => 0],
                            ['name' => 'Valor_Total_Materiales', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 5.0, 'qualification' => '< 5% — Eficiencia en consumo y bajo desperdicio', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Moderado (Amarillo)', 'min_value' => 5.01, 'max_value' => 10.0, 'qualification' => '5% – 10% — Requiere ajuste en cortes y control en frentes', 'color' => 'acceptable', 'score' => 70],
                            ['level' => 'Crítico (Rojo)', 'min_value' => 10.01, 'max_value' => 1000, 'qualification' => '> 10% — Sobrecostos por alto desperdicio o roturas', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            // BLOQUE D: Gestión de Información y Coordinación (20%)
            [
                'name' => 'D. Gestión de Información y Coordinación',
                'description' => 'Eficiencia en resolución de dudas técnicas en Dashboard, puntualidad en actas de comité, evaluación 360° y gestión de quejas posventas post-entrega.',
                'formula' => 'Promedio ponderado de resolución técnica, actas a tiempo, clima laboral y posventas QR',
                'target' => 100,
                'unit' => '%',
                'stage' => 'D. Gestión de Información y Coordinación',
                'weight' => 20,
                'incidence' => 20,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Tiempo Promedio de Resolución de Solicitudes Técnicas Dashboard',
                        'definition' => 'Rapidez y efectividad en dar respuesta a dudas técnicas y tareas operativas asignadas en el Dashboard. Meta: ≤ 2 días hábiles.',
                        'formula' => 'Tiempo_Promedio_Resolucion_Dias',
                        'unit' => 'días',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Tiempo_Promedio_Resolucion_Dias', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 2.0, 'qualification' => '≤ 2 días hábiles — Respuesta ágil y gestión oportuna', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable (Amarillo)', 'min_value' => 2.01, 'max_value' => 4.0, 'qualification' => '2.1 – 4 días hábiles — Carga operativa con retrasos leves', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Crítico (Rojo)', 'min_value' => 4.01, 'max_value' => 1000, 'qualification' => '> 4 días hábiles — Cuellos de botella en atención técnica', 'color' => 'deficient', 'score' => 20],
                        ],
                    ],
                    [
                        'name' => 'Puntualidad en la Entrega de Informes de Acta de comité',
                        'definition' => 'Cumplimiento en fecha (jueves) y formato establecido para el envío de informes de avance y actas de comité. Meta: 100% de entregas puntuales.',
                        'formula' => '(Informes_Entregados_ATiempo / Total_Informes_Programados) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Informes_Entregados_ATiempo', 'value' => 0],
                            ['name' => 'Total_Informes_Programados', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Entregas puntuales en todos los cortes establecidos', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Parcial (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99% — Entrega con retraso menor', 'color' => 'acceptable', 'score' => 75],
                            ['level' => 'Incumplimiento (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Incumplimiento en reporte de actas', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Contribución al Ambiente Laboral (Evaluación 360° / Feedback Interno)',
                        'definition' => 'Evaluación de competencias de liderazgo, trabajo en equipo, comunicación y coordinación técnica en obra. Meta: ≥ 4.5 / 5.0.',
                        'formula' => 'Puntaje_Evaluacion_360',
                        'unit' => 'pts',
                        'parameters' => [
                            ['name' => 'Puntaje_Evaluacion_360', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Sobresaliente (Verde)', 'min_value' => 4.5, 'max_value' => 5.0, 'qualification' => '≥ 4.5 pts — Liderazgo sobresaliente y articulación de equipos', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable (Amarillo)', 'min_value' => 3.5, 'max_value' => 4.49, 'qualification' => '3.5 – 4.49 pts — Buen desempeño técnico con oportunidad en habilidades blandas', 'color' => 'acceptable', 'score' => 89],
                            ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 3.49, 'qualification' => '< 3.5 pts — Desalineación en clima y relaciones de trabajo', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Número de Quejas de Clientes por Calidad de Acabados (Post-entrega)',
                        'definition' => 'Control de incidencias y solicitudes posventas radicadas a través de códigos QR Inverconstrucción y QR Croma tras entrega formal. Meta: 0 quejas no resueltas en plazo.',
                        'formula' => 'Numero_Quejas_Sin_Resolver',
                        'unit' => 'quejas',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Numero_Quejas_Sin_Resolver', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 0.0, 'qualification' => '0 quejas no resueltas — Alta satisfacción y calidad de entrega', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Moderado (Amarillo)', 'min_value' => 1.0, 'max_value' => 2.0, 'qualification' => '1 – 2 incidencias atendidas dentro de tiempos de garantía', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Crítico (Rojo)', 'min_value' => 2.01, 'max_value' => 1000, 'qualification' => '> 2 quejas o incumplimiento de tiempos de garantía', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ]
        ];

        foreach ($kpisAcabados as $kpiData) {
            $userAcabados->kpis()->updateOrCreate(
                ['name' => $kpiData['name']],
                $kpiData
            );
        }
    }
}
