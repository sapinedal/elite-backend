<?php

namespace Database\Seeders;

use App\Http\Modules\Configuracion\Models\Area;
use App\Http\Modules\Configuracion\Models\Position;
use App\Http\Modules\Users\Models\User;
use App\Http\Modules\Plantillas\Models\KPI;
use Illuminate\Database\Seeder;

class MercadeoAreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Asegurar el Área de Mercadeo sin duplicar si ya existe
        $mercadeoArea = Area::whereRaw('LOWER(name) IN (?, ?, ?)', ['mercadeo', 'marketing', 'área de mercadeo'])->first()
            ?? Area::firstOrCreate(
                ['name' => 'Mercadeo'],
                ['description' => 'Área encargada de la estrategia de marketing, adquisición digital, posicionamiento de marca y eventos']
            );

        // 2. Cargos pertenecientes al Área de Mercadeo
        $positions = [
            'Directora de Mercadeo y Publicidad',
            'Analista de Mercadeo',
            'Coordinador de Mercadeo',
        ];

        $createdPositions = [];
        foreach ($positions as $posName) {
            $createdPositions[$posName] = Position::where('area_id', $mercadeoArea->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($posName)])
                ->first()
                ?? Position::whereRaw('LOWER(name) = ?', [mb_strtolower($posName)])->first()
                ?? Position::firstOrCreate([
                    'name' => $posName,
                    'area_id' => $mercadeoArea->id
                ]);
        }

        // =========================================================================
        // USUARIOS DEL ÁREA DE MERCADEO
        // =========================================================================
        $colaboradores = [
            [
                'first_name' => 'MANUELA MARIA',
                'last_name' => 'MEJIA GOMEZ',
                'name' => 'MANUELA MARIA MEJIA GOMEZ',
                'document' => '1152193831',
                'position_name' => 'Directora de Mercadeo y Publicidad',
                'email' => 'mmejia@cybproject.com',
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

            $position = $createdPositions[$colab['position_name']] ?? $createdPositions['Directora de Mercadeo y Publicidad'];

            if (!$user) {
                $user = User::create([
                    'first_name' => mb_strtoupper($colab['first_name'], 'UTF-8'),
                    'last_name' => mb_strtoupper($colab['last_name'], 'UTF-8'),
                    'name' => mb_strtoupper($colab['name'], 'UTF-8'),
                    'document' => $colab['document'],
                    'position_id' => $position->id,
                    'area_id' => $mercadeoArea->id,
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
                    'area_id' => $mercadeoArea->id,
                    'email' => $user->email ?: trim($colab['email']),
                ]);
            }

            $instantiatedUsers[$colab['document']] = $user;
        }

        // =========================================================================
        // KPIS: DIRECTORA DE MERCADEO Y PUBLICIDAD (CC: 1152193831 - Manuela Maria Mejia Gomez)
        // =========================================================================
        $userManuela = $instantiatedUsers['1152193831'] ?? null;
        if ($userManuela) {
            $kpisManuela = [
                // BLOQUE A: Gestión de Leads (20%)
                [
                    'name' => 'A. Gestión de Leads',
                    'description' => 'Mide la capacidad de atracción y conversión del embudo digital: tráfico web (≥ 10%/mes), registros en formulario (≥ 10%/mes), generación de leads calificados (≥ 200/mes), eficiencia en CPL (≤ $2.000 COP) y tasas de conversión a citas, visitas y ventas (≥ 10%/mes).',
                    'formula' => 'Promedio ponderado de tráfico web, registros, leads calificados, CPL y conversiones del funnel',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'A. Gestión de Leads',
                    'weight' => 20,
                    'incidence' => 20,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Tráfico Página Web - Incremento % Visitas Únicas',
                            'definition' => 'Crecimiento mensual del volumen de visitas únicas al sitio web mediante tráfico orgánico y pauta. Meta: ≥ 10%/mes.',
                            'formula' => 'Incremento_Porcentaje_Visitas_Unicas_Web',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Incremento_Porcentaje_Visitas_Unicas_Web', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 10, 'max_value' => 1000, 'qualification' => '≥ 10% — Crecimiento sólido en captación de tráfico web', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 0, 'max_value' => 9.99, 'qualification' => '0% – 9.99% — Tráfico estable con crecimiento moderado', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => -1000, 'max_value' => -0.01, 'qualification' => '< 0% — Disminución en tráfico web', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Página Web - Registro en el Formulario',
                            'definition' => 'Incremento porcentual mensual en los registros captados a través del formulario de la página web. Meta: ≥ 10%/mes.',
                            'formula' => 'Incremento_Porcentaje_Registros_Formulario_Web',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Incremento_Porcentaje_Registros_Formulario_Web', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 10, 'max_value' => 1000, 'qualification' => '≥ 10% — Alta efectividad en conversión web', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 0, 'max_value' => 9.99, 'qualification' => '0% – 9.99% — Conversión moderada en formularios', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => -1000, 'max_value' => -0.01, 'qualification' => '< 0% — Caída en captura de formularios', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Leads Calificados Generados',
                            'definition' => 'Volumen mensual de prospectos calificados integrados al CRM (Zoho). Meta: ≥ 200 leads/mes.',
                            'formula' => 'Total_Leads_Calificados_Generados',
                            'unit' => 'leads',
                            'parameters' => [
                                ['name' => 'Total_Leads_Calificados_Generados', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 200, 'max_value' => 10000, 'qualification' => '≥ 200 leads/mes — Excelente volumen de oportunidades calificadas', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 150, 'max_value' => 199.99, 'qualification' => '150 – 199 leads — Volumen aceptable para gestión comercial', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 149.99, 'qualification' => '< 150 leads — Volumen insuficiente de prospectos calificados', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'CPL de Campañas (Costo por Lead)',
                            'definition' => 'Eficiencia en la inversión publicitaria para captación de prospectos. Meta: ubicarse por debajo de $2.000 COP por lead.',
                            'formula' => 'Costo_Por_Lead_COP',
                            'unit' => '$',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Costo_Por_Lead_COP', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 2000.0, 'qualification' => '≤ $2.000 COP — Alta rentabilidad y optimización en pauta', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 2000.01, 'max_value' => 3000.0, 'qualification' => '$2.001 – $3.000 COP — Costo controlado dentro del rango operativo', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 3000.01, 'max_value' => 100000, 'qualification' => '> $3.000 COP — Encarecimiento del lead o baja eficiencia', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Conversión de Leads a Citas',
                            'definition' => 'Porcentaje o incremento de leads contactados que se agendan efectivamente para citas comerciales. Meta: ≥ 10%/mes.',
                            'formula' => 'Tasa_Conversion_Leads_A_Citas_Porcentaje',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Tasa_Conversion_Leads_A_Citas_Porcentaje', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 10, 'max_value' => 1000, 'qualification' => '≥ 10% — Alta efectividad en agendamiento de citas', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 5, 'max_value' => 9.99, 'qualification' => '5% – 9.99% — Agendamiento moderado', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 4.99, 'qualification' => '< 5% — Baja tasa de conversión a citas', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Conversión de Leads a Visitas',
                            'definition' => 'Porcentaje o incremento de prospectos que asisten efectivamente a la sala de ventas. Meta: ≥ 10%/mes.',
                            'formula' => 'Tasa_Conversion_Leads_A_Visitas_Porcentaje',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Tasa_Conversion_Leads_A_Visitas_Porcentaje', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 10, 'max_value' => 1000, 'qualification' => '≥ 10% — Alto flujo de visitas efectivas en sala de ventas', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 5, 'max_value' => 9.99, 'qualification' => '5% – 9.99% — Asistencia moderada a sala', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 4.99, 'qualification' => '< 5% — Baja asistencia efectiva a sala', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Ventas por Canales Digitales',
                            'definition' => 'Participación o incremento en ventas cerradas originadas a través de canales digitales. Meta: ≥ 10%/mes.',
                            'formula' => 'Tasa_Ventas_Canales_Digitales_Porcentaje',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Tasa_Ventas_Canales_Digitales_Porcentaje', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 10, 'max_value' => 1000, 'qualification' => '≥ 10% — Gran impacto del canal digital en cierres de ventas', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 5, 'max_value' => 9.99, 'qualification' => '5% – 9.99% — Cierres digitales sostenidos', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 4.99, 'qualification' => '< 5% — Baja conversión final a ventas digitales', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Ventas por Canales Tradicionales',
                            'definition' => 'Participación de ventas provenientes de sala de ventas, vallas, volantes y referidos. Meta: ≥ 10%/mes.',
                            'formula' => 'Tasa_Ventas_Canales_Tradicionales_Porcentaje',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Tasa_Ventas_Canales_Tradicionales_Porcentaje', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 10, 'max_value' => 1000, 'qualification' => '≥ 10% — Buen aporte de canales físicos y publicidad tradicional', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 5, 'max_value' => 9.99, 'qualification' => '5% – 9.99% — Cierres tradicionales moderados', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 4.99, 'qualification' => '< 5% — Baja efectividad en punto físico', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],

                // BLOQUE B: Redes Sociales (40%)
                [
                    'name' => 'B. Redes Sociales',
                    'description' => 'Mide el desempeño de la pauta digital (impresiones, clics, alcance) y el crecimiento orgánico en Facebook e Instagram (alcance, interacciones totales y tasa de engagement ≥ 10%/mes).',
                    'formula' => 'Promedio del desempeño de pauta y métricas orgánicas de Facebook e Instagram',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'B. Redes Sociales',
                    'weight' => 40,
                    'incidence' => 40,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Pauta - Impresiones',
                            'definition' => 'Incremento mensual del volumen de impresiones en campañas de Meta Ads y Google Ads. Meta: ≥ 10%/mes.',
                            'formula' => 'Incremento_Porcentaje_Pauta_Impresiones',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Incremento_Porcentaje_Pauta_Impresiones', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 10, 'max_value' => 10000, 'qualification' => '≥ 10% — Alta exposición y cobertura publicitaria', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 0, 'max_value' => 9.99, 'qualification' => '0% – 9.99% — Impresiones estables', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => -1000, 'max_value' => -0.01, 'qualification' => '< 0% — Disminución en impresiones de pauta', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Pauta - Clics en el Enlace Totales',
                            'definition' => 'Incremento mensual en clics hacia páginas de destino y canales de contacto. Meta: ≥ 10%/mes.',
                            'formula' => 'Incremento_Porcentaje_Pauta_Clics_Enlace',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Incremento_Porcentaje_Pauta_Clics_Enlace', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 10, 'max_value' => 10000, 'qualification' => '≥ 10% — Excelente interés y tráfico derivado de pauta', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 0, 'max_value' => 9.99, 'qualification' => '0% – 9.99% — Volumen de clics constante', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => -1000, 'max_value' => -0.01, 'qualification' => '< 0% — Caída en clics generados por pauta', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Pauta - Alcance',
                            'definition' => 'Incremento mensual en el número de usuarios únicos alcanzados por la pauta digital. Meta: ≥ 10%/mes.',
                            'formula' => 'Incremento_Porcentaje_Pauta_Alcance',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Incremento_Porcentaje_Pauta_Alcance', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 10, 'max_value' => 10000, 'qualification' => '≥ 10% — Cobertura publicitaria en expansión', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 0, 'max_value' => 9.99, 'qualification' => '0% – 9.99% — Alcance publicitario estable', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => -1000, 'max_value' => -0.01, 'qualification' => '< 0% — Caída en alcance de pauta', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Orgánico - Facebook Alcance',
                            'definition' => 'Incremento mensual del alcance de origen orgánico en la página de Facebook. Meta: ≥ 10%/mes.',
                            'formula' => 'Incremento_Porcentaje_Facebook_Alcance_Organico',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Incremento_Porcentaje_Facebook_Alcance_Organico', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 10, 'max_value' => 10000, 'qualification' => '≥ 10% — Crecimiento orgánico destacado en Facebook', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 0, 'max_value' => 9.99, 'qualification' => '0% – 9.99% — Alcance orgánico estable', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => -1000, 'max_value' => -0.01, 'qualification' => '< 0% — Pérdida de alcance orgánico', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Orgánico - Facebook Interacciones Total',
                            'definition' => 'Incremento mensual en interacciones totales (likes, comentarios, compartidos) en Facebook. Meta: ≥ 10%/mes.',
                            'formula' => 'Incremento_Porcentaje_Facebook_Interacciones_Total',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Incremento_Porcentaje_Facebook_Interacciones_Total', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 10, 'max_value' => 50000, 'qualification' => '≥ 10% — Comunidad activa y altamente participativa', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 0, 'max_value' => 9.99, 'qualification' => '0% – 9.99% — Interacciones estables', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => -1000, 'max_value' => -0.01, 'qualification' => '< 0% — Caída en interacciones', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Orgánico - Facebook Engagement (%)',
                            'definition' => 'Tasa de engagement alcanzada sobre los usuarios expuestos en Facebook. Meta: ≥ 10%/mes.',
                            'formula' => 'Tasa_Facebook_Engagement_Porcentaje',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Tasa_Facebook_Engagement_Porcentaje', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 10, 'max_value' => 1000, 'qualification' => '≥ 10% — Alto nivel de conexión y afinidad', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 5, 'max_value' => 9.99, 'qualification' => '5% – 9.99% — Engagement moderado', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 4.99, 'qualification' => '< 5% — Bajo engagement en publicaciones', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Orgánico - Instagram Interacciones del Contenido',
                            'definition' => 'Incremento mensual en interacciones con las publicaciones e historias de Instagram. Meta: ≥ 10%/mes.',
                            'formula' => 'Incremento_Porcentaje_Instagram_Interacciones_Contenido',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Incremento_Porcentaje_Instagram_Interacciones_Contenido', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 10, 'max_value' => 10000, 'qualification' => '≥ 10% — Contenido atractivo con alta interacción', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 0, 'max_value' => 9.99, 'qualification' => '0% – 9.99% — Interacción constante', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => -1000, 'max_value' => -0.01, 'qualification' => '< 0% — Disminución en interacción con contenidos', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Instagram Clics en el Enlace',
                            'definition' => 'Incremento mensual en clics hacia WhatsApp, biografía y páginas web desde Instagram. Meta: ≥ 10%/mes.',
                            'formula' => 'Incremento_Porcentaje_Instagram_Clics_Enlace',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Incremento_Porcentaje_Instagram_Clics_Enlace', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 10, 'max_value' => 10000, 'qualification' => '≥ 10% — Excelente redirección y captación desde Instagram', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 0, 'max_value' => 9.99, 'qualification' => '0% – 9.99% — Tráfico saliente estable', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => -1000, 'max_value' => -0.01, 'qualification' => '< 0% — Caída en clics al enlace', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Instagram Alcance',
                            'definition' => 'Incremento mensual en cuentas alcanzadas en Instagram. Meta: ≥ 10%/mes.',
                            'formula' => 'Incremento_Porcentaje_Instagram_Alcance',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Incremento_Porcentaje_Instagram_Alcance', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 10, 'max_value' => 10000, 'qualification' => '≥ 10% — Cuentas alcanzadas en constante crecimiento', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 0, 'max_value' => 9.99, 'qualification' => '0% – 9.99% — Alcance de perfil estable', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => -1000, 'max_value' => -0.01, 'qualification' => '< 0% — Disminución en alcance de la cuenta', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Instagram Interacciones Totales',
                            'definition' => 'Incremento mensual en interacciones totales en la cuenta de Instagram. Meta: ≥ 10%/mes.',
                            'formula' => 'Incremento_Porcentaje_Instagram_Interacciones_Totales',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Incremento_Porcentaje_Instagram_Interacciones_Totales', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 10, 'max_value' => 50000, 'qualification' => '≥ 10% — Gran dinamismo e interacción global', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 0, 'max_value' => 9.99, 'qualification' => '0% – 9.99% — Nivel de interacción adecuado', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => -1000, 'max_value' => -0.01, 'qualification' => '< 0% — Caída en interacciones de Instagram', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Instagram Engagement (%)',
                            'definition' => 'Tasa de engagement lograda sobre la audiencia de Instagram. Meta: ≥ 10%/mes.',
                            'formula' => 'Tasa_Instagram_Engagement_Porcentaje',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Tasa_Instagram_Engagement_Porcentaje', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 10, 'max_value' => 1000, 'qualification' => '≥ 10% — Comunidad altamente comprometida', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 5, 'max_value' => 9.99, 'qualification' => '5% – 9.99% — Engagement moderado', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 4.99, 'qualification' => '< 5% — Bajo engagement de la comunidad', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],

                // BLOQUE C: Posicionamiento de Marca (20%)
                [
                    'name' => 'C. Posicionamiento de Marca',
                    'description' => 'Mide la consistencia visual y de marca corporativa (100%), cumplimiento de plazos en material publicitario tradicional y digital (≥ 90%), clima laboral 360° (≥ 4.5 pts) y feedback del área comercial (≥ 4.0 pts).',
                    'formula' => 'Promedio de consistencia visual, piezas tradicionales/digitales a tiempo, evaluación 360 y feedback comercial',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'C. Posicionamiento de Marca',
                    'weight' => 20,
                    'incidence' => 20,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Consistencia Visual de Marca',
                            'definition' => 'Auditoría visual de todas las piezas gráficas, vallas, renders y contenidos alineados con el manual de identidad corporativa. Meta: 100%.',
                            'formula' => 'Porcentaje_Consistencia_Visual_Marca',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Porcentaje_Consistencia_Visual_Marca', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 1000, 'qualification' => '100% — Total coherencia e identidad visual corporativa', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 90, 'max_value' => 99.99, 'qualification' => '90% – 99.99% — Desviaciones menores corregidas oportunamente', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 89.99, 'qualification' => '< 90% — Inconsistencias graves en imagen de marca', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Material Publicitario Tradicional - Cumplimiento de Plazos',
                            'definition' => 'Porcentaje de piezas publicitarias físicas (vallas, volantes, avisos, habladores, pendones) entregadas en los plazos requeridos. Meta: ≥ 90%.',
                            'formula' => '(Piezas_Tradicionales_Entregadas_ATiempo / Total_Piezas_Tradicionales_Solicitadas) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Piezas_Tradicionales_Entregadas_ATiempo', 'value' => 0],
                                ['name' => 'Total_Piezas_Tradicionales_Solicitadas', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 90, 'max_value' => 1000, 'qualification' => '≥ 90% — Oportunidad y cumplimiento en producción física', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 80, 'max_value' => 89.99, 'qualification' => '80% – 89.99% — Cumplimiento adecuado con rezagos leves', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — Retrasos en entrega de piezas tradicionales', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Material Publicitario Digital - Cumplimiento de Plazos',
                            'definition' => 'Porcentaje de piezas digitales (videos, creativos de pauta, email marketing, SMS, automatizaciones) entregadas a tiempo. Meta: ≥ 90%.',
                            'formula' => '(Piezas_Digitales_Entregadas_ATiempo / Total_Piezas_Digitales_Solicitadas) * 100',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Piezas_Digitales_Entregadas_ATiempo', 'value' => 0],
                                ['name' => 'Total_Piezas_Digitales_Solicitadas', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 90, 'max_value' => 1000, 'qualification' => '≥ 90% — Entrega oportuna de campañas y contenidos digitales', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 80, 'max_value' => 89.99, 'qualification' => '80% – 89.99% — Cumplimiento digital con ajustes menores', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 79.99, 'qualification' => '< 80% — Retrasos que afectan lanzamientos o campañas', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Contribución al Ambiente Laboral (Evaluación 360°)',
                            'definition' => 'Evaluación de actitud, responsabilidad, proactividad y trabajo en equipo (escala de 1 a 5). Meta: ≥ 4.5 pts.',
                            'formula' => 'Puntaje_Evaluacion_360',
                            'unit' => 'pts',
                            'parameters' => [
                                ['name' => 'Puntaje_Evaluacion_360', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Sobresaliente (Verde)', 'min_value' => 4.5, 'max_value' => 5.0, 'qualification' => '≥ 4.5 pts — Alta responsabilidad, liderazgo y clima positivo', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 3.5, 'max_value' => 4.49, 'qualification' => '3.5 – 4.49 pts — Desempeño sólido con oportunidades de mejora', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 3.49, 'qualification' => '< 3.5 pts — Oportunidades en comunicación y articulación', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Feedback Comercial',
                            'definition' => 'Calificación y retroalimentación del equipo comercial sobre el apoyo en herramientas de ventas, piezas y agilidad (escala de 1 a 5). Meta: ≥ 4.0 pts.',
                            'formula' => 'Puntaje_Feedback_Comercial',
                            'unit' => 'pts',
                            'parameters' => [
                                ['name' => 'Puntaje_Feedback_Comercial', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 4.0, 'max_value' => 5.0, 'qualification' => '≥ 4.0 pts — Excelente respaldo y generación de valor comercial', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 3.0, 'max_value' => 3.99, 'qualification' => '3.0 – 3.99 pts — Apoyo adecuado con áreas de oportunidad', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 2.99, 'qualification' => '< 3.0 pts — Apoyo insuficiente a la fuerza comercial', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],

                // BLOQUE D: Eventos (15%)
                [
                    'name' => 'D. Eventos',
                    'description' => 'Mide los resultados de lanzamientos, ferias y eventos comerciales: contactos generados (≥ 100), satisfacción de los asistentes (≥ 4.0 / 5.0) y cobertura en medios/redes sociales (≥ 5 menciones).',
                    'formula' => 'Promedio de contactos generados en eventos, satisfacción de asistentes y cobertura en medios/redes',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'D. Eventos',
                    'weight' => 15,
                    'incidence' => 15,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Contactos Generados en Eventos',
                            'definition' => 'Volumen de prospectos y registros recopilados en ferias inmobiliarias, lanzamientos de torres y eventos comerciales. Meta: ≥ 100 contactos.',
                            'formula' => 'Total_Contactos_Generados_Eventos',
                            'unit' => 'contactos',
                            'parameters' => [
                                ['name' => 'Total_Contactos_Generados_Eventos', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 100, 'max_value' => 10000, 'qualification' => '≥ 100 contactos — Alta efectividad y captación en eventos', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 70, 'max_value' => 99.99, 'qualification' => '70 – 99 contactos — Captación aceptable en eventos', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 69.99, 'qualification' => '< 70 contactos — Baja captación de prospectos', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Satisfacción de Asistentes a Eventos (1 a 5)',
                            'definition' => 'Calificación promedio obtenida en encuestas post-evento y experiencia del cliente en eventos/sala de ventas. Meta: ≥ 4.0 pts.',
                            'formula' => 'Puntaje_Satisfaccion_Asistentes_Eventos',
                            'unit' => 'pts',
                            'parameters' => [
                                ['name' => 'Puntaje_Satisfaccion_Asistentes_Eventos', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 4.0, 'max_value' => 5.0, 'qualification' => '≥ 4.0 pts — Experiencia memorable y altamente satisfactoria', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 3.0, 'max_value' => 3.99, 'qualification' => '3.0 – 3.99 pts — Satisfacción adecuada con detalles a mejorar', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 2.99, 'qualification' => '< 3.0 pts — Inconformidad de los asistentes', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Cobertura en Medios y Redes Sociales',
                            'definition' => 'Número de menciones, reposts, transmisiones en vivo y coberturas en redes/medios durante eventos. Meta: ≥ 5 menciones/coberturas.',
                            'formula' => 'Total_Menciones_Coberturas_Eventos',
                            'unit' => 'menciones',
                            'parameters' => [
                                ['name' => 'Total_Menciones_Coberturas_Eventos', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 5, 'max_value' => 1000, 'qualification' => '≥ 5 menciones — Amplia visibilidad y amplificación en redes', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 3, 'max_value' => 4.99, 'qualification' => '3 – 4 menciones — Cobertura moderada', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 2.99, 'qualification' => '< 3 menciones — Baja exposición del evento', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],

                // BLOQUE E: Presupuesto (5%)
                [
                    'name' => 'E. Presupuesto',
                    'description' => 'Mide la disciplina financiera del área de mercadeo: ejecución presupuestal dentro del límite (≤ 100%) y consecución de descuentos en negociaciones con proveedores (≥ 5%).',
                    'formula' => 'Promedio de ejecución presupuestal y porcentaje de descuentos obtenidos',
                    'target' => 100,
                    'unit' => '%',
                    'stage' => 'E. Presupuesto',
                    'weight' => 5,
                    'incidence' => 5,
                    'lower_is_better' => false,
                    'indicators' => [
                        [
                            'name' => 'Ejecución Presupuestal (%)',
                            'definition' => 'Control del gasto en mercadeo, eventos y publicidad asegurando mantenerse dentro del presupuesto aprobado. Meta: ≤ 100%.',
                            'formula' => '(Gasto_Real_Mercadeo / Presupuesto_Aprobado_Mercadeo) * 100',
                            'unit' => '%',
                            'lower_is_better' => true,
                            'parameters' => [
                                ['name' => 'Gasto_Real_Mercadeo', 'value' => 0],
                                ['name' => 'Presupuesto_Aprobado_Mercadeo', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 0, 'max_value' => 100.0, 'qualification' => '≤ 100% — Gasto disciplinado dentro del presupuesto aprobado', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 100.01, 'max_value' => 105.0, 'qualification' => '100.1% – 105% — Sobregiro menor justificado por contingencias', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 105.01, 'max_value' => 1000, 'qualification' => '> 105% — Desviación presupuestal no autorizada', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                        [
                            'name' => 'Descuentos en Negociaciones con Proveedores',
                            'definition' => 'Porcentaje de ahorro o descuento obtenido en cotizaciones y negociaciones con proveedores de stands, mobiliario, pauta, vallas e imprenta. Meta: ≥ 5%.',
                            'formula' => 'Porcentaje_Descuentos_Obtenidos_Proveedores',
                            'unit' => '%',
                            'parameters' => [
                                ['name' => 'Porcentaje_Descuentos_Obtenidos_Proveedores', 'value' => 0],
                            ],
                            'conditional_goals' => [
                                ['level' => 'Óptimo (Verde)', 'min_value' => 5, 'max_value' => 100, 'qualification' => '≥ 5% — Eficiencia financiera y excelentes negociaciones', 'color' => 'optimal', 'score' => 100],
                                ['level' => 'Aceptable (Amarillo)', 'min_value' => 1, 'max_value' => 4.99, 'qualification' => '1% – 4.99% — Ahorros moderados en contrataciones', 'color' => 'acceptable', 'score' => 80],
                                ['level' => 'Deficiente (Rojo)', 'min_value' => 0, 'max_value' => 0.99, 'qualification' => '< 1% — Sin optimización ni descuentos obtenidos', 'color' => 'deficient', 'score' => 0],
                            ],
                        ],
                    ]
                ],
            ];

            foreach ($kpisManuela as $kpiData) {
                $userManuela->kpis()->updateOrCreate(
                    ['name' => $kpiData['name']],
                    $kpiData
                );
            }
        }
    }
}
