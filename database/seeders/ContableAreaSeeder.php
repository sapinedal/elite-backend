<?php

namespace Database\Seeders;

use App\Http\Modules\Configuracion\Models\Area;
use App\Http\Modules\Configuracion\Models\Position;
use App\Http\Modules\Users\Models\User;
use App\Http\Modules\Plantillas\Models\KPI;
use Illuminate\Database\Seeder;

class ContableAreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Asegurar el Área Contable sin duplicar si ya existe
        $contableArea = Area::whereRaw('LOWER(name) IN (?, ?, ?)', ['contable', 'contabilidad', 'contabilidad y finanzas'])->first()
            ?? Area::firstOrCreate(
                ['name' => 'Contable'],
                ['description' => 'Área encargada de la gestión financiera, contable, tributaria, tesorería y talento humano']
            );

        // 2. Cargos pertenecientes al Área Contable
        $positions = [
            'Directora Contable',
            'Analista Contable',
            'Analista de tesoreria',
            'Analista Talento Humano',
        ];

        $createdPositions = [];
        foreach ($positions as $posName) {
            $createdPositions[$posName] = Position::where('area_id', $contableArea->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($posName)])
                ->first()
                ?? Position::whereRaw('LOWER(name) = ?', [mb_strtolower($posName)])->first()
                ?? Position::firstOrCreate([
                    'name' => $posName,
                    'area_id' => $contableArea->id
                ]);
        }

        // =========================================================================
        // USUARIOS DEL ÁREA CONTABLE
        // =========================================================================
        $colaboradores = [
            [
                'first_name' => 'SOFIA',
                'last_name' => 'YEPES PEÑA',
                'name' => 'SOFIA YEPES PEÑA',
                'document' => '1152225718',
                'position_name' => 'Directora Contable',
                'email' => 'contadora@inverconstruccion.com',
            ],
            [
                'first_name' => 'ISABEL CRISTINA',
                'last_name' => 'GARCIA MARIN',
                'name' => 'ISABEL CRISTINA GARCIA MARIN',
                'document' => '1128277835',
                'position_name' => 'Analista Contable',
                'email' => 'analistacontable2@inverconstruccion.com',
            ],
            [
                'first_name' => 'CLAUDIA PATRICIA',
                'last_name' => 'JIMENEZ CARVAJAL',
                'name' => 'CLAUDIA PATRICIA JIMENEZ CARVAJAL',
                'document' => '1035414229',
                'position_name' => 'Analista de tesoreria',
                'email' => 'asistentedetesoreria@inverconstruccion.com',
            ],
            [
                'first_name' => 'SHARON JOLAINE',
                'last_name' => 'VELANDIA TELLEZ',
                'name' => 'SHARON JOLAINE VELANDIA TELLEZ',
                'document' => '1065917552',
                'position_name' => 'Analista de tesoreria',
                'email' => 'facturacion@inverconstruccion.com',
            ],
            [
                'first_name' => 'VANESSA',
                'last_name' => 'CALLE VALDERRAMA',
                'name' => 'VANESSA CALLE VALDERRAMA',
                'document' => '1020437864',
                'position_name' => 'Analista Talento Humano',
                'email' => 'analistacontable@inverconstruccion.com',
            ],
        ];

        $instantiatedUsers = [];
        foreach ($colaboradores as $colab) {
            $user = User::where('document', $colab['document'])
                ->orWhereRaw('LOWER(email) = ?', [mb_strtolower($colab['email'])])
                ->first();

            $position = $createdPositions[$colab['position_name']] ?? $createdPositions['Analista Contable'];

            if (!$user) {
                $user = User::create([
                    'first_name' => mb_strtoupper($colab['first_name'], 'UTF-8'),
                    'last_name' => mb_strtoupper($colab['last_name'], 'UTF-8'),
                    'name' => mb_strtoupper($colab['name'], 'UTF-8'),
                    'document' => $colab['document'],
                    'position_id' => $position->id,
                    'area_id' => $contableArea->id,
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
                    'area_id' => $contableArea->id,
                    'email' => $user->email ?: $colab['email'],
                ]);
            }

            $instantiatedUsers[$colab['document']] = $user;
        }

        // =========================================================================
        // KPIS: DIRECTORA CONTABLE (CC: 1152225718 - Sofia Yepes Peña)
        // =========================================================================
        $userDirectora = $instantiatedUsers['1152225718'] ?? null;
        if ($userDirectora) {
            $kpisDirectora = [
                // BLOQUE A: Gestión Financiera y Contable (40%)
                [
                    'name' => 'A. Gestión Financiera y Contable',
                    'description' => 'Garantiza la entrega oportuna de estados financieros, proyecciones tributarias, exactitud contable, conciliaciones de cartera, bancarias, anticipos y cuadros de pago.',
                    'formula' => 'Promedio ponderado de estados financieros, exactitud contable, conciliaciones y oportunidad en pagos',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'A. Gestión Financiera y Contable',
                    'weight' => 40,
                    'incidence' => 40,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Oportunidad en presentación de estados financieros',
                            'definition' => 'Mide el porcentaje de cumplimiento en la entrega de estados financieros mensuales y anuales dentro de los primeros 15 días del mes siguiente.',
                            'formula' => '(Estados_Financieros_ATiempo / Total_Estados_Financieros) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Estados_Financieros_ATiempo', 'value' => 0],
                                ['name' => 'Total_Estados_Financieros', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Envío a tiempo dentro de los primeros 15 días del mes', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99% — Envío con retraso menor justificado', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Incumplimiento (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Entrega fuera de los plazos establecidos', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Proyección del impuesto de renta',
                            'definition' => 'Cumplimiento en la entrega oportuna de la proyección del impuesto de renta dentro de los primeros 15 días del mes siguiente.',
                            'formula' => '(Proyecciones_Renta_ATiempo / Total_Proyecciones_Renta) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Proyecciones_Renta_ATiempo', 'value' => 0],
                                ['name' => 'Total_Proyecciones_Renta', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Envío dentro de fechas límite', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99% — Envío con retraso menor', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Incumplimiento (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Entrega extemporánea', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Proyección tasa mínima de tributación',
                            'definition' => 'Cumplimiento en la entrega oportuna de la proyección de tasa mínima de tributación dentro de los plazos establecidos.',
                            'formula' => '(Proyecciones_TasaMinima_ATiempo / Total_Proyecciones_TasaMinima) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Proyecciones_TasaMinima_ATiempo', 'value' => 0],
                                ['name' => 'Total_Proyecciones_TasaMinima', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Envío dentro de fechas límite', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99% — Envío con retraso menor', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Incumplimiento (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Entrega extemporánea', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Exactitud de la información contable',
                            'definition' => 'Número de ajustes a periodos anteriores realizados después de haber cerrado el mes contable. Meta: 0 ajustes.',
                            'formula' => 'Numero_Ajustes_Periodos_Anteriores',
                            'unit' => 'ajustes',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Numero_Ajustes_Periodos_Anteriores', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 0.0, 'qualification' => '0 ajustes — Información contable exacta y sin reprocesos', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 1.0, 'max_value' => 2.0, 'qualification' => '1 – 2 ajustes resueltos en el periodo', 'color' => 'acceptable', 'score' => 70],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 2.01, 'max_value' => 1000, 'qualification' => '> 2 ajustes después del cierre contable', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Causación en la asignación presupuestal correcta - Codificación',
                            'definition' => 'Revisión del auxiliar de la cuenta 14. Tras el cierre de mes no debe existir ningún registro mal codificado.',
                            'formula' => 'Numero_Inconsistencias_Codificacion',
                            'unit' => 'inconsistencias',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Numero_Inconsistencias_Codificacion', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 0.0, 'qualification' => '0 inconsistencias — Causación y codificación presupuestal exacta', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 1.0, 'max_value' => 1.0, 'qualification' => '1 inconsistencia corregida de inmediato', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 1.01, 'max_value' => 1000, 'qualification' => '> 1 error de codificación tras el cierre', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Conciliación partidas cartera (Apartamentos, Parqueaderos y Croma)',
                            'definition' => 'Conciliación mensual de cartera con área comercial y Multifox, subsanando diferencias identificadas.',
                            'formula' => '(Diferencias_Cartera_Subsanadas / Total_Diferencias_Cartera_Identificadas) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Diferencias_Cartera_Subsanadas', 'value' => 0],
                                ['name' => 'Total_Diferencias_Cartera_Identificadas', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Cierre sin novedades ni diferencias', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99% — Diferencias identificadas y en proceso de gestión', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Partidas no conciliadas ni aclaradas', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Elaboración Paz y Salvo clientes para escrituración',
                            'definition' => 'Emitir el paz y salvo dentro de los 2 días hábiles siguientes a la solicitud de cartera o subsanación de novedades comerciales.',
                            'formula' => 'Tiempo_Promedio_Emision_PazYSalvo_Dias',
                            'unit' => 'días',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Tiempo_Promedio_Emision_PazYSalvo_Dias', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 2.0, 'qualification' => '≤ 2 días hábiles — Emisión oportuna de paz y salvos', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 2.01, 'max_value' => 5.0, 'qualification' => '2.1 – 5 días hábiles — Trámite con novedades comerciales en gestión', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 5.01, 'max_value' => 1000, 'qualification' => '> 5 días hábiles — Retraso que afecta escrituración', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Revisión de conciliaciones bancarias',
                            'definition' => 'Validación de conciliaciones bancarias al día 25 de cada mes, con todas las diferencias identificadas. Meta: ≥ 95%.',
                            'formula' => '(Conciliaciones_Bancarias_Conformes / Total_Cuentas_Bancarias) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Conciliaciones_Bancarias_Conformes', 'value' => 0],
                                ['name' => 'Total_Cuentas_Bancarias', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 95, 'max_value' => 1000, 'qualification' => '≥ 95% — Conciliaciones al día y diferencias identificadas', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 80, 'max_value' => 94.99, 'qualification' => '80% – 94.99% — Conciliación parcial con partidas en tránsito', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — Cuentas sin conciliar oportunamente', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Revisión y envío cuadro de pagos semanal',
                            'definition' => 'Cuadro de pagos semanal sin inconsistencias ni errores, enviado los jueves de cada semana. Meta: 100% a tiempo.',
                            'formula' => '(Cuadros_Pagos_ATiempo / Total_Cuadros_Pagos_Programados) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Cuadros_Pagos_ATiempo', 'value' => 0],
                                ['name' => 'Total_Cuadros_Pagos_Programados', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Cuadro de pagos enviado a tiempo y sin errores', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99% — Retraso menor justificado', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Incumplimiento (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Incumplimiento en cronograma de pagos', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Diligenciamiento formatos de actualización de información financiera',
                            'definition' => 'Tiempo de respuesta para diligenciar y remitir formatos y certificaciones financieras solicitadas internamente. Meta: ≤ 1 día hábil.',
                            'formula' => 'Tiempo_Promedio_Respuesta_Formatos_Dias',
                            'unit' => 'días',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Tiempo_Promedio_Respuesta_Formatos_Dias', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 1.0, 'qualification' => '≤ 1 día hábil — Información enviada con máxima agilidad', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 1.01, 'max_value' => 2.0, 'qualification' => '1.1 – 2 días hábiles — Respuesta oportuna', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 2.01, 'max_value' => 1000, 'qualification' => '> 2 días hábiles — Demora en trámites financieros/bancarios', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Revisión de cuentas de nómina en estado financiero',
                            'definition' => 'Revisión periódica de nómina en estados financieros y corrección inmediata de inconsistencias detectadas. Meta: 0 errores tras pago.',
                            'formula' => '(Registros_Nomina_Correctos / Total_Registros_Nomina) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Registros_Nomina_Correctos', 'value' => 0],
                                ['name' => 'Total_Registros_Nomina', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Nómina sin inconsistencias ni errores', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99% — Novedades menores corregidas en el ciclo', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Errores en pagos o causación de nómina', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Revisión de Conciliación de anticipos y cuentas por pagar',
                            'definition' => 'Seguimiento al saldo por amortizar de anticipos al día 5 del mes siguiente y actualización permanente de estados de cuenta de proveedores.',
                            'formula' => '(Cuentas_Anticipos_Conciliadas / Total_Cuentas_Proveedores) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Cuentas_Anticipos_Conciliadas', 'value' => 0],
                                ['name' => 'Total_Cuentas_Proveedores', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 95, 'max_value' => 1000, 'qualification' => '≥ 95% — Anticipos amortizados y cuentas por pagar al día', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 85, 'max_value' => 94.99, 'qualification' => '85% – 94.99% — Seguimiento continuo con variaciones justificadas', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 84.99, 'qualification' => '< 85% — Descontrol en amortizaciones o cuentas de proveedores', 'color' => 'deficient', 'score' => 0],
                            ],
                        ]
                    ]
                ],

                // BLOQUE B: Gestión Tributaria y Legal (30%)
                [
                    'name' => 'B. Gestión Tributaria y Legal',
                    'description' => 'Mide la oportunidad y cumplimiento estricto en la presentación y pago de obligaciones tributarias (Retefuente, ReteICA, Renta, ICA, Exógena), conciliación mensual de impuestos y cero sanciones o multas.',
                    'formula' => 'Promedio de cumplimiento en declaraciones tributarias, conciliación fiscal y cero sanciones',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'B. Gestión Tributaria y Legal',
                    'weight' => 30,
                    'incidence' => 30,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Cumplimiento de obligaciones tributarias (Retefuente, ReteICA, Renta, ICA y Exógena)',
                            'definition' => 'Porcentaje de declaraciones tributarias nacionales y distritales presentadas y pagadas dentro de las fechas límites estipuladas. Meta: 100% a tiempo.',
                            'formula' => '(Declaraciones_Presentadas_ATiempo / Total_Declaraciones_Obligatorias) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Declaraciones_Presentadas_ATiempo', 'value' => 0],
                                ['name' => 'Total_Declaraciones_Obligatorias', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Todas las declaraciones presentadas y pagadas a tiempo', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99% — Presentación dentro de calendario', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Extemporaneidad o riesgo fiscal', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Conciliación mensual de impuestos',
                            'definition' => 'Conciliación mensual de cuentas de impuestos antes del día 20 de cada mes sin diferencias pendientes. Meta: 100%.',
                            'formula' => '(Impuestos_Conciliados_ATiempo / Total_Periodos_Impuestos) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Impuestos_Conciliados_ATiempo', 'value' => 0],
                                ['name' => 'Total_Periodos_Impuestos', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Cierre fiscal y conciliación sin novedades', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99% — Observaciones del revisor subsanadas oportunamente', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Diferencias en cuentas tributarias', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Cero Sanciones, Multas o Glosas DIAN',
                            'definition' => 'Mantener 0 sanciones, multas o glosas por parte de la DIAN u otras entidades tributarias/laborales. Meta: 0.',
                            'formula' => 'Numero_Sanciones_Multas_Glosas',
                            'unit' => 'sanciones',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Numero_Sanciones_Multas_Glosas', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 0.0, 'qualification' => '0 sanciones/glosas — Cumplimiento normativo impecable', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 1.0, 'max_value' => 1000, 'qualification' => '> 0 sanciones o glosas atribuibles al área', 'color' => 'deficient', 'score' => 0],
                            ],
                        ]
                    ]
                ],

                // BLOQUE C: Liderazgo y Gestión Administrativa (30%)
                [
                    'name' => 'C. Liderazgo y Gestión Administrativa',
                    'description' => 'Mide la actualización semanal del flujo de caja, clima laboral y desarrollo del equipo (360°), envío oportuno de comprobantes a proveedores (24h) y cumplimiento de tareas.',
                    'formula' => 'Promedio de flujo de caja, evaluación 360, envío de comprobantes y seguimiento en Dashboard/Drive',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'C. Liderazgo y Gestión Administrativa',
                    'weight' => 30,
                    'incidence' => 30,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Eficiencia en gestión de tesorería y flujo de caja',
                            'definition' => 'Actualización semanal del formato de flujo de caja antes del cierre (viernes 5:00 p.m.) garantizando visibilidad de liquidez. Meta: 100%.',
                            'formula' => '(Semanas_Flujo_Actualizado_ATiempo / Total_Semanas_Periodo) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Semanas_Flujo_Actualizado_ATiempo', 'value' => 0],
                                ['name' => 'Total_Semanas_Periodo', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Flujo de caja actualizado semanalmente sin interrupciones', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 80, 'max_value' => 99.99, 'qualification' => '80% – 99% — Actualización con retrasos menores', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — Falta de visibilidad en liquidez', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Desempeño y Clima Laboral del Equipo (Evaluación 360° / Rotación)',
                            'definition' => 'Calificación de liderazgo en evaluación 360°, estabilidad y cumplimiento de metas del equipo contable/tesorería. Meta: ≥ 4.5 pts.',
                            'formula' => 'Puntaje_Liderazgo_360',
                            'unit' => 'pts',
                            'parameters' => [
                                ['name' => 'Puntaje_Liderazgo_360', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Sobresaliente (Verde)', 'min_value' => 4.5, 'max_value' => 5.0, 'qualification' => '≥ 4.5 pts — Liderazgo sólido, comunicación clara y equipo estable', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 3.5, 'max_value' => 4.49, 'qualification' => '3.5 – 4.49 pts — Buen desempeño con oportunidad en delegación', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 3.49, 'qualification' => '< 3.5 pts — Oportunidad de fortalecimiento en clima laboral', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Envío oportuno de comprobantes de pago a proveedores',
                            'definition' => 'Envío de comprobantes de pago dentro de las 24 horas hábiles siguientes a la ejecución del pago. Meta: 100%.',
                            'formula' => '(Comprobantes_Enviados_ATiempo / Total_Pagos_Realizados) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Comprobantes_Enviados_ATiempo', 'value' => 0],
                                ['name' => 'Total_Pagos_Realizados', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Comprobantes enviados oportunamente dentro de 24h', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99% — Pocas novedades aisladas', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Riesgo de reprocesos y consultas de proveedores', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Seguimiento de tareas en Dashboard y Drive',
                            'definition' => 'Porcentaje de tareas propias y del equipo con seguimiento semanal en Daily y cumplimiento en Drive en plazos establecidos. Meta: 100%.',
                            'formula' => '(Tareas_Cumplidas_ATiempo / Total_Tareas_Asignadas) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Tareas_Cumplidas_ATiempo', 'value' => 0],
                                ['name' => 'Total_Tareas_Asignadas', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Tareas completadas a tiempo con seguimiento registrado', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99% — Cumplimiento parcial con justificación', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Incumplimiento (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Tareas vencidas o sin seguimiento', 'color' => 'deficient', 'score' => 0],
                            ],
                        ]
                    ]
                ]
            ];

            foreach ($kpisDirectora as $kpiData) {
                $userDirectora->kpis()->updateOrCreate(
                    ['name' => $kpiData['name']],
                    $kpiData
                );
            }
        }

        // =========================================================================
        // KPIS: ANALISTA CONTABLE (CC: 1128277835 - Isabel Cristina Garcia Marin)
        // =========================================================================
        $userAnalista = $instantiatedUsers['1128277835'] ?? null;
        if ($userAnalista) {
            $kpisAnalista = [
                // BLOQUE A: Precisión y Cumplimiento Normativo (15%)
                [
                    'name' => 'A. Precisión y Cumplimiento Normativo',
                    'description' => 'Mide la exactitud, control y cumplimiento legal/fiscal en recepción y revisión de facturas, tiempo total del flujo de facturación, ausencia de errores en registros contables y exactitud en borradores tributarios.',
                    'formula' => 'Promedio de cumplimiento de facturas, tiempos de facturación, registros contables e impuestos',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'A. Precisión y Cumplimiento Normativo',
                    'weight' => 15,
                    'incidence' => 15,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => '% de Facturas con Cumplimiento Legal y Fiscal',
                            'definition' => 'Mide la eficiencia y exactitud en recepción, revisión y procesamiento de facturas, verificando cumplimiento de requisitos DIAN y procedimientos internos. Meta: 0 errores propios no detectados.',
                            'formula' => 'Numero_Errores_Propios_No_Detectados',
                            'unit' => 'errores',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Numero_Errores_Propios_No_Detectados', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 0.0, 'qualification' => '0 errores propios — Excelente control, detección oportuna de inconsistencias externas', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 1.0, 'max_value' => 2.0, 'qualification' => '1 – 2 errores propios no detectados — Oportunidad de mejora en revisión', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 2.01, 'max_value' => 1000, 'qualification' => '> 2 errores propios no detectados — Fallas en validación contable', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Tiempo Total del Proceso de Facturación',
                            'definition' => 'Evalúa la eficiencia del proceso interno de registro y validación de facturas, asegurando que el tiempo total del flujo no exceda los 6 días hábiles.',
                            'formula' => 'Tiempo_Total_Proceso_Facturacion_Dias',
                            'unit' => 'días hábiles',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Tiempo_Total_Proceso_Facturacion_Dias', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 6.0, 'qualification' => '≤ 6 días hábiles — Proceso fluido y dentro de tiempos', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 6.01, 'max_value' => 8.0, 'qualification' => '6.1 – 8 días hábiles — Desviación moderada en flujo', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 8.01, 'max_value' => 1000, 'qualification' => '> 8 días hábiles — Retrasos significativos en el ciclo', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Número de Errores en Registros Contables',
                            'definition' => 'Cantidad de errores detectados en la causación y registro de facturas o documentos de soporte. Meta: 0–1 errores.',
                            'formula' => 'Numero_Errores_Registros_Contables',
                            'unit' => 'errores',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Numero_Errores_Registros_Contables', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 1.0, 'qualification' => '0 – 1 errores — Cumplimiento excelente sin impacto operativo', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 2.0, 'max_value' => 2.0, 'qualification' => '2 errores — Cumplimiento mínimo aceptable, requiere seguimiento', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 2.01, 'max_value' => 1000, 'qualification' => '≥ 3 errores — Afecta la calidad de la información financiera', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Porcentaje de Borradores de Impuestos sin Errores',
                            'definition' => 'Exactitud y oportunidad de borradores de declaraciones tributarias (Retefuente, ReteICA) antes de su presentación.',
                            'formula' => '(Borradores_Correctos / Borradores_Totales) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Borradores_Correctos', 'value' => 0],
                                ['name' => 'Borradores_Totales', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Cumplimiento total, sin ajustes ni reprocesos', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 95, 'max_value' => 99.99, 'qualification' => '95% – 99.99% — Ajustes menores antes de presentación', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 94.99, 'qualification' => '< 95% — Riesgo tributario o necesidad de reprocesos', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],

                // BLOQUE B: Eficiencia en Procesos (40%)
                [
                    'name' => 'B. Eficiencia en Procesos',
                    'description' => 'Mide la celeridad en la causación de facturas (0-48h), oportunidad en la aceptación/rechazo en RADIAN a tiempo (100%) y emisión oportuna de documentos soporte electrónicos (≤ 6 días).',
                    'formula' => 'Promedio ponderado de causación oportuna, radicación RADIAN y emisión de documentos soporte',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'B. Eficiencia en Procesos',
                    'weight' => 40,
                    'incidence' => 40,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Tiempo Promedio de Causación de Facturas',
                            'definition' => 'Tiempo promedio en registrar contablemente una factura en Siigo desde su recepción. Meta: 0 – 48 horas.',
                            'formula' => 'Tiempo_Promedio_Causacion_Horas',
                            'unit' => 'horas',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Tiempo_Promedio_Causacion_Horas', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 48.0, 'qualification' => '0 – 48 horas — Proceso ágil y dentro del estándar óptimo', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 48.01, 'max_value' => 72.0, 'qualification' => '49 – 72 horas — Retrasos moderados que requieren seguimiento', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 72.01, 'max_value' => 1000, 'qualification' => '> 72 horas — Retrasos significativos en oportunidad contable', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Porcentaje de Facturas Registradas en RADIAN a Tiempo',
                            'definition' => 'Cumplimiento de plazos en el acuse y registro de eventos de facturas electrónicas en RADIAN (3 días hábiles). Meta: 100%.',
                            'formula' => '(Facturas_Registradas_ATiempo_RADIAN / Total_Facturas_A_Registrar_RADIAN) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Facturas_Registradas_ATiempo_RADIAN', 'value' => 0],
                                ['name' => 'Total_Facturas_A_Registrar_RADIAN', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Registro y acuse oportuno de todas las facturas en RADIAN', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Cumplimiento adecuado con rezagos mínimos', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Riesgo de aceptación tácita indebida', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Tiempo Promedio de Emisión de Documentos Soporte',
                            'definition' => 'Rapidez en la elaboración de documentos soporte electrónicos a personas naturales (hasta el sexto día hábil tras cuenta de cobro). Meta: ≤ 6 días.',
                            'formula' => 'Tiempo_Promedio_Emision_DocSoporte_Dias',
                            'unit' => 'días',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Tiempo_Promedio_Emision_DocSoporte_Dias', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 6.0, 'qualification' => '≤ 6 días — Emisión ágil dentro del marco legal', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 6.01, 'max_value' => 8.0, 'qualification' => '6.1 – 8 días — Trámite con novedades en validación', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 8.01, 'max_value' => 1000, 'qualification' => '> 8 días — Retrasos que afectan soporte de costos', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],

                // BLOQUE C: Gestión y Control (15%)
                [
                    'name' => 'C. Gestión y Control',
                    'description' => 'Mide la calidad de la conciliación contable DIAN vs Multifox (≤1% discrepancias), trazabilidad en gestión y cierre de discrepancias, control y amortización de anticipos, clima laboral (360°) y cero hallazgos en auditorías.',
                    'formula' => 'Promedio de control DIAN, gestión de discrepancias, anticipos, evaluación 360 y auditorías',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'C. Gestión y Control',
                    'weight' => 15,
                    'incidence' => 15,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Calidad del Registro Contable (DIAN vs Multifox) - Discrepancias Identificadas',
                            'definition' => 'Medir la exactitud del registro contable frente a lo reportado en la DIAN. Meta: ≤ 1% de discrepancias.',
                            'formula' => '(Discrepancias_Detectadas / Total_Facturas_Verificadas) * 100',
                            'unit' => '%',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Discrepancias_Detectadas', 'value' => 0],
                                ['name' => 'Total_Facturas_Verificadas', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 1.0, 'qualification' => '≤ 1% — Excelente control y calidad del registro contable', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 1.01, 'max_value' => 3.0, 'qualification' => '1.01% – 3% — Trazabilidad aceptable, requiere seguimiento', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 3.01, 'max_value' => 1000, 'qualification' => '> 3% — Riesgo alto, requiere correcciones urgentes', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Gestión y Cierre de Discrepancias',
                            'definition' => 'Efectividad del seguimiento para garantizar el cierre de todas las discrepancias detectadas (reporte inicial, recordatorios y cierre formal). Meta: 100%.',
                            'formula' => '(Discrepancias_Gestionadas_Completas / Total_Discrepancias_Detectadas) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Discrepancias_Gestionadas_Completas', 'value' => 0],
                                ['name' => 'Total_Discrepancias_Detectadas', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Gestión completa con insistencia y cierre documentado', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Gestión adecuada, falta seguimiento en casos aislados', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Gestión insuficiente o falta de insistencia/cierre', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Porcentaje de Anticipos Legalizados y Amortizados Correctamente',
                            'definition' => 'Control y seguimiento contable frente a la legalización y amortización de anticipos entregados a contratistas y proveedores. Meta: ≥ 95%.',
                            'formula' => '(Anticipos_Amortizados_Correctamente / Total_Anticipos_Programados) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Anticipos_Amortizados_Correctamente', 'value' => 0],
                                ['name' => 'Total_Anticipos_Programados', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 95, 'max_value' => 1000, 'qualification' => '≥ 95% — Control óptimo, amortizaciones al día sin riesgos', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 80, 'max_value' => 94.99, 'qualification' => '80% – 94.99% — Gestión aceptable acorde al avance de frentes', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — Riesgo por acumulación de anticipos sin amortizar', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Resultados de encuestas de clima laboral (Evaluación 360°)',
                            'definition' => 'Evaluación de competencias, colaboración, proactividad y clima de trabajo (escala sobre 5.0). Meta: ≥ 4.5 pts.',
                            'formula' => 'Puntaje_Evaluacion_360',
                            'unit' => 'pts',
                            'parameters' => [
                                ['name' => 'Puntaje_Evaluacion_360', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Sobresaliente (Verde)', 'min_value' => 4.5, 'max_value' => 5.0, 'qualification' => '≥ 4.5 pts — Desempeño sólido, colaborativo y de alto aporte al equipo', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 3.5, 'max_value' => 4.49, 'qualification' => '3.5 – 4.49 pts — Desempeño adecuado con oportunidades de mejora', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 3.49, 'qualification' => '< 3.5 pts — Alerta en trabajo en equipo o comunicación', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Número de Hallazgos en Auditorías Internas/Externas',
                            'definition' => 'Calidad del trabajo contable evidenciada en revisiones de auditoría interna, revisoría fiscal y control interno. Meta: 0 hallazgos.',
                            'formula' => 'Numero_Hallazgos_Auditoria',
                            'unit' => 'hallazgos',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Numero_Hallazgos_Auditoria', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 0.0, 'qualification' => '0 hallazgos — Cumplimiento total y excelente calidad documental contable', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 1.0, 'max_value' => 2.0, 'qualification' => '1 – 2 hallazgos menores — Riesgo bajo con correcciones puntuales', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 2.01, 'max_value' => 1000, 'qualification' => '≥ 3 hallazgos — Deficiencia significativa en procesos', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],

                // BLOQUE D: Análisis y Verificación (30%)
                [
                    'name' => 'D. Análisis y Verificación',
                    'description' => 'Mide la exactitud en la verificación y liquidación de comisiones de ventas (100%), oportunidad en la ejecución de conciliaciones bancarias (100%) y completitud/archivo de soportes en Drive (100%).',
                    'formula' => 'Promedio de comisiones sin error, conciliaciones a tiempo y archivo documental',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'D. Análisis y Verificación',
                    'weight' => 30,
                    'incidence' => 30,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Porcentaje de Comisiones de Ventas Verificadas sin Errores',
                            'definition' => 'Exactitud y confiabilidad en la detección de inconsistencias antes de aprobar pagos de comisiones comerciales. Meta: 100%.',
                            'formula' => '(Comisiones_Sin_Errores / Total_Comisiones_Verificadas) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Comisiones_Sin_Errores', 'value' => 0],
                                ['name' => 'Total_Comisiones_Verificadas', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Control total, detección oportuna de inconsistencias sin pagos erróneos', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Algún error mínimo en revisión subsanado oportunamente', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Inconsistencias no detectadas con riesgo operativo', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => '% Conciliaciones Bancarias Realizadas a Tiempo',
                            'definition' => 'Cumplimiento en la ejecución oportuna de conciliaciones bancarias (operativas antes del día 20, fiducias, fondo reserva y tarjeta antes del día 30). Meta: 100%.',
                            'formula' => '(Conciliaciones_Bancarias_ATiempo / Total_Conciliaciones_Bancarias) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Conciliaciones_Bancarias_ATiempo', 'value' => 0],
                                ['name' => 'Total_Conciliaciones_Bancarias', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Todas las cuentas conciliadas dentro de los plazos establecidos', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Conciliaciones con retrasos justificados menores', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Rezagos en conciliación que afectan cierres contables', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => '% de Soportes de Conciliación Completos y Archivados',
                            'definition' => 'Calidad, trazabilidad y disciplina documental en conciliaciones bancarias reposando completas en Drive antes del día 30. Meta: 100%.',
                            'formula' => '(Conciliaciones_Soportes_Drive_ATiempo / Total_Conciliaciones_Bancarias) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Conciliaciones_Soportes_Drive_ATiempo', 'value' => 0],
                                ['name' => 'Total_Conciliaciones_Bancarias', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Totalidad de soportes archivados en Drive dentro del plazo', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Soportes cargados con leve retraso justificado', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Falta de soportes o trazabilidad en auditoría', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],
            ];

            foreach ($kpisAnalista as $kpiData) {
                $userAnalista->kpis()->updateOrCreate(
                    ['name' => $kpiData['name']],
                    $kpiData
                );
            }
        }

        // =========================================================================
        // KPIS: ANALISTA DE TESORERÍA (CC: 1035414229 - Claudia Patricia Jimenez Carvajal)
        // =========================================================================
        $userClaudia = $instantiatedUsers['1035414229'] ?? null;
        if ($userClaudia) {
            $kpisClaudia = [
                // BLOQUE A: Gestión de Egresos y Pagos (50%)
                [
                    'name' => 'A. Gestión de Egresos y Pagos',
                    'description' => 'Mide la oportunidad en la elaboración de comprobantes de egreso (≤ 1 día hábil entre pago y egreso) y exactitud/calidad en la elaboración de comprobantes de egreso sin errores.',
                    'formula' => 'Promedio de tiempos de egreso y exactitud en comprobantes',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'A. Gestión de Egresos y Pagos',
                    'weight' => 50,
                    'incidence' => 50,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Tiempo de Elaboración del Comprobante de Egreso',
                            'definition' => 'Evalúa cuántos días hábiles pasan entre la fecha en que se realiza el pago y la fecha en que se elabora el comprobante de egreso. Meta: Promedio mensual ≤ 1 día hábil.',
                            'formula' => 'Tiempo_Promedio_Pago_A_Egreso_Dias',
                            'unit' => 'días hábiles',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Tiempo_Promedio_Pago_A_Egreso_Dias', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 1.0, 'qualification' => '≤ 1 día hábil — Excelente disciplina operativa y control de flujo', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Riesgo Moderado (Amarillo)', 'min_value' => 1.01, 'max_value' => 2.0, 'qualification' => '1.01 – 2 días hábiles — Riesgo moderado de rezago', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 2.01, 'max_value' => 1000, 'qualification' => '> 2 días hábiles — Retrasos críticos en registro de egresos', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Número de Errores en Comprobantes de Egreso',
                            'definition' => 'Calidad y exactitud en la elaboración de comprobantes de egreso (proveedor, valor, concepto, retenciones y correspondencia con pagos). Meta: 0–1 error.',
                            'formula' => 'Numero_Errores_Comprobantes_Egreso',
                            'unit' => 'errores',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Numero_Errores_Comprobantes_Egreso', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Excelente (Verde)', 'min_value' => 0, 'max_value' => 1.0, 'qualification' => '0 – 1 error — Control sólido y alta precisión contable', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 2.0, 'max_value' => 3.0, 'qualification' => '2 – 3 errores — Fallas puntuales, requiere seguimiento', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 3.01, 'max_value' => 1000, 'qualification' => '≥ 4 errores — Alto riesgo operativo y reprocesos', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],

                // BLOQUE B: Gestión de Ingresos y Registros Bancarios (40%)
                [
                    'name' => 'B. Gestión de Ingresos y Registros Bancarios',
                    'description' => 'Mide la exactitud, oportunidad y actualización diaria del libro de bancos (100% al día siguiente) y resolución oportuna de discrepancias en conciliaciones bancarias.',
                    'formula' => 'Promedio de conciliación diaria de bancos y resolución de discrepancias bancarias',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'B. Gestión de Ingresos y Registros Bancarios',
                    'weight' => 40,
                    'incidence' => 40,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Porcentaje de Conciliación Diario del Libro de Bancos',
                            'definition' => 'Medir la oportunidad en el registro y conciliación de movimientos bancarios al día siguiente de cada movimiento. Meta: 100%.',
                            'formula' => '(Dias_Conciliados_Oportunamente / Total_Dias_Movimiento_Bancario) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Dias_Conciliados_Oportunamente', 'value' => 0],
                                ['name' => 'Total_Dias_Movimiento_Bancario', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Cumplimiento diario perfecto sin rezagos', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 95, 'max_value' => 99.99, 'qualification' => '95% – 99.99% — Ligeros rezagos, requiere seguimiento', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 94.99, 'qualification' => '< 95% — Acumulación o atrasos en control bancario', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Número de Discrepancias No Resueltas en Conciliaciones',
                            'definition' => 'Cantidad de diferencias identificadas en la conciliación bancaria no aclaradas o corregidas al cierre del periodo. Meta: 0.',
                            'formula' => 'Numero_Discrepancias_No_Resueltas',
                            'unit' => 'discrepancias',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Numero_Discrepancias_No_Resueltas', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Excelente (Verde)', 'min_value' => 0, 'max_value' => 0.0, 'qualification' => '0 discrepancias — Control bancario excelente y conciliaciones exactas', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 1.0, 'max_value' => 1.0, 'qualification' => '1 discrepancia — Riesgo bajo con seguimiento puntual', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 1.01, 'max_value' => 1000, 'qualification' => '≥ 2 discrepancias — Fallas en control bancario o inconsistencias', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],

                // BLOQUE C: Soporte y Control (10%)
                [
                    'name' => 'C. Soporte y Control',
                    'description' => 'Mide la entrega oportuna de extractos bancarios a contabilidad (día 5), envío de soportes de pago (< 24h), índice de envío de comprobantes (IEC) a obra, clima laboral (360°) y respuesta a auditorías.',
                    'formula' => 'Promedio de entrega de extractos, soportes de pago, IEC, clima laboral y auditorías',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'C. Soporte y Control',
                    'weight' => 10,
                    'incidence' => 10,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Porcentaje de Extractos Bancarios Entregados a Contabilidad a Tiempo',
                            'definition' => 'Puntualidad en la entrega de extractos bancarios el quinto día hábil del mes para cierres y conciliaciones. Meta: 100%.',
                            'formula' => '(Extractos_Entregados_ATiempo / Total_Extractos_Bancarios) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Extractos_Entregados_ATiempo', 'value' => 0],
                                ['name' => 'Total_Extractos_Bancarios', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Entregados el quinto día hábil o antes', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 80, 'max_value' => 99.99, 'qualification' => '80% – 99.99% — Retraso menor justificado', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — Extemporaneidad que afecta el cierre contable', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Envío de soportes de pago a tiempo',
                            'definition' => 'Agilidad en el envío de soportes de pago (comprobantes de egreso) dentro de las 24 horas siguientes al pago. Meta: 100%.',
                            'formula' => '(Soportes_Enviados_ATiempo / Total_Soportes_Mes) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Soportes_Enviados_ATiempo', 'value' => 0],
                                ['name' => 'Total_Soportes_Mes', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Soportes enviados dentro de las 24 horas hábiles', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 80, 'max_value' => 99.99, 'qualification' => '80% – 99.99% — Rezagos menores en remisión', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — Demoras que afectan trazabilidad ante proveedores', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Índice de Envío de Comprobantes (IEC)',
                            'definition' => 'Garantizar que el 100% de los comprobantes de pago generados sean remitidos oportunamente al área técnica para su difusión en obra.',
                            'formula' => '(Comprobantes_Enviados_ATecnica / Total_Pagos_Realizados) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Comprobantes_Enviados_ATecnica', 'value' => 0],
                                ['name' => 'Total_Pagos_Realizados', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Totalidad de comprobantes enviados al área técnica a tiempo', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Desviación leve con regularización rápida', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Afecta la trazabilidad y difusión en campo', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Resultados de encuestas de clima laboral (Evaluación 360°)',
                            'definition' => 'Evaluación 360° de competencias, trabajo en equipo, amabilidad y clima laboral (escala sobre 5.0). Meta: ≥ 4.5 pts.',
                            'formula' => 'Puntaje_Evaluacion_360',
                            'unit' => 'pts',
                            'parameters' => [
                                ['name' => 'Puntaje_Evaluacion_360', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Sobresaliente (Verde)', 'min_value' => 4.5, 'max_value' => 5.0, 'qualification' => '≥ 4.5 pts — Desempeño sólido, empático y colaborativo', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 3.5, 'max_value' => 4.49, 'qualification' => '3.5 – 4.49 pts — Desempeño adecuado con oportunidades de mejora', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 3.49, 'qualification' => '< 3.5 pts — Oportunidad de fortalecimiento en clima laboral', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Tiempo de Respuesta a Requerimientos de Auditoría/Cierres Contables',
                            'definition' => 'Agilidad en la provisión de información, estados de cuenta y soportes requeridos en menos de 24 horas. Meta: 100%.',
                            'formula' => '(Requerimientos_Respondidos_Dentro_24h / Total_Requerimientos_Mes) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Requerimientos_Respondidos_Dentro_24h', 'value' => 0],
                                ['name' => 'Total_Requerimientos_Mes', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Respuesta oportuna dentro de las 24 horas', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 80, 'max_value' => 99.99, 'qualification' => '80% – 99.99% — Respuesta con requerimientos complejos justificados', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — Demoras en entrega de información contable', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],
            ];

            foreach ($kpisClaudia as $kpiData) {
                $userClaudia->kpis()->updateOrCreate(
                    ['name' => $kpiData['name']],
                    $kpiData
                );
            }
        }

        // =========================================================================
        // KPIS: ANALISTA DE TESORERÍA (CC: 1065917552 - Sharon Jolaine Velandia Tellez)
        // =========================================================================
        $userSharon = $instantiatedUsers['1065917552'] ?? null;
        if ($userSharon) {
            $kpisSharon = [
                // BLOQUE A: Gestión de Egresos y Pagos (50%)
                [
                    'name' => 'A. Gestión de Egresos y Pagos',
                    'description' => 'Mide la oportunidad en la elaboración de comprobantes de egreso (≤ 1 día hábil), exactitud y ausencia de errores en comprobantes, tiempo del flujo de facturación, radicación en RADIAN y documentos soporte.',
                    'formula' => 'Promedio de tiempos de egreso, exactitud en comprobantes, facturación, RADIAN y documentos soporte',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'A. Gestión de Egresos y Pagos',
                    'weight' => 50,
                    'incidence' => 50,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Tiempo de Elaboración del Comprobante de Egreso',
                            'definition' => 'Evalúa cuántos días hábiles pasan entre la fecha en que se realiza el pago y la fecha en que se elabora el comprobante de egreso. Meta: Promedio mensual ≤ 1 día hábil.',
                            'formula' => 'Tiempo_Promedio_Pago_A_Egreso_Dias',
                            'unit' => 'días hábiles',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Tiempo_Promedio_Pago_A_Egreso_Dias', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 1.0, 'qualification' => '≤ 1 día hábil — Excelente disciplina operativa y control de flujo', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Riesgo Moderado (Amarillo)', 'min_value' => 1.01, 'max_value' => 2.0, 'qualification' => '1.01 – 2 días hábiles — Riesgo moderado de rezago', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 2.01, 'max_value' => 1000, 'qualification' => '> 2 días hábiles — Retrasos críticos en registro de egresos', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Número de Errores en Comprobantes de Egreso',
                            'definition' => 'Calidad y exactitud en la elaboración de comprobantes de egreso (proveedor, valor, concepto, retenciones y correspondencia con pagos). Meta: 0–1 error.',
                            'formula' => 'Numero_Errores_Comprobantes_Egreso',
                            'unit' => 'errores',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Numero_Errores_Comprobantes_Egreso', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Excelente (Verde)', 'min_value' => 0, 'max_value' => 1.0, 'qualification' => '0 – 1 error — Control sólido y alta precisión contable', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 2.0, 'max_value' => 3.0, 'qualification' => '2 – 3 errores — Fallas puntuales, requiere seguimiento', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 3.01, 'max_value' => 1000, 'qualification' => '≥ 4 errores — Alto riesgo operativo y reprocesos', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Tiempo Total del Proceso de Facturación',
                            'definition' => 'Evalúa la eficiencia del proceso interno de registro y validación de facturas, asegurando que la carga al Drive y flujo no exceda los tiempos definidos (Drive ≤ 1 día hábil).',
                            'formula' => 'Tiempo_Total_Proceso_Facturacion_Dias',
                            'unit' => 'días hábiles',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Tiempo_Total_Proceso_Facturacion_Dias', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 6.0, 'qualification' => '≤ 6 días hábiles — Proceso fluido y dentro de tiempos', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 6.01, 'max_value' => 8.0, 'qualification' => '6.1 – 8 días hábiles — Desviación moderada en flujo', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 8.01, 'max_value' => 1000, 'qualification' => '> 8 días hábiles — Retrasos significativos en el ciclo', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Porcentaje de Facturas Registradas en RADIAN a Tiempo',
                            'definition' => 'Cumplimiento de plazos en el registro y eventos de facturas electrónicas en RADIAN (3 días hábiles). Meta: 100%.',
                            'formula' => '(Facturas_Registradas_ATiempo_RADIAN / Total_Facturas_A_Registrar_RADIAN) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Facturas_Registradas_ATiempo_RADIAN', 'value' => 0],
                                ['name' => 'Total_Facturas_A_Registrar_RADIAN', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Registro oportuno de todas las facturas en RADIAN', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Cumplimiento adecuado con rezagos mínimos', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Riesgo de aceptación tácita indebida', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Tiempo Promedio de Emisión de Documentos Soporte',
                            'definition' => 'Rapidez en la elaboración de documentos soporte electrónicos a personas naturales (hasta el sexto día hábil tras cuenta de cobro). Meta: ≤ 6 días.',
                            'formula' => 'Tiempo_Promedio_Emision_DocSoporte_Dias',
                            'unit' => 'días',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Tiempo_Promedio_Emision_DocSoporte_Dias', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 6.0, 'qualification' => '≤ 6 días — Emisión ágil dentro del marco legal', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 6.01, 'max_value' => 8.0, 'qualification' => '6.1 – 8 días — Trámite con novedades en validación', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 8.01, 'max_value' => 1000, 'qualification' => '> 8 días — Retrasos que afectan soporte de costos', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],

                // BLOQUE B: Gestión de Ingresos y Registros Bancarios (40%)
                [
                    'name' => 'B. Gestión de Ingresos y Registros Bancarios',
                    'description' => 'Mide la exactitud, oportunidad y actualización diaria del libro de bancos (100% al día siguiente) y resolución oportuna de discrepancias en conciliaciones bancarias.',
                    'formula' => 'Promedio de conciliación diaria de bancos y resolución de discrepancias bancarias',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'B. Gestión de Ingresos y Registros Bancarios',
                    'weight' => 40,
                    'incidence' => 40,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Porcentaje de Conciliación Diario del Libro de Bancos',
                            'definition' => 'Medir la oportunidad en el registro y conciliación de movimientos bancarios al día siguiente de cada movimiento. Meta: 100%.',
                            'formula' => '(Dias_Conciliados_Oportunamente / Total_Dias_Movimiento_Bancario) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Dias_Conciliados_Oportunamente', 'value' => 0],
                                ['name' => 'Total_Dias_Movimiento_Bancario', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Cumplimiento diario perfecto sin rezagos', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 95, 'max_value' => 99.99, 'qualification' => '95% – 99.99% — Ligeros rezagos, requiere seguimiento', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 94.99, 'qualification' => '< 95% — Acumulación o atrasos en control bancario', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Número de Discrepancias No Resueltas en Conciliaciones',
                            'definition' => 'Cantidad de diferencias identificadas en la conciliación bancaria no aclaradas o corregidas al cierre del periodo. Meta: 0.',
                            'formula' => 'Numero_Discrepancias_No_Resueltas',
                            'unit' => 'discrepancias',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Numero_Discrepancias_No_Resueltas', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Excelente (Verde)', 'min_value' => 0, 'max_value' => 0.0, 'qualification' => '0 discrepancias — Control bancario excelente y conciliaciones exactas', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 1.0, 'max_value' => 1.0, 'qualification' => '1 discrepancia — Riesgo bajo con seguimiento puntual', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 1.01, 'max_value' => 1000, 'qualification' => '≥ 2 discrepancias — Fallas en control bancario o inconsistencias', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],

                // BLOQUE C: Soporte y Control (10%)
                [
                    'name' => 'C. Soporte y Control',
                    'description' => 'Mide la entrega oportuna de extractos bancarios a contabilidad (día 5), envío de soportes de pago (< 24h), índice de envío de comprobantes (IEC) a obra, clima laboral (360°) y respuesta a auditorías.',
                    'formula' => 'Promedio de entrega de extractos, soportes de pago, IEC, clima laboral y auditorías',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'C. Soporte y Control',
                    'weight' => 10,
                    'incidence' => 10,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Porcentaje de Extractos Bancarios Entregados a Contabilidad a Tiempo',
                            'definition' => 'Puntualidad en la entrega de extractos bancarios el quinto día hábil del mes para cierres y conciliaciones. Meta: 100%.',
                            'formula' => '(Extractos_Entregados_ATiempo / Total_Extractos_Bancarios) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Extractos_Entregados_ATiempo', 'value' => 0],
                                ['name' => 'Total_Extractos_Bancarios', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Entregados el quinto día hábil o antes', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 80, 'max_value' => 99.99, 'qualification' => '80% – 99.99% — Retraso menor justificado', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — Extemporaneidad que afecta el cierre contable', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Envío de soportes de pago a tiempo',
                            'definition' => 'Agilidad en el envío de soportes de pago (comprobantes de egreso) dentro de las 24 horas siguientes al pago. Meta: 100%.',
                            'formula' => '(Soportes_Enviados_ATiempo / Total_Soportes_Mes) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Soportes_Enviados_ATiempo', 'value' => 0],
                                ['name' => 'Total_Soportes_Mes', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Soportes enviados dentro de las 24 horas hábiles', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 80, 'max_value' => 99.99, 'qualification' => '80% – 99.99% — Rezagos menores en remisión', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — Demoras que afectan trazabilidad ante proveedores', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Índice de Envío de Comprobantes (IEC)',
                            'definition' => 'Garantizar que el 100% de los comprobantes de pago generados sean remitidos oportunamente al área técnica para su difusión en obra.',
                            'formula' => '(Comprobantes_Enviados_ATecnica / Total_Pagos_Realizados) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Comprobantes_Enviados_ATecnica', 'value' => 0],
                                ['name' => 'Total_Pagos_Realizados', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Totalidad de comprobantes enviados al área técnica a tiempo', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Desviación leve con regularización rápida', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Afecta la trazabilidad y difusión en campo', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Resultados de encuestas de clima laboral (Evaluación 360°)',
                            'definition' => 'Evaluación 360° de competencias, trabajo en equipo, amabilidad y clima laboral (escala sobre 5.0). Meta: ≥ 4.5 pts.',
                            'formula' => 'Puntaje_Evaluacion_360',
                            'unit' => 'pts',
                            'parameters' => [
                                ['name' => 'Puntaje_Evaluacion_360', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Sobresaliente (Verde)', 'min_value' => 4.5, 'max_value' => 5.0, 'qualification' => '≥ 4.5 pts — Desempeño sólido, empático y colaborativo', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 3.5, 'max_value' => 4.49, 'qualification' => '3.5 – 4.49 pts — Desempeño adecuado con oportunidades de mejora', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 3.49, 'qualification' => '< 3.5 pts — Oportunidad de fortalecimiento en clima laboral', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Tiempo de Respuesta a Requerimientos de Auditoría/Cierres Contables',
                            'definition' => 'Agilidad en la provisión de información, estados de cuenta y soportes requeridos en menos de 24 horas. Meta: 100%.',
                            'formula' => '(Requerimientos_Respondidos_Dentro_24h / Total_Requerimientos_Mes) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Requerimientos_Respondidos_Dentro_24h', 'value' => 0],
                                ['name' => 'Total_Requerimientos_Mes', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Respuesta oportuna dentro de las 24 horas', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 80, 'max_value' => 99.99, 'qualification' => '80% – 99.99% — Respuesta con requerimientos complejos justificados', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — Demoras en entrega de información contable', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],
            ];

            foreach ($kpisSharon as $kpiData) {
                $userSharon->kpis()->updateOrCreate(
                    ['name' => $kpiData['name']],
                    $kpiData
                );
            }
        }

        // =========================================================================
        // KPIS: ANALISTA TALENTO HUMANO (CC: 1020437864 - Vanessa Calle Valderrama)
        // =========================================================================
        $userVanessa = $instantiatedUsers['1020437864'] ?? null;
        if ($userVanessa) {
            $kpisVanessa = [
                // BLOQUE A: Gestión de Talento Humano (60%)
                [
                    'name' => 'A. Gestión de Talento Humano',
                    'description' => 'Mide la eficiencia en reclutamiento y selección, tiempo para cubrir vacantes (≤ 30 días hábiles), retención del nuevo personal, nómina sin errores, pago oportuno de PILA, liquidaciones oportunas y cumplimiento documental de expedientes.',
                    'formula' => 'Promedio de selección, nómina, seguridad social, liquidaciones y control documental',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'A. Gestión de Talento Humano',
                    'weight' => 60,
                    'incidence' => 60,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Tiempo Promedio para Cubrir Vacantes',
                            'definition' => 'Tiempo en días hábiles desde el lanzamiento/solicitud formal de la vacante hasta el ingreso del candidato. Meta: ≤ 30 días hábiles.',
                            'formula' => 'Tiempo_Promedio_Cubrir_Vacantes_Dias',
                            'unit' => 'días hábiles',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Tiempo_Promedio_Cubrir_Vacantes_Dias', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Excelente (Verde)', 'min_value' => 0, 'max_value' => 30.0, 'qualification' => '≤ 30 días hábiles — Reclutamiento ágil y oportuno sin afectar operación', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Riesgo Moderado (Amarillo)', 'min_value' => 30.01, 'max_value' => 40.0, 'qualification' => '31 – 40 días hábiles — Retrasos puntuales que requieren seguimiento', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 40.01, 'max_value' => 1000, 'qualification' => '> 40 días hábiles — Retraso significativo y riesgo operativo', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => '% Cumplimiento de Procesos de Selección',
                            'definition' => 'Efectividad en completar las etapas de los procesos de selección asignados en el periodo (publicación, pruebas, entrevistas, contratación). Meta: ≥ 95%.',
                            'formula' => '(Procesos_Completados / Total_Procesos_Asignados) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Procesos_Completados', 'value' => 0],
                                ['name' => 'Total_Procesos_Asignados', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Excelente (Verde)', 'min_value' => 95, 'max_value' => 1000, 'qualification' => '≥ 95% — Cumplimiento óptimo y gestión a tiempo', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 85, 'max_value' => 94.99, 'qualification' => '85% – 94.99% — Cumplimiento adecuado con margen de mejora', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 84.99, 'qualification' => '< 85% — Incumplimiento significativo en cierre de vacantes', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Índice de Retención del Nuevo Personal',
                            'definition' => 'Capacidad de retención de colaboradores recién vinculados superando el periodo crítico de adaptación (≥ 90% a 3 meses, ≥ 85% a 6 meses).',
                            'formula' => '(Trabajadores_Permanecen / Total_Trabajadores_Vinculados) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Trabajadores_Permanecen', 'value' => 0],
                                ['name' => 'Total_Trabajadores_Vinculados', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 90, 'max_value' => 1000, 'qualification' => '≥ 90% — Retención sólida y procesos de inducción efectivos', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Alerta (Amarillo)', 'min_value' => 80, 'max_value' => 89.99, 'qualification' => '80% – 89.99% — Señales de alerta en rotación temprana', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — Rotación alta que requiere plan de acción inmediato', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => '% de Nóminas Procesadas sin Errores',
                            'definition' => 'Precisión y confiabilidad en la liquidación de nómina mensual sin inconsistencias en salarios, novedades ni deducciones. Meta: ≥ 99.5%.',
                            'formula' => '(Nominas_Sin_Errores / Total_Nominas_Procesadas) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Nominas_Sin_Errores', 'value' => 0],
                                ['name' => 'Total_Nominas_Procesadas', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Excelente (Verde)', 'min_value' => 99.5, 'max_value' => 1000, 'qualification' => '≥ 99.5% — Nómina exacta y sin inconsistencias', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 97, 'max_value' => 99.49, 'qualification' => '97% – 99.49% — Novedades subsanadas antes del pago', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 96.99, 'qualification' => '< 97% — Errores que afectan cumplimiento legal o clima laboral', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => '% Pago Oportuno de Seguridad Social (PILA)',
                            'definition' => 'Cumplimiento en el pago de aportes a Seguridad Social Integral dentro del plazo legal (quinto día hábil de cada mes). Meta: 100%.',
                            'formula' => '(Pagos_PILA_ATiempo / Total_Pagos_PILA) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Pagos_PILA_ATiempo', 'value' => 0],
                                ['name' => 'Total_Pagos_PILA', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Pagos realizados oportunamente dentro del plazo legal', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 95, 'max_value' => 99.99, 'qualification' => '95% – 99.99% — Pago dentro de plazos con trámite menor', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 94.99, 'qualification' => '< 95% — Riesgo de pérdida de cobertura o sanciones', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Tiempo Promedio de Liquidación de Prestaciones/Vacaciones',
                            'definition' => 'Agilidad en realizar la liquidación de prestaciones sociales o vacaciones (≤ 3 días hábiles desde solicitud o retiro).',
                            'formula' => 'Tiempo_Promedio_Liquidaciones_Dias',
                            'unit' => 'días hábiles',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Tiempo_Promedio_Liquidaciones_Dias', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 3.0, 'qualification' => '≤ 3 días hábiles — Liquidación ágil y completa con soportes', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 3.01, 'max_value' => 5.0, 'qualification' => '3.1 – 5 días hábiles — Trámite en gestión con retraso menor', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 5.01, 'max_value' => 1000, 'qualification' => '> 5 días hábiles — Retraso que genera riesgo legal o administrativo', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Nivel de Cumplimiento Documental en Vinculación',
                            'definition' => 'Porcentaje de colaboradores con expediente completo y archivado (físico y digital) dentro de los 3 días hábiles posteriores a la firma. Meta: 100%.',
                            'formula' => '(Expedientes_Completos / Total_Colaboradores_Vinculados) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Expedientes_Completos', 'value' => 0],
                                ['name' => 'Total_Colaboradores_Vinculados', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 95, 'max_value' => 1000, 'qualification' => '≥ 95% — Expedientes completos con todos los soportes legales', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 85, 'max_value' => 94.99, 'qualification' => '85% – 94.99% — Documentación en recolección activa', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 84.99, 'qualification' => '< 85% — Expedientes incompletos o sin archivo adecuado', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Nivel de Cumplimiento del Proceso de Desvinculación Laboral',
                            'definition' => 'Garantizar que ante retiros el proceso de desvinculación cuente con todos los documentos obligatorios (renuncia/despido, liquidación, paz y salvo, exámenes egreso) dentro de 3 días. Meta: 100%.',
                            'formula' => '(Desvinculaciones_Completas / Total_Desvinculaciones) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Desvinculaciones_Completas', 'value' => 0],
                                ['name' => 'Total_Desvinculaciones', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Desvinculaciones cerradas con soporte integral', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 85, 'max_value' => 99.99, 'qualification' => '85% – 99.99% — Documentos en trámite de firma', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 84.99, 'qualification' => '< 85% — Procesos abiertos o sin exámenes/soportes obligatorios', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Resultados de encuestas de clima laboral (Evaluación 360°)',
                            'definition' => 'Satisfacción del equipo y evaluación 360° de competencias y clima organizacional (escala sobre 5.0). Meta: ≥ 4.5 pts.',
                            'formula' => 'Puntaje_Evaluacion_360',
                            'unit' => 'pts',
                            'parameters' => [
                                ['name' => 'Puntaje_Evaluacion_360', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Sobresaliente (Verde)', 'min_value' => 4.5, 'max_value' => 5.0, 'qualification' => '≥ 4.5 pts — Desempeño sólido, empático y colaborativo', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 3.5, 'max_value' => 4.49, 'qualification' => '3.5 – 4.49 pts — Desempeño adecuado con oportunidades de mejora', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 3.49, 'qualification' => '< 3.5 pts — Oportunidad de fortalecimiento en clima laboral', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Tiempo Promedio de Respuesta a Solicitudes de Empleados',
                            'definition' => 'Rapidez para atender y resolver solicitudes de empleados (certificaciones, novedades, soportes) en un tiempo ≤ 72 horas (3 días hábiles).',
                            'formula' => 'Tiempo_Promedio_Respuesta_Solicitudes_Dias',
                            'unit' => 'días hábiles',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Tiempo_Promedio_Respuesta_Solicitudes_Dias', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 3.0, 'qualification' => '≤ 3 días hábiles — Respuesta ágil y servicio oportuno al colaborador', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 3.01, 'max_value' => 5.0, 'qualification' => '3.1 – 5 días hábiles — Atención con requerimientos especiales en curso', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 5.01, 'max_value' => 1000, 'qualification' => '> 5 días hábiles — Demora en trámites de personal', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],

                // BLOQUE B: Gestión Administrativa (40%)
                [
                    'name' => 'B. Gestión Administrativa',
                    'description' => 'Mide la oportunidad en el envío de certificados de retención (≤ 2 días hábiles), programación de mensajería (≥ 95%), disponibilidad de insumos de oficina (100%), control de activos con acta (100%) y felicitaciones corporativas.',
                    'formula' => 'Promedio de certificados de retención, mensajería, insumos, activos y bienestar organizacional',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'B. Gestión Administrativa',
                    'weight' => 40,
                    'incidence' => 40,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Envío de Certificados de Retención',
                            'definition' => 'Oportunidad y eficiencia en la entrega de certificados de retención solicitados por proveedores o contratistas (máximo 2 días hábiles). Meta: 100%.',
                            'formula' => '(Certificados_Enviados_ATiempo / Total_Certificados_Solicitados) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Certificados_Enviados_ATiempo', 'value' => 0],
                                ['name' => 'Total_Certificados_Solicitados', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Enviados dentro de los 2 días hábiles', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Parcial (Amarillo)', 'min_value' => 80, 'max_value' => 99.99, 'qualification' => '80% – 99.99% — Rezagos menores justificados', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Crítico (Rojo)', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — Retrasos que afectan soportes tributarios de terceros', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Cumplimiento de Programación de Mensajería',
                            'definition' => 'Correcta programación, seguimiento y cumplimiento de los servicios de mensajería requeridos por la organización. Meta: ≥ 95%.',
                            'formula' => '(Servicios_Mensajeria_Cumplidos / Total_Servicios_Programados) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Servicios_Mensajeria_Cumplidos', 'value' => 0],
                                ['name' => 'Total_Servicios_Programados', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 95, 'max_value' => 1000, 'qualification' => '≥ 95% — Servicios ejecutados oportunamente según cronograma', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 80, 'max_value' => 94.99, 'qualification' => '80% – 94.99% — Reprogramaciones menores justificadas', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — Fallas recurrentes en rutas y entregas', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Disponibilidad de Insumos de Oficina',
                            'definition' => 'Garantizar la disponibilidad continua de insumos necesarios para la operación (papelería, aseo, cafetería) sin quiebres de stock. Meta: 100%.',
                            'formula' => '(Dias_Sin_Faltantes_Insumos / Total_Dias_Periodo) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Dias_Sin_Faltantes_Insumos', 'value' => 0],
                                ['name' => 'Total_Dias_Periodo', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Abastecimiento permanente y oportuno', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Quiebres puntuales resueltos rápidamente', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Desabastecimiento que afecta la operación diaria', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Control de Entrega y Registro de Activos (Equipos, dotación, etc.)',
                            'definition' => 'Garantizar que todos los activos entregados a colaboradores (computadores, celulares, dotación) cuenten con acta firmada y registro en Drive. Meta: 100%.',
                            'formula' => '(Activos_Con_Soporte_Firmado / Total_Activos_Entregados) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Activos_Con_Soporte_Firmado', 'value' => 0],
                                ['name' => 'Total_Activos_Entregados', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Totalidad de activos con acta formal de entrega debidamente firmada', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Actas en proceso de recolección de firmas', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Entrega de equipos sin soporte ni trazabilidad', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Cumplimiento de Felicitaciones y Mensajes Corporativos (Bienestar y Cultura)',
                            'definition' => 'Envío oportuno de mensajes corporativos (cumpleaños, días especiales, profesiones, ingresos, egresos) como parte de la estrategia de bienestar y cultura. Meta: 100%.',
                            'formula' => '(Eventos_Corporativos_Gestionados / Total_Eventos_Programados) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Eventos_Corporativos_Gestionados', 'value' => 0],
                                ['name' => 'Total_Eventos_Programados', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Difusión puntual de fechas especiales y mensajes institucionales', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Cumplimiento adecuado con omisiones aisladas', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Descuido en la estrategia de bienestar y comunicación', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],
            ];

            foreach ($kpisVanessa as $kpiData) {
                $userVanessa->kpis()->updateOrCreate(
                    ['name' => $kpiData['name']],
                    $kpiData
                );
            }
        }
    }
}
