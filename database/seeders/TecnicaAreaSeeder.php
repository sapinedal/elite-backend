<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Http\Modules\Users\Models\User;
use App\Http\Modules\Plantillas\Models\KPI;
use App\Http\Modules\Configuracion\Models\Area;
use App\Http\Modules\Configuracion\Models\Position;

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
                            ['name' => 'Recepciones_Sin_Discrepancias', 'value' => 4],
                            ['name' => 'Total_Recepciones', 'value' => 106],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo', 'min_value' => 99, 'max_value' => 1000, 'qualification' => 'Meta cumplida — desempeño óptimo (≥ 99%)', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Bajo Riesgo', 'min_value' => 95, 'max_value' => 98.99, 'qualification' => 'Bajo riesgo — requiere mejora para mantener el estándar (95% - 98.99%)', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 94.99, 'qualification' => 'Meta incumplida — impacto directo en calidad y costos (< 95%)', 'color' => 'deficient', 'score' => 0],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Nº Factura', 'Discrepancia / Observación Identificada', 'Estado'],
                            'rows' => [
                                ['2640', 'Se repite cobro en la factura 2638', 'Rechazada / NC Solicitada'],
                                ['2644', 'Cobro de un insumo ya facturado previamente', 'Rechazada / NC Solicitada'],
                                ['2655', 'El valor unitario del insumo está errado', 'Ajustada'],
                                ['2671', 'Cobro duplicado de insumo ya facturado', 'Rechazada / NC Solicitada'],
                            ]
                        ]
                    ],
                    [
                        'name' => 'Tiempo Promedio de Despacho de Materiales (desde solicitud)',
                        'definition' => 'Evaluar la agilidad y eficiencia en la entrega de materiales a la obra desde la solicitud hasta la entrega. Metas estándar: Almacén ≤ 10 min, Patios ≤ 20 min, Bodegas satelitales ≤ 20 min.',
                        'formula' => 'Promedio de cumplimiento en tiempos por tipo de despacho',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Tiempo_Promedio_Almacen_Min', 'value' => 3],
                            ['name' => 'Meta_Almacen_Min', 'value' => 10],
                            ['name' => 'Tiempo_Promedio_Patios_Min', 'value' => 14],
                            ['name' => 'Meta_Patios_Min', 'value' => 20],
                            ['name' => 'Tiempo_Promedio_Satelitales_Min', 'value' => 17],
                            ['name' => 'Meta_Satelitales_Min', 'value' => 20],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 100, 'max_value' => 1000, 'qualification' => 'Cumple o mejora meta estándar de tiempos', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable / Riesgo', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => 'Hasta 10% por encima de la meta — Riesgo operativo a intervenir', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '> 10% por encima de la meta — Retrasos críticos que afectan obra', 'color' => 'deficient', 'score' => 0],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Tipo de Despacho', 'Muestreo / Frecuencia', 'Tiempo Registrado', 'Meta Estándar', '% Cumplimiento'],
                            'rows' => [
                                ['Almacén', '8 muestras aleatorias en el mes', '3 min', '≤ 10 min', '100%'],
                                ['Patios (Madera y Bloque)', '4 muestras aleatorias en el mes', '14 min', '≤ 20 min', '100%'],
                                ['Bodegas Satelitales (Cemento, Pega, Enchape)', '4 bodegas satelitales muestreadas', '17 min', '≤ 20 min', '100%'],
                            ]
                        ]
                    ],
                    [
                        'name' => 'Índice de Rotación de Inventario (Almacén)',
                        'definition' => 'Mide la eficiencia de salida de materiales (menor tiempo de almacenamiento). Rotación = Material despachado / Inventario promedio.',
                        'formula' => 'Rotacion_Veces_Mes',
                        'unit' => 'veces/mes',
                        'parameters' => [
                            ['name' => 'Rotacion_Veces_Mes', 'value' => 1.0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo', 'min_value' => 1.0, 'max_value' => 999, 'qualification' => 'Rotación ágil (≥ 1.0 veces/mes) — Desempeño 100%', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Bueno', 'min_value' => 0.4, 'max_value' => 0.999, 'qualification' => 'Rotación moderada (≥ 0.4 y < 1.0) — Desempeño 80%', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Aceptable', 'min_value' => 0.2, 'max_value' => 0.399, 'qualification' => 'Baja rotación (≥ 0.2 y < 0.4) — Desempeño 50%', 'color' => 'at_risk', 'score' => 50],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 0.199, 'qualification' => 'Inventario inmovilizado (< 0.2) — Desempeño 0%', 'color' => 'deficient', 'score' => 0],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Insumo / Torre', 'Inv. Inicial', 'Inv. Final', '% Variación', 'Rotación (veces/mes)'],
                            'rows' => [
                                ['Cemento (Torre 2)', '210', '47', '-77.62%', '1.27'],
                                ['Paraguas (Torre 2)', '8', '5', '-37.50%', '0.46'],
                                ['Cerámica Baños (Torre 2)', '1,146.6', '784.8', '-31.55%', '0.37'],
                                ['Cerámica Pto Fijo (Torre 2)', '1,362.6', '2,655', '+94.85%', '-0.64'],
                                ['Pega (Torre 2)', '19,560', '37,360', '+91.00%', '-0.63'],
                                ['Desmoldante (Torre 1)', '55', '55', '0.00%', '0.00'],
                            ]
                        ]
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
                        'parameters' => [
                            ['name' => 'Valor_Absoluto_Diferencias', 'value' => 6700000],
                            ['name' => 'Valor_Total_Inventario', 'value' => 370047516],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo', 'min_value' => 0, 'max_value' => 1.0, 'qualification' => 'IDI < 1% del valor total del inventario — Exactitud sobresaliente', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable', 'min_value' => 1.01, 'max_value' => 2.5, 'qualification' => 'IDI 1% – 2.5% — Desviación moderada bajo control', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 2.51, 'max_value' => 1000, 'qualification' => 'IDI > 2.5% — Desviación alta física vs. sistema', 'color' => 'deficient', 'score' => 0],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Torre / Concepto', 'Inventario Total', 'Valor Diferencia / Reintegro', '% IDI'],
                            'rows' => [
                                ['Faltantes Torre 1', '$ 179,484,654.96', '$ 10,833,966.57', '6.04%'],
                                ['Sobrantes Torre 1', '$ 179,484,654.96', '$ 19,973.50', '0.01%'],
                                ['Faltantes Torre 2', '$ 190,562,861.59', '$ 2,362,616.49', '1.24%'],
                                ['Sobrantes Torre 2', '$ 190,562,861.59', '$ 0.00', '0.00%'],
                                ['Promedio Global IDI', '$ 370,047,516.55', '$ 13,216,556.56', '1.82%'],
                            ]
                        ]
                    ],
                    [
                        'name' => 'Velocidad de Registro de Entradas y Salidas en Sistema (≤ 8h)',
                        'definition' => 'Garantiza que el 100% de los movimientos físicos queden incorporados en el sistema ≤ 8 horas posteriores a la operación física.',
                        'formula' => '(Movimientos_Registrados_a_Tiempo / Total_Movimientos) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Movimientos_Registrados_a_Tiempo', 'value' => 100],
                            ['name' => 'Total_Movimientos', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Meta Cumplida', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% de movimientos registrados ≤ 8 horas — Registro oportuno y control total', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Parcial', 'min_value' => 80, 'max_value' => 99.99, 'qualification' => '80% – 99% de movimientos registrados ≤ 8 horas — Retrasos que pueden afectar exactitud', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Incumplida', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% registrados ≤ 8 horas — Alto riesgo de descuadres físico vs sistema', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Porcentaje de Documentación de Almacén Archivada Correctamente',
                        'definition' => 'Organización y accesibilidad de soportes (OC, vales, remisiones). Meta: 100% archivada (física/digital) ≤ 24 horas después del movimiento.',
                        'formula' => '(Documentos_Archivados_a_Tiempo / Total_Documentos) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Documentos_Archivados_a_Tiempo', 'value' => 100],
                            ['name' => 'Total_Documentos', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Cumple', 'min_value' => 95, 'max_value' => 1000, 'qualification' => '≥ 95% — Gestión del archivo eficiente y al día', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'En Riesgo', 'min_value' => 80, 'max_value' => 94.99, 'qualification' => '80% – 94% — Retrasos puntuales que requieren control', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'No Cumple', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — Acumulación de documentos sin archivar', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Nº de Inventarios Físicos Cíclicos/Generales Realizados a Tiempo',
                        'definition' => 'Cumplimiento del programa de conteos físicos. Realizar dentro de los primeros 7 días de cada mes calendario.',
                        'formula' => '(Conteos_a_Tiempo / Conteos_Programados) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Conteos_a_Tiempo', 'value' => 1],
                            ['name' => 'Conteos_Programados', 'value' => 1],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo (Días 1–7)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => 'Días 1–7 — 100% Cumple plenamente la meta. Inventario confiable', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Parcial (Días 8–14)', 'min_value' => 50, 'max_value' => 99.99, 'qualification' => 'Días 8–14 — 50% Cumplimiento parcial. Riesgo moderado en conciliaciones', 'color' => 'acceptable', 'score' => 50],
                            ['level' => 'No Cumple (> Día 14)', 'min_value' => 0, 'max_value' => 49.99, 'qualification' => 'Después del día 14 — 0% No cumple. Alto riesgo de desviaciones', 'color' => 'deficient', 'score' => 0],
                        ],
                    ]
                ]
            ],
            // BLOQUE C: Mantenimiento y Seguridad (15%)
            [
                'name' => 'C. Mantenimiento y Seguridad',
                'description' => 'Cero incidentes de seguridad atribuibles al almacén y mantenimiento de altos estándares de orden y limpieza mediante auditorías periódicas.',
                'formula' => 'Puntuación combinada de seguridad y orden/aseo',
                'target' => 100,
                'unit' => '%',
                'stage' => 'C. Mantenimiento y Seguridad',
                'weight' => 15,
                'incidence' => 15,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Nº de Incidentes de Seguridad en Almacén (Atribuibles)',
                        'definition' => 'Registra eventos que ponen en riesgo la seguridad física o laboral por fallas, omisiones o negligencias atribuibles al personal del almacén. Meta: 0 incidentes.',
                        'formula' => 'Incidentes_Atribuibles',
                        'unit' => 'Incidentes',
                        'parameters' => [
                            ['name' => 'Incidentes_Atribuibles', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo', 'min_value' => 0, 'max_value' => 0, 'qualification' => '0 incidentes atribuibles — 100% de cumplimiento en seguridad', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'En Riesgo', 'min_value' => 1, 'max_value' => 1, 'qualification' => '1 incidente atribuible — Requiere investigación y plan de acción', 'color' => 'at_risk', 'score' => 50],
                            ['level' => 'Crítico', 'min_value' => 2, 'max_value' => 999, 'qualification' => '≥ 2 incidentes atribuibles — Incumplimiento crítico de seguridad', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Puntuación en Auditorías de Orden y Limpieza',
                        'definition' => 'Calidad del mantenimiento del espacio de trabajo en almacén y patios. Meta: ≥ 4.5 / 5.0 puntos.',
                        'formula' => 'Promedio_Auditorias_Orden_Limpieza',
                        'unit' => 'Puntos',
                        'parameters' => [
                            ['name' => 'Promedio_Auditorias_Orden_Limpieza', 'value' => 5.0],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 4.5, 'max_value' => 5.0, 'qualification' => 'Puntuación ≥ 4.5/5 — Espacio limpio, aseado y perfectamente organizado', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable', 'min_value' => 3.5, 'max_value' => 4.49, 'qualification' => 'Puntuación 3.5 – 4.4 — Requiere mejoras puntuales de orden', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 3.49, 'qualification' => 'Puntuación < 3.5 — Desorden y falta de aseo', 'color' => 'deficient', 'score' => 0],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Auditor / Inspector', 'Tipo de Auditoría', 'Observaciones', 'Calificación (1-5)'],
                            'rows' => [
                                ['Paula', 'Auditoría Orden y Aseo', 'Muy organizado y bien distribuido. Aseo profundo periódico adecuado.', '5.0'],
                                ['Mauricio', 'Auditoría Orden y Aseo', 'Calificación excelente. Apoyo en consecución de cotizaciones de proveedores.', '5.0'],
                            ]
                        ]
                    ]
                ]
            ],
            // BLOQUE D: Eficiencia en Procesos (15%)
            [
                'name' => 'D. Eficiencia en Procesos',
                'description' => 'Evalúa los tiempos de causación contable, emisión de documentos soporte, resolución de discrepancias DIAN, ambiente laboral y amortización de anticipos.',
                'formula' => 'Promedio ponderado de causación, documentos soporte, conciliación DIAN, clima y anticipos',
                'target' => 100,
                'unit' => '%',
                'stage' => 'D. Eficiencia en Procesos',
                'weight' => 15,
                'incidence' => 15,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Tiempo Promedio de Causación de Facturas',
                        'definition' => 'Tiempo desde recepción hasta registro contable. Meta estándar: ≤ 3 días hábiles.',
                        'formula' => 'Dias_Promedio_Causacion',
                        'unit' => 'Días',
                        'parameters' => [
                            ['name' => 'Dias_Promedio_Causacion', 'value' => 1.5],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo', 'min_value' => 0, 'max_value' => 3.0, 'qualification' => '≤ 3 días hábiles — Causación oportuna sin demoras', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable', 'min_value' => 3.01, 'max_value' => 5.0, 'qualification' => '3.1 – 5.0 días hábiles — Retraso moderado en procesamiento contable', 'color' => 'acceptable', 'score' => 70],
                            ['level' => 'Deficiente', 'min_value' => 5.01, 'max_value' => 999, 'qualification' => '> 5 días hábiles — Ineficiencia en causación de facturas', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Tiempo Promedio de Emisión de Documentos Soporte',
                        'definition' => 'Rapidez en generación de documentos soporte electrónicos. Meta: ≤ 48 horas.',
                        'formula' => 'Horas_Promedio_Emision',
                        'unit' => 'Horas',
                        'parameters' => [
                            ['name' => 'Horas_Promedio_Emision', 'value' => 24],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 0, 'max_value' => 48, 'qualification' => '≤ 48 horas — Emisión rápida y en norma', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable', 'min_value' => 48.01, 'max_value' => 72, 'qualification' => '49 – 72 horas — Emisión con ligera demora', 'color' => 'acceptable', 'score' => 75],
                            ['level' => 'Deficiente', 'min_value' => 72.01, 'max_value' => 9999, 'qualification' => '> 72 horas — Retraso crítico en documentos soporte', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Nº de Discrepancias Identificadas y Resueltas en Conciliación DIAN',
                        'definition' => 'Eficiencia en identificar y resolver inconsistencias con DIAN en ≤ 48 horas por la obra.',
                        'formula' => '(Discrepancias_Resueltas / Discrepancias_Detectadas) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Discrepancias_Resueltas', 'value' => 4],
                            ['name' => 'Discrepancias_Detectadas', 'value' => 4],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% identificadas y resueltas a tiempo (ej: Notas Crédito tramitadas)', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Parcial', 'min_value' => 80, 'max_value' => 99.99, 'qualification' => '80% – 99% resueltas — Casos pendientes de seguimiento', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% resueltas — Riesgo fiscal / tributario', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Contribución al Ambiente Laboral (Evaluación 360°)',
                        'definition' => 'Evaluación de colaboración, actitud y retroalimentación positiva de pares y líderes (escala 1 a 5).',
                        'formula' => 'Calificacion_360',
                        'unit' => 'Puntos',
                        'parameters' => [
                            ['name' => 'Calificacion_360', 'value' => 4.88],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Excelente', 'min_value' => 4.5, 'max_value' => 5.0, 'qualification' => 'Retroalimentación altamente positiva (≥ 4.5/5)', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable', 'min_value' => 3.5, 'max_value' => 4.49, 'qualification' => 'Desempeño adecuado en trabajo en equipo (3.5 – 4.49)', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 3.49, 'qualification' => 'Baja calificación en evaluación 360 (< 3.5)', 'color' => 'deficient', 'score' => 0],
                        ],
                    ],
                    [
                        'name' => 'Porcentaje de Anticipos Legalizados y Amortizados Correctamente',
                        'definition' => 'Seguimiento y precisión en la gestión de anticipos entregados. Legalizados al final de cada mes.',
                        'formula' => '(Anticipos_Legalizados / Anticipos_Entregados) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Anticipos_Legalizados', 'value' => 5],
                            ['name' => 'Anticipos_Entregados', 'value' => 5],
                        ],
                        'conditional_goals' => [
                            ['level' => 'Óptimo', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% de anticipos legalizados y amortizados al mes', 'color' => 'optimal', 'score' => 100],
                            ['level' => 'Aceptable', 'min_value' => 80, 'max_value' => 99.99, 'qualification' => '80% – 99% legalizados — Anticipos pendientes de cierre', 'color' => 'acceptable', 'score' => 80],
                            ['level' => 'Deficiente', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — Riesgo financiero en amortización', 'color' => 'deficient', 'score' => 0],
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
            // =========================================================================
            // BLOQUE A: Supervisión Técnica y Ejecución de Estructura y urbanismo (40%)
            // =========================================================================
            [
                'name' => 'A. Supervisión Técnica y Ejecución de Estructura y urbanismo',
                'description' => 'Mide la adherencia al programa de avance físico de obra gruesa y urbanismo, el control de errores críticos antes de supervisión externa y la eficiencia en el cubicaje de concreto y acero.',
                'formula' => 'Promedio de cumplimiento de cronograma, cero errores críticos y cubicación en rango',
                'target' => 100,
                'unit' => '%',
                'stage' => 'A. Supervisión Técnica y Ejecución de Estructura y urbanismo',
                'weight' => 40,
                'incidence' => 40,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Porcentaje de Cumplimiento del Programa Mensual de Estructura y urbanismo',
                        'definition' => 'Mide la adherencia a la planificación detallada de la obra gruesa y frentes de urbanismo.',
                        'formula' => '(Actividades_Completadas / Actividades_Programadas) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Actividades_Completadas', 'value' => 100],
                            ['name' => 'Actividades_Programadas', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Cumplimiento (Verde)',
                                'min_value' => 95,
                                'max_value' => 1000,
                                'qualification' => '≥ 95% — Cumplimiento y desempeño óptimo en la secuencia constructiva',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'En Riesgo / Parcial (Amarillo)',
                                'min_value' => 80,
                                'max_value' => 94.99,
                                'qualification' => '80% – 94% — En riesgo / Parcialmente cumple, requiere seguimiento semanal',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Incumplimiento Crítico (Rojo)',
                                'min_value' => 0,
                                'max_value' => 79.99,
                                'qualification' => '< 80% — Incumplimiento crítico, activar plan de recuperación inmediata',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Frente / Actividad', 'Fecha Inicio', 'Avance Teórico', 'Avance Real', 'Adelanto / Retraso', 'Estado'],
                            'rows' => [
                                ['Tanque', '23/07/2025', '80%', '80%', '-10 días', 'Recuperado'],
                                ['Acero de Refuerzo Estructura T2', '30/03/2026', '50%', '100%', '+70.00% adelanto', 'Óptimo'],
                                ['Malla Electrosoldada T2', '30/03/2026', '50%', '100%', '+70.00% adelanto', 'Óptimo'],
                                ['Muros 15 y 20 (CIM - P5)', '30/03/2026', '100%', '100%', '0.00% al día', 'Cumplido'],
                                ['Muros 15 y 20 (P6 - P10)', '12/05/2026', '100%', '100%', '0.00% al día', 'Cumplido'],
                                ['Muros 15 y 20 (P11 - P15)', '25/06/2026', '78%', '100%', '+10.53% adelanto', 'Óptimo'],
                                ['Losa de Entrepiso T2', '30/03/2026', '73%', '100%', '+35.10% adelanto', 'Óptimo'],
                                ['Escaleras T2', '24/04/2026', '61%', '85%', '+30.24% adelanto', 'Óptimo'],
                                ['Redes Hidrosanitarias', '30/03/2026', '56%', '65%', '+15.48% adelanto', 'Óptimo'],
                                ['Redes Eléctricas Tubería', '30/03/2026', '49%', '65%', '+30.72% adelanto', 'Óptimo'],
                                ['Avance General Estructura', '30/03/2026', '70%', '98%', '+41.16% adelanto (20 días antes)', 'Finalizado con Éxito'],
                            ]
                        ]
                    ],
                    [
                        'name' => 'Número de Errores Críticos de Ejecución Estructural Detectados Internamente',
                        'definition' => 'Cuantifica la calidad del control interno antes de la supervisión externa. Mide errores críticos sin cerrar o sin plan de acción.',
                        'formula' => 'Errores_Criticos_Sin_Cerrar',
                        'unit' => 'Errores',
                        'parameters' => [
                            ['name' => 'Errores_Criticos_Sin_Cerrar', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Óptimo (Verde)',
                                'min_value' => 0,
                                'max_value' => 0,
                                'qualification' => '0 errores críticos sin cerrar — Control técnico interno efectivo',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Incumplimiento (Rojo)',
                                'min_value' => 1,
                                'max_value' => 999,
                                'qualification' => '≥ 1 error crítico sin cerrar o sin plan de acción',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Elemento / Frente', 'Tipo de Hallazgo', 'Gestión Técnica Realizada', 'Resolución / Cierre'],
                            'rows' => [
                                ['Losa Cubierta Portería', 'Observaciones al armado de acero', 'Notificación formal previa y constancia de responsabilidad contratista', 'Cerrado / Trazabilidad completa'],
                                ['Salida Eléctrica Borde', 'Ubicación en salpicadero de cocina', 'Consulta al calculista IPI para ubicar en recubrimiento exterior', 'Aprobado por calculista y liberado'],
                                ['Muros 101 y 104 (Nivel 1)', 'Cilindros no alcanzaron f\'c esperado a 14/28 días', 'Extracción de testigos, ultrasonido (3,849 m/s) y esclerometría (37.7) vs patrón', 'Liberado por Interventoría sin daño destructivo'],
                                ['Muro 20cm Balcón', 'Aval de anclaje a muro estructural', 'Alternativa de aislamiento con icopor y dovela continua', 'Aprobado por Supervisión Técnica'],
                            ]
                        ]
                    ],
                    [
                        'name' => 'Porcentaje de Cubicación de Materiales de Estructura en Rango',
                        'definition' => 'Mide la eficiencia en el uso de concreto y acero. Desviación entre cubicaje real y cubicaje de diseño (Meta: Desviación ≤ 5%).',
                        'formula' => '((Cubicaje_Real - Cubicaje_Diseno) / Cubicaje_Diseno) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Desviacion_Cubicaje_Porcentaje', 'value' => 2.5],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Cumple (Verde)',
                                'min_value' => 0,
                                'max_value' => 5.0,
                                'qualification' => '0% – 5% — Dentro del rango permitido, cubicaje controlado y eficiente',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'En Riesgo (Amarillo)',
                                'min_value' => 5.01,
                                'max_value' => 10.0,
                                'qualification' => '5.01% – 10% — En riesgo, requiere revisión y ajuste en vaciados',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Incumplimiento (Rojo)',
                                'min_value' => 10.01,
                                'max_value' => 1000,
                                'qualification' => '> 10% — Incumplimiento, alto desperdicio o desvío',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Elemento / Estructura', 'Tipo Material', '% Desperdicio Registrado', 'Meta Estándar', 'Calificación'],
                            'rows' => [
                                ['Pilas Tanque', 'Concreto', '1.00% – 2.42%', '≤ 5%', '100% (Verde)'],
                                ['Muros Tanque', 'Concreto', '0.99%', '≤ 5%', '100% (Verde)'],
                                ['Losa Aérea Tanque', 'Concreto', '1.00%', '≤ 5%', '100% (Verde)'],
                                ['Estructura Torre 2', 'Concreto Muros/Losas', '15.16% – 16.05%', '≤ 5%', 'Requiere control por fluidez en pata de muro'],
                                ['Estructura Torre 2', 'Acero de Refuerzo', 'Control de pedidos vs salidas', '≤ 5%', 'Seguimiento continuo en almacén'],
                            ]
                        ]
                    ]
                ]
            ],

            // =========================================================================
            // BLOQUE B: Control de Calidad y Cumplimiento Normativo (30%)
            // =========================================================================
            [
                'name' => 'B. Control de Calidad y Cumplimiento Normativo',
                'description' => 'Evalúa los resultados conformes en ensayos de concreto, acero y suelos, el control estricto de no conformidades y las auditorías de seguridad SG-SST.',
                'formula' => 'Promedio de conformidad de ensayos, cero no conformidades y puntuación SG-SST',
                'target' => 100,
                'unit' => '%',
                'stage' => 'B. Control de Calidad y Cumplimiento Normativo',
                'weight' => 30,
                'incidence' => 30,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => '% Cumplimiento Ensayos de Calidad con Resultados Conformes',
                        'definition' => 'Evalúa la calidad de materiales y procesos (concreto, acero, compactación de suelos/vías). Meta: 100% de ensayos conformes.',
                        'formula' => '(Ensayos_Conformes / Total_Ensayos) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Ensayos_Conformes', 'value' => 100],
                            ['name' => 'Total_Ensayos', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Óptimo (Verde)',
                                'min_value' => 100,
                                'max_value' => 1000,
                                'qualification' => '100% — Todos los ensayos conformes con especificaciones técnicas',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Aceptable (Amarillo)',
                                'min_value' => 95,
                                'max_value' => 99.99,
                                'qualification' => '95% – 99% — Conformidad alta, revisión de novedades de laboratorio',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Riesgo (Rojo)',
                                'min_value' => 0,
                                'max_value' => 94.99,
                                'qualification' => '< 95% — Riesgo de calidad estructural',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Ensayo / Frente', 'Laboratorio / Muestreo', '% Conformidad', 'Conclusión Técnica'],
                            'rows' => [
                                ['Pilas y Vigas Tanque', 'Concrelab / Vitaa', '100% – 104%', 'Ensayos conformes superando f\'c de diseño'],
                                ['Compactación Equipos y Vías', 'Densidad Proctor (~2260 kg/m³)', '99% – 100%', 'Suelo en máxima capacidad de compactación'],
                                ['Muestras Concreto Estructura T2', 'Laboratorio Concrelab', '108% – 121%', 'Resistencias nominales conformes a 28/56 días'],
                            ]
                        ]
                    ],
                    [
                        'name' => 'Número de No Conformidades en Calidad de Estructura o urbanismo',
                        'definition' => 'Cuantifica problemas de calidad detectados interna y externamente. Meta: Internas < 1/mes, Externas = 0.',
                        'formula' => 'No_Conformidades_Detectadas',
                        'unit' => 'No Conformidades',
                        'parameters' => [
                            ['name' => 'No_Conformidades_Detectadas', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Cumple (Verde)',
                                'min_value' => 0,
                                'max_value' => 0,
                                'qualification' => '0 no conformidades (internas y externas) — Cumple plenamente',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Seguimiento (Amarillo)',
                                'min_value' => 1,
                                'max_value' => 1,
                                'qualification' => '1 no conformidad interna con plan correctivo documentado',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Incumplimiento (Rojo)',
                                'min_value' => 2,
                                'max_value' => 999,
                                'qualification' => '≥ 1 no conformidad externa o ≥ 2 internas',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Periodo / Frente', 'No Conformidad', 'Acción Correctiva', 'Impacto en Calificación'],
                            'rows' => [
                                ['Losa Portería', 'Armado de acero observado', 'Advertencia formal y trazabilidad contractual preventiva', '100% — Gestión preventiva eficaz'],
                                ['Resistencia Tanque', 'Resistencia preliminar intermedia', 'Seguimiento a núcleos a 28/56 días (>136% f\'c)', 'Liberado'],
                                ['Formaleta Torre 2', 'Desplazamiento 1 cm por residuo en superficie', 'Protocolo de limpieza e inspección pre-vaciado', '80% — En seguimiento'],
                                ['Cierre Estructura', 'Sin novedades al 28 de agosto', 'Finalización con 20 días de adelanto', '100% (Verde)'],
                            ]
                        ]
                    ],
                    [
                        'name' => 'Puntuación en Auditorías de Seguridad (SG-SST)',
                        'definition' => 'Evalúa la adherencia a normas de seguridad y salud en el trabajo en frentes de estructura y urbanismo. Meta: > 95%.',
                        'formula' => '(Puntos_Obtenidos / Puntos_Posibles) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Puntuacion_SST_Inverconstruccion', 'value' => 99.5],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Excelente (Verde)',
                                'min_value' => 95,
                                'max_value' => 1000,
                                'qualification' => '≥ 95% — Cumple plenamente, alta adherencia preventiva',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'En Riesgo (Amarillo)',
                                'min_value' => 90,
                                'max_value' => 94.99,
                                'qualification' => '90% – 94% — En riesgo, requiere acciones correctivas',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Incumplimiento (Rojo)',
                                'min_value' => 0,
                                'max_value' => 89.99,
                                'qualification' => '< 90% — Incumplimiento crítico de seguridad',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Empresa / Contratista', 'Puntuación SG-SST', 'Estado', 'Observaciones'],
                            'rows' => [
                                ['INVERCONSTRUCCIÓN', '99.50%', '🟢 Verde (Excelente)', 'Cultura preventiva sólida en frente de estructura'],
                                ['EMELECT', '88.75% – 91.00%', '🟡 En seguimiento', 'Pendiente cierre de acciones en instalaciones'],
                                ['IHC', '87.25%', '🟡 En seguimiento', 'Plan de mejora en señalización'],
                                ['IASS', '65.00%', '🔴 Requiere intervención', 'Plan de choque en estándares de seguridad'],
                            ]
                        ]
                    ]
                ]
            ],

            // =========================================================================
            // BLOQUE C: Gestión de Información y Coordinación (15%)
            // =========================================================================
            [
                'name' => 'C. Gestión de Información y Coordinación',
                'description' => 'Mide la agilidad en resolución de consultas técnicas, puntualidad en bitácora de obra, contribución al clima laboral y orden en la documentación técnica.',
                'formula' => 'Promedio de atención técnica, bitácora al día, evaluación 360 y archivo Drive',
                'target' => 100,
                'unit' => '%',
                'stage' => 'C. Gestión de Información y Coordinación',
                'weight' => 15,
                'incidence' => 15,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Tiempo Promedio de Resolución de Dudas Técnicas',
                        'definition' => 'Mide la agilidad en respuestas de diseño / especialistas y atención a tareas técnicas en obra. Meta: ≤ 24 horas hábiles.',
                        'formula' => '(Solicitudes_a_Tiempo / Total_Solicitudes) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Solicitudes_a_Tiempo', 'value' => 21],
                            ['name' => 'Total_Solicitudes', 'value' => 24],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Óptimo (Verde)',
                                'min_value' => 90,
                                'max_value' => 1000,
                                'qualification' => '≥ 90% resueltas en ≤ 24 horas — Cumplimiento óptimo',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Aceptable (Amarillo)',
                                'min_value' => 70,
                                'max_value' => 89.99,
                                'qualification' => '70% – 89% resueltas en plazo — Cumplimiento aceptable, requiere atención',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Crítico (Rojo)',
                                'min_value' => 0,
                                'max_value' => 69.99,
                                'qualification' => '< 70% resueltas — Incumplimiento crítico, requiere plan de mejora',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Periodo / Dashboard', 'Total Tareas / Dudas', 'Completadas', 'En Progreso / Pendientes', '% Cumplimiento'],
                            'rows' => [
                                ['Dashboard Inicial', '20 tareas registradas', '16 completadas', '4 por hacer', '100%'],
                                ['Dashboard Intermedio', '31 solicitudes', '27 resueltas', '3 en progreso, 1 pendiente', '100%'],
                                ['Dashboard Consolidado', '118 tareas', '112 completadas', '4 en progreso, 2 por hacer', '100%'],
                                ['Dashboard Reciente', '24 solicitudes', '21 completadas', '3 en progreso', '100%'],
                            ]
                        ]
                    ],
                    [
                        'name' => 'Puntualidad en Entrega de Informes de Avance - Bitácora de obra',
                        'definition' => 'Mide la disciplina en reportes de avance de obra y actualización continua de la bitácora física y digital.',
                        'formula' => '(Informes_a_Tiempo / Total_Informes) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Informes_a_Tiempo', 'value' => 100],
                            ['name' => 'Total_Informes', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Total (Verde)',
                                'min_value' => 100,
                                'max_value' => 1000,
                                'qualification' => '100% de informes entregados a tiempo — Disciplina documental ejemplar',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Buen Cumplimiento (Amarillo)',
                                'min_value' => 90,
                                'max_value' => 99.99,
                                'qualification' => '90% – 99% — Buen cumplimiento, pero requiere mejorar para llegar al estándar',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Incumplimiento (Rojo)',
                                'min_value' => 0,
                                'max_value' => 89.99,
                                'qualification' => '< 90% — Incumplimiento significativo, bitácora desactualizada',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                    ],
                    [
                        'name' => 'Contribución al Ambiente Laboral (Evaluación 360°)',
                        'definition' => 'Evaluación de colaboración, actitud y liderazgo operativo en obra (escala 1 a 5).',
                        'formula' => 'Puntuacion_360',
                        'unit' => 'Puntos',
                        'parameters' => [
                            ['name' => 'Puntuacion_360', 'value' => 4.94],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Excelente (Verde)',
                                'min_value' => 4.5,
                                'max_value' => 5.0,
                                'qualification' => '≥ 4.5 / 5.0 — Liderazgo sobresaliente, cooperación y excelente clima de equipo',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Aceptable (Amarillo)',
                                'min_value' => 3.5,
                                'max_value' => 4.49,
                                'qualification' => '3.5 – 4.49 — Buen desempeño en trabajo colaborativo',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Deficiente (Rojo)',
                                'min_value' => 0,
                                'max_value' => 3.49,
                                'qualification' => '< 3.5 — Oportunidades en comunicación y trabajo en equipo',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Evaluador / Periodo', 'Puntuación (1-5)', 'Fortalezas Destacadas', 'Oportunidades de Mejora'],
                            'rows' => [
                                ['Evaluación Inicial (2025)', '4.97 / 5.00', 'Compromiso, rápido aprendizaje, actitud colaborativa y cooperación con cuadrillas', 'Breves espacios de feedback diario y formalización de directrices'],
                                ['Evaluación Junio 2026', '4.94 / 5.00', 'Sólidos conocimientos técnicos, liderazgo, organización y generosidad al compartir saberes', 'Receptividad continua a solicitudes del equipo de obra'],
                            ]
                        ]
                    ],
                    [
                        'name' => '% Documentación Técnica Archivada y Accesible',
                        'definition' => 'Evalúa la organización y accesibilidad de archivos técnicos en Drive y carpetas oficiales del proyecto (planos, cortes, contratos, especificaciones).',
                        'formula' => '(Docs_Archivados_Correctamente / Total_Docs) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Docs_Archivados_Correctamente', 'value' => 100],
                            ['name' => 'Total_Docs', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Óptimo (Verde)',
                                'min_value' => 100,
                                'max_value' => 1000,
                                'qualification' => '100% — Organización y accesibilidad óptima en rutas oficiales',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Adecuada (Amarillo)',
                                'min_value' => 90,
                                'max_value' => 99.99,
                                'qualification' => '90% – 99% — Gestión adecuada, con leves oportunidades de orden',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'No Cumple (Rojo)',
                                'min_value' => 0,
                                'max_value' => 89.99,
                                'qualification' => '< 90% — Desorganización o documentación en rutas personales',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                    ]
                ]
            ],

            // =========================================================================
            // BLOQUE D: Control Presupuestal y Administrativo (15%)
            // =========================================================================
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
                            ['name' => 'Presupuesto_Aprobado_Estructura', 'value' => 22454184688],
                            ['name' => 'Costo_Real_Proyectado', 'value' => 22381941286],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Óptimo (Verde)',
                                'min_value' => 0,
                                'max_value' => 2.0,
                                'qualification' => '0% – 2% — Control presupuestal óptimo, dentro de la meta y P&G positivo',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Alerta (Amarillo)',
                                'min_value' => 2.01,
                                'max_value' => 5.0,
                                'qualification' => '2.01% – 5% — Alerta: desviación moderada, requiere seguimiento de costos',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Crítico (Rojo)',
                                'min_value' => 5.01,
                                'max_value' => 1000,
                                'qualification' => '> 5% — Desviación crítica, sobrecostos en obra',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Fase / Elemento', 'Presupuesto Aprobado', 'Proyección / Costo Real', 'P&G / Margen', '% Desviación'],
                            'rows' => [
                                ['Urbanismo Inicial', '$ 6,919,732,896', '$ 6,919,732,896', 'Equilibrado', '0.00%'],
                                ['Urbanismo Actualizado', '$ 7,650,464,996', '$ 7,650,464,996', 'Equilibrado', '0.00%'],
                                ['Estructura Torre 2 (Corte 1)', '$ 22,000,974,415', '$ 22,000,974,415', '+$ 218,716,963 (Positivo)', '-1.00%'],
                                ['Estructura Torre 2 (Corte 2)', '$ 22,454,184,688', '$ 22,381,941,286', '+$ 70,814,726 (Positivo)', '-0.32%'],
                                ['Estructura Torre 2 (Cierre)', '$ 22,454,184,688', '$ 22,454,184,688', 'P&G Positivo / Sin sobrecostos', '0.00%'],
                            ]
                        ]
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
            // =========================================================================
            // BLOQUE A: Cumplimiento de Plazo y Programación (50%)
            // =========================================================================
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
                        'definition' => 'Mide el progreso real de la obra vs. cronograma planificado (Curva S) para Torre 1, Torre 2 y Urbanismo. Meta: Dentro de ±5% del avance programado.',
                        'formula' => '(Avance_Fisico_Real_Acumulado / Avance_Fisico_Programado_Acumulado) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Avance_Fisico_Real_Acumulado', 'value' => 99.24],
                            ['name' => 'Avance_Fisico_Programado_Acumulado', 'value' => 100.00],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Verde (Cumple Rango)',
                                'min_value' => 95,
                                'max_value' => 1000,
                                'qualification' => '≥ 95% del programado (diferencia ≤ ±5%) — Avance dentro de parámetros aceptables',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Amarillo (Seguimiento)',
                                'min_value' => 90,
                                'max_value' => 94.99,
                                'qualification' => '90% – 94.9% del programado — Ligeramente por debajo, requiere seguimiento',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Rojo (Incumplimiento)',
                                'min_value' => 0,
                                'max_value' => 89.99,
                                'qualification' => '< 90% del programado — Incumplimiento significativo, retraso que afecta hitos',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Frente de Obra', 'Avance Ejecutado', 'Avance Programado', '% Cumplimiento', 'Estado'],
                            'rows' => [
                                ['Torre 1 (Cierre Obra Gris y Acabados)', '99.24% – 99.90%', '100.00%', '99.24% – 99.90%', '🟢 Verde (En tolerancia ±5%)'],
                                ['Torre 2 (Fase Estructura Inicial)', '24.02%', '20.21%', '119.00%', '🟢 Verde (Adelantada)'],
                                ['Torre 2 (Fase Estructura Intermedia)', '37.09%', '32.70%', '113.00%', '🟢 Verde (Adelantada)'],
                                ['Torre 2 (Fase Estructura Avanzada)', '58.23%', '49.47%', '117.70%', '🟢 Verde (Adelantada +15 días)'],
                            ]
                        ]
                    ],
                    [
                        'name' => 'Cumplimiento de Hitos Clave',
                        'definition' => 'Porcentaje de hitos importantes entregados en la fecha planificada o antes (Fundaciones, Estructura, Acabados, Certificaciones). Meta: ≥ 95% a tiempo o con atraso ≤ 3 semanas.',
                        'formula' => '(Hitos_Cumplidos_a_Tiempo / Total_Hitos) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Hitos_Cumplidos_a_Tiempo', 'value' => 95],
                            ['name' => 'Total_Hitos', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Verde (A tiempo)',
                                'min_value' => 90,
                                'max_value' => 1000,
                                'qualification' => 'A tiempo hasta –3 semanas de atraso — Avance dentro de tolerancia técnica',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Amarillo / Naranja (Riesgo Moderado)',
                                'min_value' => 70,
                                'max_value' => 89.99,
                                'qualification' => '–4 a –8 semanas de atraso — Atraso moderado que requiere plan de impulso',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Rojo (Atraso Crítico)',
                                'min_value' => 0,
                                'max_value' => 69.99,
                                'qualification' => 'Más de –9 semanas de atraso — Atraso crítico en ruta crítica y entregas',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Hito / Frente', 'Fecha Programada', 'Fecha Ejecución / Estado', 'Desviación', 'Estado'],
                            'rows' => [
                                ['Estructura Torre 1', '15/08/2025', '29/05/2025', '78 días de adelanto', '🟢 Cumplido'],
                                ['Impermeabilizaciones y Cubierta', 'Programa Maestro', '100% ejecutado (manto y regatas)', '0 sem', '🟢 Cerrado'],
                                ['Obra Gris y Mampostería', 'Programa Maestro', '100% finalizado', '0 sem', '🟢 Cerrado'],
                                ['Sistema de Detección y Alarmas', 'Programa Maestro', '100% funcional en zonas comunes', '0 sem', '🟢 Cerrado'],
                                ['Acabados Interiores T1', 'Programa Maestro', '99% – 100% (remates en punto fijo)', '0 sem', '🟢 Cerrado'],
                                ['RCI y Bombeo', 'Programa Maestro', 'Pendiente presurización y motobombas', '–15 sem', '🟡 En recuperación'],
                                ['RETIE y Energización Trafo', 'Programa Maestro', 'Acometida 90%, trámite Servimeter/EPM', '–5 sem', '🟡 En gestión'],
                            ]
                        ]
                    ],
                    [
                        'name' => 'Tiempo Promedio de Resolución de Desvíos',
                        'definition' => 'Días promedio para corregir desviaciones críticas e implementar soluciones técnicas en obra. Meta: ≤ 5 días hábiles.',
                        'formula' => 'Dias_Promedio_Resolucion_Desvios',
                        'unit' => 'Días',
                        'parameters' => [
                            ['name' => 'Dias_Promedio_Resolucion_Desvios', 'value' => 1],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Óptimo (Verde)',
                                'min_value' => 0,
                                'max_value' => 5.0,
                                'qualification' => '≤ 5 días hábiles — Resolución oportuna y efectiva de contingencias',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Aceptable (Amarillo)',
                                'min_value' => 5.01,
                                'max_value' => 10.0,
                                'qualification' => '5.1 – 10 días hábiles — Demora moderada en gestión de solución',
                                'color' => 'acceptable',
                                'score' => 70
                            ],
                            [
                                'level' => 'Deficiente (Rojo)',
                                'min_value' => 10.01,
                                'max_value' => 999,
                                'qualification' => '> 10 días hábiles — Desvío crítico no resuelto en plazo',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Periodo', 'Desvío / Contingencia Identificada', 'Acción Técnica Implementada', 'Tiempo de Respuesta'],
                            'rows' => [
                                ['Agosto', 'Situación Tanque y vías internas', 'Reunión presencial y plan de acción de frentes', '0 días (En el mes)'],
                                ['Septiembre', 'Fibras y figuras entrega shut y tanque', 'Resolución inmediata en comité técnico', '0 días (En el mes)'],
                                ['Octubre', 'Derrumbe contra sala de ventas', 'Reunión con calculista IPI y diseño de contención', '1 día (Gestionado)'],
                                ['Diciembre - Enero', 'Afectación viviendas vecinas por vía interna', 'Actas de inspección, radicación SURA y diseño muro pilas', '1 día (Gestionado)'],
                                ['Marzo - Abril', 'Ejecución muro contención y cambio contratista', 'Finalización con Hidrodinámica e inicio con Quintana', '1 día (Gestionado)'],
                                ['Junio - Agosto', 'Resistencias tanque y propuesta Vitaa', 'Rechazo de impermeabilización y peritaje con Ing. Julio Garcés', 'En seguimiento'],
                            ]
                        ]
                    ]
                ]
            ],

            // =========================================================================
            // BLOQUE B: Control Presupuestal y Rentabilidad (20%)
            // =========================================================================
            [
                'name' => 'B. Control Presupuestal y Rentabilidad',
                'description' => 'Garantiza el control de costos frente al presupuesto aprobado en Torre 1, Torre 2 y Urbanismo, el margen de rentabilidad bruta (P&G positivo) y la productividad de la mano de obra.',
                'formula' => 'Promedio de desviación presupuestal, margen P&G y productividad de mano de obra',
                'target' => 100,
                'unit' => '%',
                'stage' => 'B. Control Presupuestal y Rentabilidad',
                'weight' => 20,
                'incidence' => 20,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Desviación Presupuestal del Proyecto (T1, T2 y Urbanismo)',
                        'definition' => 'Control de costos consolidado frente al presupuesto aprobado acumulado. Meta: Desviación ≤ 2%.',
                        'formula' => '((Costo_Real_Acumulado - Presupuesto_Aprobado_Acumulado) / Presupuesto_Aprobado_Acumulado) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Presupuesto_Aprobado_Total', 'value' => 50085554610],
                            ['name' => 'Costo_Real_Total', 'value' => 50492552251],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Óptimo (Verde)',
                                'min_value' => 0,
                                'max_value' => 2.0,
                                'qualification' => '0% – 2% — Control presupuestal óptimo, dentro de la meta',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Alerta (Amarillo)',
                                'min_value' => 2.01,
                                'max_value' => 5.0,
                                'qualification' => '> 2% – 5% — Alerta: desviación moderada, requiere seguimiento',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Crítico (Rojo)',
                                'min_value' => 5.01,
                                'max_value' => 1000,
                                'qualification' => '> 5% — Desviación crítica, sobrecostos que afectan rentabilidad',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Proyecto / Frente', 'Presupuesto Aprobado', 'Proyección / Costo Real', 'Variación / Ahorro', 'P&G Proyectado'],
                            'rows' => [
                                ['Torre 1', '$ 19,980,904,926', '$ 20,470,215,377', '-$ 489,310,451 (+2.45%)', '+$ 239,872,694 (Positivo)'],
                                ['Torre 2', '$ 22,454,184,688', '$ 22,371,871,878', '+$ 82,312,810 (-0.37%)', '+$ 218,716,963 (Positivo)'],
                                ['Urbanismo', '$ 7,650,464,996', '$ 7,650,464,996', '$ 0.00 (0.00%)', 'Equilibrado'],
                            ]
                        ]
                    ],
                    [
                        'name' => 'Rentabilidad Bruta de la Obra (Margen de Contribución P&G)',
                        'definition' => 'Mide la rentabilidad económica y margen de contribución positivo por frente de obra.',
                        'formula' => 'Margen_Contribucion_Total',
                        'unit' => '$',
                        'parameters' => [
                            ['name' => 'Margen_Contribucion_Total', 'value' => 458589657],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Óptimo (P&G Positivo)',
                                'min_value' => 100,
                                'max_value' => 1000,
                                'qualification' => 'P&G Positivo en todas las líneas analizadas — Viabilidad y rentabilidad asegurada',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Equilibrado',
                                'min_value' => 50,
                                'max_value' => 99.99,
                                'qualification' => 'Margen equilibrado en línea con lo proyectado',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Negativo (Pérdida)',
                                'min_value' => 0,
                                'max_value' => 49.99,
                                'qualification' => 'Margen negativo o sobrecosto no compensado',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Frente', 'Margen P&G Proyectado', 'Estado'],
                            'rows' => [
                                ['Torre 1', '$ 239,872,694.00', '🟢 P&G Positivo'],
                                ['Torre 2', '$ 218,716,963.00', '🟢 P&G Positivo'],
                                ['Urbanismo', '$ 0.00', '🟢 P&G Nivelado'],
                            ]
                        ]
                    ],
                    [
                        'name' => 'Índice de Productividad de Mano de Obra',
                        'definition' => 'Eficiencia del uso de mano de obra y porcentaje de actividades incumplidas por rendimiento de subcontratistas. Meta: ≤ 20% mensual.',
                        'formula' => '(Actividades_Incumplidas_Rendimiento / Total_Actividades_Programadas) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Actividades_Incumplidas_Rendimiento', 'value' => 17],
                            ['name' => 'Total_Actividades_Programadas', 'value' => 84],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Cumple (Verde)',
                                'min_value' => 0,
                                'max_value' => 20.0,
                                'qualification' => '≤ 20% — Rendimiento adecuado de mano de obra y cuadrillas',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Riesgo Moderado (Amarillo)',
                                'min_value' => 20.01,
                                'max_value' => 30.0,
                                'qualification' => '21% – 30% — Riesgo moderado en rendimiento de subcontratistas',
                                'color' => 'acceptable',
                                'score' => 70
                            ],
                            [
                                'level' => 'Crítico (Rojo)',
                                'min_value' => 30.01,
                                'max_value' => 1000,
                                'qualification' => '> 30% — Bajo rendimiento crítico de mano de obra',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Periodo', 'Actividades Programadas', 'Incumplidas por Rendimiento', '% Incumplimiento MO', 'Calificación'],
                            'rows' => [
                                ['Agosto', 'Promedio Sector', 'Subcontratistas MO', '14.00%', '100% (Verde)'],
                                ['Septiembre', '386 actividades', '63.0 actividades', '16.00%', '100% (Verde)'],
                                ['Octubre', '425 actividades', '62.5 actividades', '15.00%', '100% (Verde)'],
                                ['Noviembre', '196 actividades', '20.5 actividades', '10.00%', '100% (Verde)'],
                                ['Diciembre - Enero', '127 actividades', '15.5 actividades', '12.00%', '100% (Verde)'],
                                ['Marzo', '103 actividades', '16.0 actividades', '16.00%', '100% (Verde)'],
                                ['Abril', '233 actividades', '29.0 actividades', '12.00%', '100% (Verde)'],
                                ['Mayo', '84 actividades', '17.0 actividades', '20.00%', '100% (Verde)'],
                                ['Junio', '72 actividades', '21.0 actividades', '29.00%', '71% (Amarillo)'],
                            ]
                        ]
                    ]
                ]
            ],

            // =========================================================================
            // BLOQUE C: Calidad y Seguridad (10%)
            // =========================================================================
            [
                'name' => 'C. Calidad y Seguridad',
                'description' => 'Mide la ausencia de no conformidades críticas al cierre de cada etapa, el control de la accidentalidad (IF) por debajo del sector y la puntuación sobresaliente en auditorías SG-SST.',
                'formula' => 'Promedio de cero no conformidades críticas, índice IF y cumplimiento SG-SST',
                'target' => 100,
                'unit' => '%',
                'stage' => 'C. Calidad y Seguridad',
                'weight' => 10,
                'incidence' => 10,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'No Conformidades Críticas de Calidad',
                        'definition' => 'Defectos graves de calidad detectados en obra. Meta: 0 al cierre de cada etapa constructiva.',
                        'formula' => 'No_Conformidades_Criticas',
                        'unit' => 'No Conformidades',
                        'parameters' => [
                            ['name' => 'No_Conformidades_Criticas', 'value' => 0],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Cumplimiento Total (Verde)',
                                'min_value' => 0,
                                'max_value' => 0,
                                'qualification' => '0% — Cumplimiento total, sin no conformidades críticas',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Riesgo Moderado (Amarillo)',
                                'min_value' => 1,
                                'max_value' => 1,
                                'qualification' => '1 caso en gestión técnica y plan correctivo',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Crítico (Rojo)',
                                'min_value' => 2,
                                'max_value' => 999,
                                'qualification' => '≥ 2 casos críticos sin resolver',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Periodo', 'Hallazgo / Elemento', 'Tratamiento Técnico', 'Estado'],
                            'rows' => [
                                ['Agosto', 'Alabeo de enchape', 'Gestión y reposición con proveedor Corona', 'Cerrado'],
                                ['Septiembre - Mayo', 'Sin novedades críticas', 'Control preventivo en vaciados y acabados', '0 No conformidades'],
                                ['Junio - Agosto', 'Resistencias tanque agua', 'Segunda evaluación con Ing. Julio Garcés (Patología)', 'En seguimiento'],
                            ]
                        ]
                    ],
                    [
                        'name' => 'Índice de Frecuencia de Accidentes (IF)',
                        'definition' => 'Frecuencia de accidentes laborales por cada 100 trabajadores. Meta: Por debajo del promedio del sector (7.488).',
                        'formula' => 'Accidentes_Reportados',
                        'unit' => 'Accidentes',
                        'parameters' => [
                            ['name' => 'Accidentes_Reportados', 'value' => 0],
                            ['name' => 'Promedio_Sector', 'value' => 7.488],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Cumplimiento Total (Verde)',
                                'min_value' => 0,
                                'max_value' => 0,
                                'qualification' => '0 accidentes — Cumplimiento total, cero accidentes',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Riesgo Moderado (Amarillo)',
                                'min_value' => 0.1,
                                'max_value' => 4.0,
                                'qualification' => '0.1 – 4.0 — Muy por debajo del promedio sectorial (7.49)',
                                'color' => 'acceptable',
                                'score' => 90
                            ],
                            [
                                'level' => 'Crítico (Rojo)',
                                'min_value' => 4.01,
                                'max_value' => 999,
                                'qualification' => '> 4.0 — Por encima del estándar sectorial',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Periodo', 'Trabajadores en Obra', 'Promedio Sector', 'Accidentes Registrados', 'Calificación'],
                            'rows' => [
                                ['Agosto', '117 trabajadores', '7.488', '2 (Contratistas)', '100% (Bajo promedio)'],
                                ['Septiembre - Marzo', '~120 trabajadores', '7.488', '0 accidentes', '100% (Cero accidentes)'],
                                ['Abril', '~130 trabajadores', '7.488', '2 (Subcontratistas)', '100% (Bajo promedio)'],
                                ['Mayo', '~130 trabajadores', '7.488', '1 (Inverconstrucción)', '90% (Bajo promedio)'],
                                ['Junio', '~130 trabajadores', '7.488', '1 (Emelect)', '90% (Bajo promedio)'],
                                ['Agosto', '~130 trabajadores', '6.400', '0 accidentes', '100% (Cero accidentes)'],
                            ]
                        ]
                    ],
                    [
                        'name' => 'Puntuación Auditorías SG-SST',
                        'definition' => 'Cumplimiento normativo del Sistema de Gestión de Seguridad y Salud en el Trabajo. Meta: ≥ 90% (Promedio del sector).',
                        'formula' => 'Puntuacion_Auditoria_SST',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Puntuacion_Auditoria_SST', 'value' => 99.5],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Alto (Verde)',
                                'min_value' => 90,
                                'max_value' => 1000,
                                'qualification' => '≥ 90% — Cumplimiento alto, supera o iguala estándar del sector',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Parcial (Amarillo)',
                                'min_value' => 80,
                                'max_value' => 89.99,
                                'qualification' => '80% – 89% — Cumplimiento parcial, requiere ajustes',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Incumplimiento (Rojo)',
                                'min_value' => 0,
                                'max_value' => 79.99,
                                'qualification' => '< 80% — Incumplimiento normativo',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Auditoría / Periodo', 'Puntuación Esperada', 'Calificación Obtenida', 'Resultado'],
                            'rows' => [
                                ['Auditoría Inicial (Abril 2025)', '≥ 90.0%', '92.0%', '🟢 Verde (Supera estándar)'],
                                ['Auditorías de Seguimiento (Nov - Ago)', '≥ 90.0%', '99.5%', '🟢 Verde (Desempeño sobresaliente)'],
                            ]
                        ]
                    ]
                ]
            ],

            // =========================================================================
            // BLOQUE D: Gestión de Recursos y Relaciones (20%)
            // =========================================================================
            [
                'name' => 'D. Gestión de Recursos y Relaciones',
                'description' => 'Mide la optimización en consumo de materiales (desperdicio ≤ 5%), el liderazgo en clima laboral (360°), la puntualidad en informes de cierre y el seguimiento de tareas en Dashboard y Drive.',
                'formula' => 'Promedio de control de desperdicios, evaluación 360, informes y tareas semanales',
                'target' => 100,
                'unit' => '%',
                'stage' => 'D. Gestión de Recursos y Relaciones',
                'weight' => 20,
                'incidence' => 20,
                'lower_is_better' => false,
                'indicators' => [
                    [
                        'name' => 'Uso Eficiente de Recursos (Control de Desperdicio)',
                        'definition' => 'Optimización en consumo de materiales y uso de equipos. Meta: Desperdicio promedio general ≤ 5%.',
                        'formula' => 'Promedio_Desperdicio_Real',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Promedio_Desperdicio_Real', 'value' => 1.64],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Óptimo (Verde)',
                                'min_value' => -100,
                                'max_value' => 5.0,
                                'qualification' => 'Desperdicio ≤ 5% — Eficiencia en el uso global de recursos y ahorro presupuestal',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Moderado (Amarillo)',
                                'min_value' => 5.01,
                                'max_value' => 10.0,
                                'qualification' => '5.1% – 10% — Desviación moderada en consumo',
                                'color' => 'acceptable',
                                'score' => 75
                            ],
                            [
                                'level' => 'Crítico (Rojo)',
                                'min_value' => 10.01,
                                'max_value' => 1000,
                                'qualification' => '> 10% — Desperdicio excesivo de materiales',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Periodo', 'Desperdicio Promedio Real', 'Materiales en Rango (±5%)', 'Materiales con Ahorro', 'Estado'],
                            'rows' => [
                                ['Agosto - Septiembre', '4.46%', '73.00%', '6.00%', '100% (Verde)'],
                                ['Octubre - Noviembre', '3.59%', '47.10%', '17.60%', '100% (Verde)'],
                                ['Noviembre - Enero', '-10.45% (Ahorro)', '26.70%', '53.30%', '100% (Ahorro global)'],
                                ['Abril - Mayo', '1.64%', '52.94%', '29.41%', '100% (Verde)'],
                            ]
                        ]
                    ],
                    [
                        'name' => 'Contribución al Ambiente Laboral (Evaluación 360°)',
                        'definition' => 'Evaluación de liderazgo, actitud, serenidad bajo presión y colaboración en obra (escala 1 a 5).',
                        'formula' => 'Calificacion_360',
                        'unit' => 'Puntos',
                        'parameters' => [
                            ['name' => 'Calificacion_360', 'value' => 4.92],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Sobresaliente (Verde)',
                                'min_value' => 4.5,
                                'max_value' => 5.0,
                                'qualification' => '≥ 4.50 — Liderazgo operativo sobresaliente, resolución y serenidad bajo presión',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Adecuado (Amarillo)',
                                'min_value' => 3.5,
                                'max_value' => 4.49,
                                'qualification' => '3.50 – 4.49 — Desempeño adecuado, fortalecimiento de escucha activa',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Oportunidad (Rojo)',
                                'min_value' => 0,
                                'max_value' => 3.49,
                                'qualification' => '< 3.50 — Oportunidad de fortalecimiento en relaciones humanas',
                                'color' => 'deficient',
                                'score' => 67
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Evaluación', 'Calificación', 'Aspectos Destacados', 'Recomendaciones'],
                            'rows' => [
                                ['Evaluación Inicial 360°', '4.92 / 5.00', 'Liderazgo operativo unánime, serenidad ante la presión y resolución práctica', 'Promover espacios de escucha activa y delegación'],
                                ['Evaluación de Seguimiento', '3.33 / 5.00', 'Compromiso y orientación al resultado', 'Acompañamiento cercano y comunicación abierta'],
                            ]
                        ]
                    ],
                    [
                        'name' => 'Puntualidad en Presentación de Informes',
                        'definition' => 'Entrega de informes de cierre mensual del área técnica en fecha y formato. Meta: 100%.',
                        'formula' => '(Informes_a_Tiempo / Total_Informes) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Informes_a_Tiempo', 'value' => 100],
                            ['name' => 'Total_Informes', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Total (Verde)',
                                'min_value' => 100,
                                'max_value' => 1000,
                                'qualification' => '100% — Cumplimiento total, informes entregados a tiempo',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Parcial (Amarillo)',
                                'min_value' => 90,
                                'max_value' => 99.99,
                                'qualification' => '90% – 99% — Cumplimiento parcial con leves demoras',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Incumplimiento (Rojo)',
                                'min_value' => 0,
                                'max_value' => 89.99,
                                'qualification' => '< 90% — Incumplimiento en entrega de informes',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                    ],
                    [
                        'name' => 'Tareas con Seguimiento Semanal y Cumplidas en Drive / Dashboard',
                        'definition' => 'Porcentaje de tareas con seguimiento registrado semanalmente en Dashboard y Drive. Meta: 100%.',
                        'formula' => '(Tareas_Seguimiento / Total_Tareas) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Tareas_Seguimiento', 'value' => 100],
                            ['name' => 'Total_Tareas', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Total (Verde)',
                                'min_value' => 100,
                                'max_value' => 1000,
                                'qualification' => '100% — Todas las tareas con seguimiento registrado en Dashboard y Drive',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Parcial (Amarillo)',
                                'min_value' => 90,
                                'max_value' => 99.99,
                                'qualification' => '90% – 99% — Cumplimiento parcial en actualización',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Incumplimiento (Rojo)',
                                'min_value' => 0,
                                'max_value' => 89.99,
                                'qualification' => '< 90% — Tareas sin seguimiento registrado',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Periodo', 'Tareas Programadas', 'Seguimientos Registrados', '% Cumplimiento', 'Estado'],
                            'rows' => [
                                ['Agosto - Noviembre', '14 – 19 tareas/sem', '100% de tareas con seguimiento', '100%', '🟢 Verde'],
                                ['Diciembre - Enero', '19 tareas/sem', '100% en Dashboard y Drive', '100%', '🟢 Verde'],
                                ['Marzo - Abril', '27 – 30 tareas/sem', '245 y 117 seguimientos', '100%', '🟢 Verde'],
                                ['Mayo - Junio', '10 tareas/sem', '102 y 69 seguimientos', '100%', '🟢 Verde'],
                            ]
                        ]
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
            // =========================================================================
            // BLOQUE A: Cumplimiento de Plazo y Programación (30%)
            // =========================================================================
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
                            ['name' => 'Avance_Real_Acabados', 'value' => 95],
                            ['name' => 'Avance_Programado_Acabados', 'value' => 100],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Óptimo (Verde)',
                                'min_value' => 90,
                                'max_value' => 1000,
                                'qualification' => 'Cumplimiento ≥ 90% — Proyecto bajo control y en parámetros aceptables',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'En Riesgo (Amarillo)',
                                'min_value' => 70,
                                'max_value' => 89.99,
                                'qualification' => 'Cumplimiento 70% – 89% — Requiere ajustes inmediatos en frentes de trabajo',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Crítico (Rojo)',
                                'min_value' => 0,
                                'max_value' => 69.99,
                                'qualification' => 'Cumplimiento < 70% — Plan de acción obligatorio por atraso crítico',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Frente / Actividad', 'Avance Real', 'Programado', '% Ejecución', 'Desviación / Estado'],
                            'rows' => [
                                ['Impermeabilización Cubierta y Duchas', '100%', '100%', '100%', '0 sem (Terminada)'],
                                ['Mampostería Interna y Dovelas', '100%', '100%', '100%', '0 sem (Terminada)'],
                                ['Red Contra Incendio (RCI Tub/Válv/Gab)', '99%', '100%', '99%', 'En fase de pruebas y bomba'],
                                ['Sistema Detección y Alarmas', '100%', '100%', '100%', 'Instalación 100% terminada'],
                                ['Instalaciones Eléctricas Internas / TGA', '90% - 100%', '100%', '95%', 'Pendiente acometida y RETIE'],
                                ['Enchapes y Morteros (Baños/Cocinas/PF)', '100%', '100%', '100%', '100% Ejecutado'],
                                ['Ventanería, Vidrieras y Puertas', '100%', '100%', '100%', '100% Ejecutado'],
                                ['Ascensores y Tanques', '100%', '100%', '100%', '100% Ejecutado'],
                                ['Frente Torre 2 (Fase Inicial Acabados)', '29%', '29%', '100%', '🟢 En programa / Abasto y desague (+15d)'],
                            ]
                        ]
                    ],
                    [
                        'name' => 'Cumplimiento de Hitos de Entrega de Unidades/Zonas Comunes (Acabados)',
                        'definition' => 'Mide la puntualidad en finalización y entrega formal de apartamentos y zonas comunes al área comercial y propietarios. Meta: 90% de entregas en o antes de la fecha programada.',
                        'formula' => '(Unidades_Entregadas_ATiempo / Total_Unidades_Programadas) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Unidades_Entregadas', 'value' => 200],
                            ['name' => 'Unidades_Programadas', 'value' => 200],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Óptimo (Verde)',
                                'min_value' => 90,
                                'max_value' => 1000,
                                'qualification' => '≥ 90% — Entregas a tiempo y controladas',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'En Riesgo (Amarillo)',
                                'min_value' => 75,
                                'max_value' => 89.99,
                                'qualification' => '75% – 89% — Desviaciones menores con capacidad de ajuste semanal',
                                'color' => 'acceptable',
                                'score' => 75
                            ],
                            [
                                'level' => 'Crítico (Rojo)',
                                'min_value' => 0,
                                'max_value' => 74.99,
                                'qualification' => '< 75% — Afectación a la promesa comercial y entregas',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Frente de Entrega', 'Aptos Programados', 'Aptos Entregados', '% Cumplimiento', 'Observaciones'],
                            'rows' => [
                                ['Torre 1 - Noviembre 2025', '87', '84', '97%', 'Entregas al área comercial con desviaciones menores'],
                                ['Torre 1 - Diciembre / Enero 2026', '113', '105', '93%', 'Cierre de entregas programadas'],
                                ['Torre 1 - Febrero 2026', '6', '6', '100%', '100% de unidades programadas entregadas'],
                                ['Cierre Total Torre 1', '200', '200', '100%', '🟢 100% de unidades entregadas al área comercial y Croma'],
                            ]
                        ]
                    ]
                ]
            ],

            // =========================================================================
            // BLOQUE B: Control de Calidad y No Conformidades (30%)
            // =========================================================================
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
                        'formula' => 'Conteo mensual de no conformidades',
                        'unit' => 'NC',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'No_Conformidades_Mes', 'value' => 0],
                            ['name' => 'Meta_Max_NC', 'value' => 2],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Óptimo (Verde)',
                                'min_value' => 0,
                                'max_value' => 1.0,
                                'qualification' => '≤ 1 NC — Control adecuado sin reprocesos formales ni fallas reportadas',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Aceptable (Amarillo)',
                                'min_value' => 1.01,
                                'max_value' => 2.0,
                                'qualification' => '2 NC — Riesgo moderado con plan de acción correctivo',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Crítico (Rojo)',
                                'min_value' => 2.01,
                                'max_value' => 1000,
                                'qualification' => '> 2 NC internas o críticas externas',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                    ],
                    [
                        'name' => 'Porcentaje de Reprocesos en Acabados',
                        'definition' => 'Evalúa la eficiencia y calidad en la primera ejecución de acabados. Meta: < 3% del costo total de la actividad.',
                        'formula' => '(Costo_Reprocesos / Costo_Total_Acabados) * 100',
                        'unit' => '%',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Costo_Reproceso', 'value' => 735003],
                            ['name' => 'Costo_Total_Actividad', 'value' => 45937732],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Óptimo (Verde)',
                                'min_value' => 0,
                                'max_value' => 3.0,
                                'qualification' => '< 3% — Calidad óptima en primera ejecución sin sobrecostos significativos',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Moderado (Amarillo)',
                                'min_value' => 3.01,
                                'max_value' => 5.0,
                                'qualification' => '3% – 5% — Requiere supervisión en frentes puntuales',
                                'color' => 'acceptable',
                                'score' => 75
                            ],
                            [
                                'level' => 'Crítico (Rojo)',
                                'min_value' => 5.01,
                                'max_value' => 1000,
                                'qualification' => '> 5% — Alto sobrecosto por reprocesos',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Frente Evaluado', 'Costo Total Actividad', 'Costo Reproceso', '% Reproceso', 'Estado / Resolución'],
                            'rows' => [
                                ['Revoque Baños Piso 9 al 17', '$ 52,000 / baño', '$ 1,263 / baño', '2.80%', 'Mano de obra asumida por contratista'],
                                ['Enchape Baños Piso 2 al 18', '$ 43,385,636', '$ 735,003', '1.69%', 'Subsanado oportunamente'],
                                ['Enchape Baños Piso 2 al 19', '$ 45,937,732', '$ 735,003', '1.60%', '🟢 Dentro de meta (< 3%)'],
                                ['Cortes Subsiguientes (Feb-Jun)', '$ 0', '$ 0', '0.00%', '🟢 Sin hallazgos de reproceso'],
                            ]
                        ]
                    ],
                    [
                        'name' => 'Tiempo y Cumplimiento de Respuesta a Observaciones en Entregas de Obra al Área Comercial',
                        'definition' => 'Capacidad de respuesta y efectividad en la subsanación de observaciones técnicas detectadas durante la entrega de apartamentos al área comercial. Meta: ≥ 95%.',
                        'formula' => '(Observaciones_Subsanadas / Observaciones_Detectadas) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Observaciones_Subsanadas', 'value' => 9],
                            ['name' => 'Observaciones_Detectadas', 'value' => 9],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Óptimo (Verde)',
                                'min_value' => 95,
                                'max_value' => 1000,
                                'qualification' => '≥ 95% — Respuesta oportuna, entregas controladas sin reclamos comerciales',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Aceptable (Amarillo)',
                                'min_value' => 85,
                                'max_value' => 94.99,
                                'qualification' => '85% – 94% — Se corrige la mayoría, pero con rezagos menores',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Crítico (Rojo)',
                                'min_value' => 0,
                                'max_value' => 84.99,
                                'qualification' => '< 85% — Alto riesgo de reclamos y reprocesos post-entrega',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                    ]
                ]
            ],

            // =========================================================================
            // BLOQUE C: Control Presupuestal y Uso de Recursos (20%)
            // =========================================================================
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
                            ['name' => 'Presupuesto_Aprobado_Acabados', 'value' => 6500000000],
                            ['name' => 'Costo_Real_Acabados', 'value' => 6500000000],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Óptimo (Verde)',
                                'min_value' => 0,
                                'max_value' => 2.0,
                                'qualification' => '≤ 2% — Control presupuestal óptimo con P&G positivo',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'En Alerta (Amarillo)',
                                'min_value' => 2.01,
                                'max_value' => 5.0,
                                'qualification' => '> 2% – 5% — Desviación moderada bajo seguimiento',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Crítico (Rojo)',
                                'min_value' => 5.01,
                                'max_value' => 1000,
                                'qualification' => '> 5% — Sobrecostos que afectan la rentabilidad',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Capítulo de Acabados', 'Presupuesto Aprobado', 'Proyección / Costo', 'P&G / Variación', 'Estado'],
                            'rows' => [
                                ['Mampostería (T1)', '$ 662,060,702', '$ 835,298,683', '-$ 181,405,069', '🔴 Sobrecosto MO contratada'],
                                ['Red Contra Incendios Interna', '$ 207,961,770', '$ 380,574,356', '-$ 146,045,378', '🔴 Ajuste de cantidades/diseño'],
                                ['Instalaciones Hidrosanitarias y Gas', '$ 1,257,205,176', '$ 1,115,039,991', '+$ 142,165,185', '🟢 Ahorro significativo'],
                                ['Instalaciones Eléctricas Internas', '$ 1,167,712,042', '$ 1,722,270,243', '-$ 548,846,654', '🔴 Desviación por redes y equipos'],
                                ['Enchapes y Forros', '$ 213,985,569', '$ 234,954,541', '-$ 21,962,955', '🟡 Variación moderada'],
                                ['Bases y Pisos', '$ 306,853,995', '$ 306,853,995', '+$ 17,291,622', '🟢 Ahorro / P&G Positivo'],
                                ['Carpintería Metálica', '$ 681,262,675', '$ 597,492,590', '-$ 3,560,940', '🟢 P&G Controlado'],
                                ['Carpintería en Madera', '$ 301,849,630', '$ 267,705,425', '+$ 34,144,205', '🟢 Ahorro'],
                                ['Muebles y Equipos de Cocina', '$ 288,525,892', '$ 144,510,438', '+$ 144,015,454', '🟢 Ahorro sobresaliente'],
                                ['Estucos y Pinturas', '$ 505,925,223', '$ 396,472,014', '+$ 82,358,071', '🟢 Ahorro'],
                                ['Aparatos Sanitarios y Grifería', '$ 88,015,100', '$ 86,313,948', '+$ 1,701,152', '🟢 Cumple meta'],
                                ['Mampostería Torre 2 (Fase Inicial)', '$ 890,356,829', '$ 1,126,512,774', '-$ 236,155,946', '🟡 Monitoreo de rendimientos MO'],
                            ]
                        ]
                    ],
                    [
                        'name' => 'Porcentaje de Desperdicios de Materiales de Acabados',
                        'definition' => 'Eficiencia en el consumo de materiales clave de acabados. Meta: Desperdicio < 5% del valor total.',
                        'formula' => '(Valor_Desperdicios / Valor_Total_Materiales) * 100',
                        'unit' => '%',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Desperdicio_Promedio_Real', 'value' => 4.56],
                            ['name' => 'Meta_Desperdicio', 'value' => 5.0],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Óptimo (Verde)',
                                'min_value' => 0,
                                'max_value' => 5.0,
                                'qualification' => '< 5% — Eficiencia en consumo y bajo desperdicio',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Moderado (Amarillo)',
                                'min_value' => 5.01,
                                'max_value' => 10.0,
                                'qualification' => '5% – 10% — Requiere ajuste en cortes y control en frentes',
                                'color' => 'acceptable',
                                'score' => 70
                            ],
                            [
                                'level' => 'Crítico (Rojo)',
                                'min_value' => 10.01,
                                'max_value' => 1000,
                                'qualification' => '> 10% — Sobrecostos por alto desperdicio o roturas',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                        'tablaDetalle' => [
                            'headers' => ['Insumo / Material', 'Cantidad Nominal', 'Cantidad Instalada', '% Desperdicio Real', 'Estado / Control'],
                            'rows' => [
                                ['Bloque 10×20×40', '63,338 und', '66,226 und', '4.56%', '🟢 Dentro de meta (<5%)'],
                                ['Bloque 15×20×40', '1,610 und', '1,792 und', '11.30%', '🔴 Desperdicio por cortes específicos'],
                                ['Bloque 20×20×40', '1,700 und', '1,792 und', '5.41%', '🟡 Desviación leve'],
                                ['Cerámica Natal', '1,775.36 m²', '1,712.10 m²', '-3.56%', '🟢 Eficiencia / Ahorro'],
                                ['Cerámica Belaya Beige', '330.00 m²', '395.98 m²', '19.99%', '🔴 Requiere control en colocación'],
                                ['Cerámica Vancuver', '1,522.00 m²', '1,583.00 m²', '3.98%', '🟢 Dentro de meta'],
                                ['Megapega', '29,022.40 kg', '28,708.61 kg', '-1.08%', '🟢 Eficiencia / Ahorro'],
                                ['Concolor Blanco / Beige / Gris', 'Varios', 'Varios', '3.27% / 13.64% / -7.72%', '🟡 Comportamiento mixto'],
                                ['Sanitarios y Lavamanos', '200 und', '200 und', '0.00%', '🟢 Control total en instalación'],
                                ['Cemento Mampostería y Morteros', '1,448 sacos', '1,501 sacos', '1.79% – 5.56%', '🟢 Buen control'],
                            ]
                        ]
                    ]
                ]
            ],

            // =========================================================================
            // BLOQUE D: Gestión de Información y Coordinación (20%)
            // =========================================================================
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
                        'formula' => 'Tiempo promedio de resolución (días hábiles)',
                        'unit' => 'días',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Tareas_Completadas', 'value' => 192],
                            ['name' => 'Total_Tareas_Asignadas', 'value' => 208],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Óptimo (Verde)',
                                'min_value' => 0,
                                'max_value' => 2.0,
                                'qualification' => '≤ 2 días hábiles — Respuesta ágil y gestión oportuna',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Aceptable (Amarillo)',
                                'min_value' => 2.01,
                                'max_value' => 4.0,
                                'qualification' => '2.1 – 4 días hábiles — Carga operativa con retrasos leves',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Crítico (Rojo)',
                                'min_value' => 4.01,
                                'max_value' => 1000,
                                'qualification' => '> 4 días hábiles — Cuellos de botella en atención técnica',
                                'color' => 'deficient',
                                'score' => 20
                            ],
                        ],
                    ],
                    [
                        'name' => 'Puntualidad en la Entrega de Informes de Acta de comité',
                        'definition' => 'Cumplimiento en fecha (jueves) y formato establecido para el envío de informes de avance y actas de comité. Meta: 100% de entregas puntuales.',
                        'formula' => '(Informes_Entregados_ATiempo / Total_Informes_Programados) * 100',
                        'unit' => '%',
                        'parameters' => [
                            ['name' => 'Informes_ATiempo', 'value' => 4],
                            ['name' => 'Total_Informes', 'value' => 4],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Óptimo (Verde)',
                                'min_value' => 100,
                                'max_value' => 1000,
                                'qualification' => '100% — Entregas puntuales en todos los cortes establecidos',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Parcial (Amarillo)',
                                'min_value' => 90,
                                'max_value' => 99.99,
                                'qualification' => '90% – 99% — Entrega con retraso menor',
                                'color' => 'acceptable',
                                'score' => 75
                            ],
                            [
                                'level' => 'Incumplimiento (Rojo)',
                                'min_value' => 0,
                                'max_value' => 89.99,
                                'qualification' => '< 90% — Incumplimiento en reporte de actas',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                    ],
                    [
                        'name' => 'Contribución al Ambiente Laboral (Evaluación 360° / Feedback Interno)',
                        'definition' => 'Evaluación de competencias de liderazgo, trabajo en equipo, comunicación y coordinación técnica en obra. Meta: ≥ 4.5 / 5.0.',
                        'formula' => 'Puntaje de evaluación 360° (Escala de 1 a 5)',
                        'unit' => 'pts',
                        'parameters' => [
                            ['name' => 'Calificacion_360', 'value' => 4.46],
                            ['name' => 'Escala_Maxima', 'value' => 5.0],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Sobresaliente (Verde)',
                                'min_value' => 4.5,
                                'max_value' => 5.0,
                                'qualification' => '≥ 4.5 pts — Liderazgo sobresaliente y articulación de equipos',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Aceptable (Amarillo)',
                                'min_value' => 3.5,
                                'max_value' => 4.49,
                                'qualification' => '3.5 – 4.49 pts — Buen desempeño técnico con oportunidad en habilidades blandas',
                                'color' => 'acceptable',
                                'score' => 89
                            ],
                            [
                                'level' => 'Deficiente (Rojo)',
                                'min_value' => 0,
                                'max_value' => 3.49,
                                'qualification' => '< 3.5 pts — Desalineación en clima y relaciones de trabajo',
                                'color' => 'deficient',
                                'score' => 0
                            ],
                        ],
                    ],
                    [
                        'name' => 'Número de Quejas de Clientes por Calidad de Acabados (Post-entrega)',
                        'definition' => 'Control de incidencias y solicitudes posventas radicadas a través de códigos QR Inverconstrucción y QR Croma tras entrega formal. Meta: 0 quejas no resueltas en plazo.',
                        'formula' => 'Conteo mensual de quejas de calidad no atendidas en plazo de garantía',
                        'unit' => 'quejas',
                        'lower_is_better' => true,
                        'parameters' => [
                            ['name' => 'Quejas_Registradas', 'value' => 1],
                            ['name' => 'Quejas_Atendidas', 'value' => 1],
                        ],
                        'conditional_goals' => [
                            [
                                'level' => 'Óptimo (Verde)',
                                'min_value' => 0,
                                'max_value' => 0.0,
                                'qualification' => '0 quejas no resueltas — Alta satisfacción y calidad de entrega',
                                'color' => 'optimal',
                                'score' => 100
                            ],
                            [
                                'level' => 'Moderado (Amarillo)',
                                'min_value' => 1.0,
                                'max_value' => 2.0,
                                'qualification' => '1 – 2 incidencias atendidas dentro de tiempos de garantía',
                                'color' => 'acceptable',
                                'score' => 80
                            ],
                            [
                                'level' => 'Crítico (Rojo)',
                                'min_value' => 2.01,
                                'max_value' => 1000,
                                'qualification' => '> 2 quejas o incumplimiento de tiempos de garantía',
                                'color' => 'deficient',
                                'score' => 0
                            ],
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


