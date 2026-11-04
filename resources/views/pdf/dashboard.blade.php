<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard por Área - {{ $report['area'] }}</title>
    <style>
        @page {
            margin: 0.8cm;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #334155;
            line-height: 1.2;
            font-size: 9px;
        }
        .header {
            margin-bottom: 10px;
            border-bottom: 2px solid #004C6C;
            padding-bottom: 6px;
        }
        .header table {
            width: 100%;
        }
        .brand {
            font-size: 22px;
            font-weight: bold;
            color: #004C6C;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .report-title {
            text-align: right;
            font-size: 13px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
        }
        .summary-box {
            background-color: #f8fafc;
            padding: 8px 12px;
            border-radius: 8px;
            margin-bottom: 12px;
            border: 1px solid #e2e8f0;
        }
        .area-name {
            font-size: 15px;
            font-weight: bold;
            color: #004C6C;
            margin: 0;
        }
        .period {
            font-size: 8px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .section-title {
            font-size: 10px;
            font-weight: bold;
            color: #004C6C;
            margin-bottom: 8px;
            text-transform: uppercase;
            border-left: 3px solid #004C6C;
            padding-left: 6px;
        }

        .employee-card {
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 10px;
        }
        .card-header {
            background-color: #f8fafc;
            padding: 6px 8px;
            border-bottom: 1px solid #cbd5e1;
        }
        .emp-name {
            font-size: 10px;
            font-weight: bold;
            color: #004C6C;
        }
        .emp-position {
            font-size: 7px;
            font-weight: bold;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .card-body {
            padding: 6px 8px;
        }
        .stage-row {
            margin-bottom: 4px;
            border-bottom: 1px dashed #e2e8f0;
            padding-bottom: 3px;
        }
        .stage-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .stage-title {
            font-size: 7px;
            font-weight: bold;
            color: #475569;
            margin-bottom: 2px;
            text-transform: uppercase;
        }
        .circle-label {
            font-size: 5px;
            font-weight: bold;
            color: #94a3b8;
            margin-top: 1px;
            text-transform: uppercase;
        }

        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 7px;
            color: #94a3b8;
            padding: 4px 0;
        }
    </style>
</head>
<body>

    @php
        $logoPath = public_path('logo_inver.svg');
        if (!file_exists($logoPath)) {
            $logoPath = base_path('public/logo_inver.svg');
        }
        $logoBase64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : '';

        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        // Todo sale de KpiDashboardService (los mismos datos que muestra la pantalla del Dashboard)
        $area = $report['area'];
        $month = $report['month'];
        $year = $report['year'];
        $avg = $report['average'];
        $collaborators = collect($report['collaborators']);
        $palette = ['#1a5b78', '#e65f2b', '#1b5e20', '#0288d1', '#9c27b0', '#55a630', '#0b2c3d', '#7209b7', '#f72585', '#ff9f1c', '#2ec4b6', '#6d597a'];
        $monthsConfig = collect($report['months'])->values()->map(fn ($m, $i) => $m + ['color' => $palette[$i % count($palette)]]);
        $scoreColor = fn ($v) => $v >= 90 ? '#10b981' : ($v >= 70 ? '#f59e0b' : '#ef4444');

        $chartOptions = fn (float $tickFont) => [
            'legend' => ['position' => 'bottom', 'labels' => ['fontSize' => 6, 'boxWidth' => 6]],
            'scales' => [
                'yAxes' => [[
                    'gridLines' => ['color' => '#f1f5f9'],
                    'ticks' => ['min' => 0, 'max' => 100, 'stepSize' => 25, 'fontSize' => 6, 'fontColor' => '#94a3b8'],
                ]],
                'xAxes' => [[
                    'gridLines' => ['display' => false],
                    'ticks' => ['fontSize' => $tickFont, 'fontColor' => '#94a3b8', 'autoSkip' => false],
                ]],
            ],
        ];
        $quickChart = fn (array $config) => 'https://quickchart.io/chart?w=300&h=200&c=' . urlencode(json_encode($config));

        // Incidencia (peso) vs calificación (aporte = peso × puntaje / 100) por colaborador y categoría
        $breakdownChartUrl = null;
        if (count($report['breakdown']) > 0) {
            $rows = collect($report['breakdown']);
            $breakdownChartUrl = $quickChart([
                'type' => 'line',
                'data' => [
                    'labels' => $rows->map(fn ($r) => strtok($r['short_name'], ' ') . ' · ' . mb_substr($r['category'], 0, 14))->all(),
                    'datasets' => [
                        ['label' => 'Incid.', 'data' => $rows->pluck('incidence')->all(), 'borderColor' => '#004c6c', 'borderWidth' => 2, 'fill' => false, 'pointBackgroundColor' => '#004c6c', 'pointRadius' => 2],
                        ['label' => 'Calif.', 'data' => $rows->pluck('contribution')->all(), 'borderColor' => '#e65f2b', 'borderWidth' => 2, 'fill' => false, 'pointBackgroundColor' => '#e65f2b', 'pointRadius' => 2],
                    ],
                ],
                'options' => $chartOptions(4.5),
            ]);
        }

        // Evolución del promedio del área (solo meses con evaluaciones)
        $evolutionChartUrl = null;
        if (count($report['evolution']) > 0) {
            $evolution = collect($report['evolution']);
            $evolutionChartUrl = $quickChart([
                'type' => 'line',
                'data' => [
                    'labels' => $evolution->pluck('label')->all(),
                    'datasets' => [[
                        'label' => 'Promedio',
                        'data' => $evolution->pluck('average')->all(),
                        'borderColor' => '#004c6c', 'borderWidth' => 3, 'fill' => false,
                        'pointBackgroundColor' => '#004c6c', 'pointBorderColor' => '#ffffff', 'pointBorderWidth' => 2, 'pointRadius' => 5,
                    ]],
                ],
                'options' => $chartOptions(7),
            ]);
        }

        // Historial de cada colaborador en la ventana de la comparativa
        $histories = collect($report['comparison'])->map(function ($c) use ($monthsConfig) {
            $history = $monthsConfig
                ->filter(fn ($m) => $c['scores'][$m['key']] !== null)
                ->map(fn ($m) => ['label' => $m['label'], 'color' => $m['color'], 'score' => $c['scores'][$m['key']]])
                ->values()
                ->all();

            return $c + ['history' => $history];
        });
    @endphp

    <div class="header">
        <table>
            <tr>
                <td class="brand" style="vertical-align: middle;">
                    @if(!empty($logoBase64))
                        <img src="data:image/svg+xml;base64,{{ $logoBase64 }}" style="height: 28px; vertical-align: middle; margin-right: 8px;" />
                    @endif
                    <span style="vertical-align: middle;">ELITE</span>
                </td>
                <td class="report-title" style="vertical-align: middle;">Informe por Área</td>
            </tr>
        </table>
    </div>

    <div class="summary-box">
        <table width="100%">
            <tr>
                <td>
                    <div class="period">Reporte Mensual</div>
                    <h1 class="area-name">{{ $area }}</h1>
                    <div style="color: #64748b; font-size: 8px; margin-top: 2px;">
                        Periodo: {{ $months[$month] }} {{ $year }}
                    </div>
                </td>
                <td align="right" style="vertical-align: middle;">
                    <div style="text-align: right;">
                        <div style="font-size: 8px; text-transform: uppercase; color: #94a3b8; font-weight: bold;">Promedio del Área</div>
                        <div style="font-size: 20px; font-weight: bold; color: #004C6C;">{{ $avg === null ? '—' : number_format($avg, 1) . '%' }}</div>
                        <div style="font-size: 7px; color: #94a3b8;">{{ $report['evaluated'] }} de {{ $report['total_users'] }} evaluados</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- SECCIÓN: TARJETAS DE COLABORADORES - ANCHO COMPLETO EN PÁGINA 1 -->
    <div style="width: 100%;">
        <div class="section-title">Desempeño Individual de Colaboradores</div>
        <table style="width: 100%; border-collapse: collapse;">
            @php
                $chunks = $collaborators->chunk(2);
            @endphp
            @foreach($chunks as $chunk)
                <tr>
                    @foreach($chunk as $c)
                        @php
                            $score = $c['total_score'];
                        @endphp
                        <td style="width: 50%; vertical-align: top; padding: 4px;">
                            <div class="employee-card">
                                <!-- CARD HEADER -->
                                <div class="card-header">
                                    <div class="emp-name">{{ $c['name'] }}</div>
                                    <div class="emp-position">{{ $c['position'] ?? 'Colaborador' }}</div>
                                </div>
                                
                                <!-- CARD BODY -->
                                <div class="card-body">
                                    <!-- STAGES -->
                                    @forelse($c['categories'] as $stage)
                                        @php
                                            $califVal = $stage['score'] ?? 0;
                                            $califColor = $stage['score'] === null ? '#cbd5e1' : $scoreColor($califVal);
                                        @endphp
                                        <div class="stage-row">
                                            <div class="stage-title">{{ $stage['name'] }}</div>
                                            <table width="100%" style="margin-top: 1px;">
                                                <tr>
                                                    <td align="center" style="width: 50%; padding: 0;">
                                                        <!-- INCIDENCIA SVG -->
                                                        <svg width="34" height="34" viewBox="0 0 30 30" style="display: block; margin: 0 auto;">
                                                            <circle cx="15" cy="15" r="12" stroke="#e2e8f0" stroke-width="2.5" fill="none" />
                                                            <circle cx="15" cy="15" r="12" stroke="#3b82f6" stroke-width="2.5" fill="none" 
                                                                    stroke-dasharray="75.4" stroke-dashoffset="{{ 75.4 - ($stage['incidence'] / 100) * 75.4 }}" 
                                                                    transform="rotate(-90 15 15)" />
                                                            <text x="15" y="18.5" text-anchor="middle" font-family="Helvetica, Arial" font-size="7" font-weight="bold" fill="#004c6c">{{ round($stage['incidence']) }}%</text>
                                                        </svg>
                                                        <div class="circle-label">INCIDENCIA</div>
                                                    </td>
                                                    <td align="center" style="width: 50%; padding: 0;">
                                                        <!-- CALIFICACIÓN SVG -->
                                                        <svg width="34" height="34" viewBox="0 0 30 30" style="display: block; margin: 0 auto;">
                                                            <circle cx="15" cy="15" r="12" stroke="#e2e8f0" stroke-width="2.5" fill="none" />
                                                            <circle cx="15" cy="15" r="12" stroke="{{ $califColor }}" stroke-width="2.5" fill="none" 
                                                                    stroke-dasharray="75.4" stroke-dashoffset="{{ 75.4 - ($califVal / 100) * 75.4 }}" 
                                                                    transform="rotate(-90 15 15)" />
                                                            <text x="15" y="18.5" text-anchor="middle" font-family="Helvetica, Arial" font-size="7" font-weight="bold" fill="{{ $califColor }}">{{ $stage['score'] === null ? '—' : round($califVal) . '%' }}</text>
                                                        </svg>
                                                        <div class="circle-label">CALIFICACIÓN</div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    @empty
                                        <div style="font-size: 7px; color: #94a3b8; font-style: italic; text-align: center; padding: 6px 0;">Sin KPIs configurados</div>
                                    @endforelse

                                    <!-- CALIFICACION FINAL TEXT PILL -->
                                    @if($score === null)
                                        <div style="margin-top: 6px; border-top: 1px solid #cbd5e1; padding-top: 6px; text-align: center; font-weight: bold; font-size: 8px; color: #94a3b8;">
                                            SIN EVALUACIÓN EN EL PERIODO
                                        </div>
                                    @else
                                        <div style="margin-top: 6px; border-top: 1px solid #cbd5e1; padding-top: 6px; text-align: center; font-weight: bold; font-size: 8px; color: {{ $scoreColor($score) }};">
                                            {{ number_format($score, 1) }}% CALIFICACIÓN FINAL
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </td>
                    @endforeach
                    @if($chunk->count() < 2)
                        <td style="width: 50%;">&nbsp;</td>
                    @endif
                </tr>
            @endforeach
        </table>
    </div>

    <!-- SALTO DE PÁGINA PARA ANALÍTICA Y GRÁFICOS -->
    <div style="page-break-before: always;"></div>

    <!-- HEADER PARA PÁGINA 2 -->
    <div class="header">
        <table>
            <tr>
                <td class="brand" style="vertical-align: middle;">
                    @if(!empty($logoBase64))
                        <img src="data:image/svg+xml;base64,{{ $logoBase64 }}" style="height: 28px; vertical-align: middle; margin-right: 8px;" />
                    @endif
                    <span style="vertical-align: middle;">ELITE</span>
                </td>
                <td class="report-title" style="vertical-align: middle;">Métricas y Comparativas del Equipo</td>
            </tr>
        </table>
    </div>

    <!-- METRICAS EN DOS COLUMNAS DE ANCHO COMPLETO (PÁGINA 2) -->
    <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
        <tr>
            <!-- PANEL 1: EVOLUCIÓN MENSUAL / INCIDENCIA VS CALIFICACIÓN (48% ancho) -->
            <td style="width: 48%; vertical-align: top; padding-right: 12px;">
                <div style="background-color: #ffffff; border-radius: 12px; border: 1px solid #cbd5e1; padding: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); min-height: 280px;">
                    @foreach([['Incidencia vs Calificación', $breakdownChartUrl], ['Evolución Mensual', $evolutionChartUrl]] as [$chartTitle, $chartUrl])
                        <div style="font-size: 10px; font-weight: bold; color: #004c6c; text-transform: uppercase; margin-bottom: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px; letter-spacing: 0.5px;">
                            {{ $chartTitle }}
                        </div>
                        <div style="text-align: center; padding: 6px 0 12px;">
                            @if($chartUrl)
                                <img src="{{ $chartUrl }}" style="width: 100%; max-width: 280px; height: auto; display: block; margin: 0 auto;" alt="{{ $chartTitle }}" />
                            @else
                                <div style="font-size: 7px; color: #94a3b8; font-style: italic;">Sin evaluaciones para graficar</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </td>
            
            <!-- PANEL 2: COMPARATIVA EQUIPO (52% ancho) -->
            <td style="width: 52%; vertical-align: top; padding-left: 12px;">
                <div style="background-color: #ffffff; border-radius: 12px; border: 1px solid #cbd5e1; padding: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); min-height: 280px;">
                    <div style="font-size: 10px; font-weight: bold; color: #004c6c; text-transform: uppercase; margin-bottom: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px; letter-spacing: 0.5px;">
                         Comparativa Equipo
                    </div>
                    <div>
                        @foreach($histories as $h)
                            @php
                                $name = $h['short_name'];
                                $cargoName = $h['position'] ?? '';
                                $history = $h['history'];
                            @endphp
                            <div style="margin-bottom: 10px; border-bottom: 1px solid #f8fafc; padding-bottom: 6px; page-break-inside: avoid;">
                                <div style="font-size: 8px; font-weight: bold; color: #1e293b; text-transform: uppercase;">
                                    {{ $name }}
                                </div>
                                <div style="font-size: 6.5px; font-weight: bold; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px;">
                                    {{ $cargoName }}
                                </div>
                                @if(count($history) > 0)
                                    <table style="width: 100%; border-collapse: collapse; margin-top: 2px;">
                                        @foreach($history as $item)
                                            <tr>
                                                <td style="width: 32px; font-size: 7px; font-weight: bold; color: #64748b; padding: 1.5px 0;">
                                                    {{ $item['label'] }}
                                                </td>
                                                <td style="padding: 1.5px 4px; vertical-align: middle;">
                                                    <div style="background-color: #f1f5f9; border-radius: 2px; height: 8px; width: 100%;">
                                                        <div style="background-color: {{ $item['color'] }}; height: 8px; border-radius: 2px; width: {{ max(5, $item['score']) }}%;"></div>
                                                    </div>
                                                </td>
                                                <td style="width: 26px; text-align: right; font-size: 7px; font-weight: bold; color: #475569; padding: 1.5px 0;">
                                                    {{ number_format($item['score'], 0) }}%
                                                </td>
                                            </tr>
                                        @endforeach
                                    </table>
                                @else
                                    <div style="font-size: 6.5px; color: #94a3b8; font-style: italic;">Sin datos históricos</div>
                                @endif
                            </div>
                        @endforeach

                        <!-- LEYENDA PERSONALIZADA EN PDF -->
                        <div style="margin-top: 8px; border-top: 1px dashed #cbd5e1; padding-top: 8px;">
                            <div style="font-size: 7px; font-weight: bold; color: #004c6c; text-transform: uppercase; margin-bottom: 4px; text-align: center;">
                                Leyenda de Meses
                            </div>
                            <div style="text-align: center;">
                                @foreach($monthsConfig as $m)
                                    <span style="display: inline-block; margin-right: 6px; margin-bottom: 2px; font-size: 6px; font-weight: bold; color: #475569; text-transform: uppercase;">
                                        <span style="display: inline-block; width: 6px; height: 6px; background-color: {{ $m['color'] }}; border-radius: 1px; vertical-align: middle; margin-right: 2px;"></span>
                                        {{ $m['label'] }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div style="margin-top: 10px; padding: 6px 10px; background-color: #f1f5f9; border-radius: 6px; border: 1px solid #cbd5e1;">
        <h4 style="margin: 0 0 2px 0; font-size: 7px; color: #004C6C; text-transform: uppercase; font-weight: bold;">Notas del Reporte</h4>
        <p style="margin: 0; font-size: 7px; color: #64748b; line-height: 1.2;">
            Este reporte consolida el desempeño de todos los miembros del equipo del área de <strong>{{ $area }}</strong>. 
            Incidencia = peso del KPI parametrizado; calificación = puntaje obtenido en la evaluación del periodo. En la gráfica, la calificación es el aporte a la nota final (peso × puntaje / 100).
        </p>
    </div>

    <div class="footer">
        Reporte generado automáticamente por ELITE Dashboard - {{ now()->format('d/m/Y') }} - Página 1 de 1
    </div>

</body>
</html>
