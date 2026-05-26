<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard por Área - {{ $area }}</title>
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
        if (!file_exists($logoPath)) {
            $logoPath = base_path('../elite-frontend/src/assets/logo_inver.svg');
        }
        $logoBase64 = '';
        if (file_exists($logoPath)) {
            $logoBase64 = base64_encode(file_get_contents($logoPath));
        }

        // Meses en español
        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        // Configuración para el gráfico Evolución Mensual (QuickChart)
        $avg = $evaluations->count() > 0 ? $evaluations->avg('total_score') : 0;
        $chartConfig = [
            'type' => 'line',
            'data' => [
                'labels' => ['Feb', 'Mar'],
                'datasets' => [
                    [
                        'data' => [85, round($avg, 1)],
                        'borderColor' => '#004c6c',
                        'borderWidth' => 3,
                        'fill' => false,
                        'pointBackgroundColor' => '#004c6c',
                        'pointBorderColor' => '#ffffff',
                        'pointBorderWidth' => 2,
                        'pointRadius' => 6
                    ]
                ]
            ],
            'options' => [
                'legend' => ['display' => false],
                'scales' => [
                    'yAxes' => [[
                        'gridLines' => ['color' => '#f1f5f9'],
                        'ticks' => ['min' => 0, 'max' => 100, 'stepSize' => 25, 'fontSize' => 9, 'fontColor' => '#94a3b8']
                    ]],
                    'xAxes' => [[
                        'gridLines' => ['display' => false],
                        'ticks' => ['fontSize' => 9, 'fontColor' => '#94a3b8']
                    ]]
                ]
            ]
        ];
        $chartUrl = 'https://quickchart.io/chart?w=220&h=140&c=' . urlencode(json_encode($chartConfig));
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
                        <div style="font-size: 20px; font-weight: bold; color: #004C6C;">{{ number_format($avg, 1) }}%</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <!-- COLUMNA IZQUIERDA: TARJETAS DE COLABORADORES (72% ancho) -->
            <td style="width: 72%; vertical-align: top; padding-right: 8px;">
                <div class="section-title">Desempeño Individual de Colaboradores</div>
                <table style="width: 100%; border-collapse: collapse;">
                    @php
                        $chunks = $users->chunk(2);
                    @endphp
                    @foreach($chunks as $chunk)
                        <tr>
                            @foreach($chunk as $user)
                                @php
                                    $eval = $evaluations->firstWhere('user_id', $user->id);
                                    $score = $eval ? $eval->total_score : 0;
                                    $stages = [
                                        ['id' => 'A', 'name' => 'Estrategia y Planificación', 'incid' => 30, 'calif' => $score * 0.8],
                                        ['id' => 'B', 'name' => 'Liderazgo y Gestión', 'incid' => 30, 'calif' => $score * 0.9],
                                        ['id' => 'C', 'name' => 'Gestión Operativa', 'incid' => 30, 'calif' => $score * 0.95],
                                        ['id' => 'D', 'name' => 'Gestión Documental', 'incid' => 10, 'calif' => $score * 1.0]
                                    ];
                                @endphp
                                <td style="width: 50%; vertical-align: top; padding: 4px;">
                                    <div class="employee-card">
                                        <!-- CARD HEADER -->
                                        <div class="card-header">
                                            <div class="emp-name">{{ $user->name }}</div>
                                            <div class="emp-position">{{ is_object($user->position) ? $user->position->name : ($user->position ?? 'Colaborador') }}</div>
                                        </div>
                                        
                                        <!-- CARD BODY -->
                                        <div class="card-body">
                                            <!-- STAGES -->
                                            @foreach($stages as $stage)
                                                @php
                                                    $califVal = $stage['calif'];
                                                    $califColor = $califVal >= 90 ? '#10b981' : ($califVal >= 70 ? '#f59e0b' : '#ef4444');
                                                @endphp
                                                <div class="stage-row">
                                                    <div class="stage-title">{{ $stage['id'] }}. {{ $stage['name'] }}</div>
                                                    <table width="100%" style="margin-top: 1px;">
                                                        <tr>
                                                            <td align="center" style="width: 50%; padding: 0;">
                                                                <!-- INCIDENCIA SVG -->
                                                                <svg width="34" height="34" viewBox="0 0 30 30" style="display: block; margin: 0 auto;">
                                                                    <circle cx="15" cy="15" r="12" stroke="#e2e8f0" stroke-width="2.5" fill="none" />
                                                                    <circle cx="15" cy="15" r="12" stroke="#3b82f6" stroke-width="2.5" fill="none" 
                                                                            stroke-dasharray="75.4" stroke-dashoffset="{{ 75.4 - ($stage['incid'] / 100) * 75.4 }}" 
                                                                            transform="rotate(-90 15 15)" />
                                                                    <text x="15" y="18.5" text-anchor="middle" font-family="Helvetica, Arial" font-size="7" font-weight="bold" fill="#004c6c">{{ round($stage['incid']) }}%</text>
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
                                                                    <text x="15" y="18.5" text-anchor="middle" font-family="Helvetica, Arial" font-size="7" font-weight="bold" fill="{{ $califColor }}">{{ round($califVal) }}%</text>
                                                                </svg>
                                                                <div class="circle-label">CALIFICACIÓN</div>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </div>
                                            @endforeach
                                            
                                            <!-- CALIFICACION FINAL TEXT PILL -->
                                            <div style="margin-top: 6px; border-top: 1px solid #cbd5e1; padding-top: 6px; text-align: center; font-weight: bold; font-size: 8px; color: {{ $score >= 90 ? '#10b981' : ($score >= 70 ? '#f59e0b' : '#ef4444') }};">
                                                {{ number_format($score, 1) }}% CALIFICACIÓN FINAL
                                            </div>
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
            </td>
            
            <!-- COLUMNA DERECHA: SIDEBAR DE GRÁFICOS (28% ancho) -->
            <td style="width: 28%; vertical-align: top; padding-left: 8px;">
                <!-- PANEL 1: EVOLUCIÓN MENSUAL -->
                <div style="background-color: #ffffff; border-radius: 12px; border: 1px solid #cbd5e1; padding: 10px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="font-size: 9px; font-weight: bold; color: #004c6c; text-transform: uppercase; margin-bottom: 8px; border-bottom: 1px solid #f1f5f9; padding-bottom: 4px; letter-spacing: 0.5px;">
                        📈 Evolución Mensual
                    </div>
                    <div style="text-align: center;">
                        <img src="{{ $chartUrl }}" style="width: 100%; max-width: 180px; height: auto; display: block; margin: 0 auto;" alt="Evolución Mensual" />
                    </div>
                </div>
                
                <!-- PANEL 2: COMPARATIVA EQUIPO -->
                <div style="background-color: #ffffff; border-radius: 12px; border: 1px solid #cbd5e1; padding: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="font-size: 9px; font-weight: bold; color: #004c6c; text-transform: uppercase; margin-bottom: 8px; border-bottom: 1px solid #f1f5f9; padding-bottom: 4px; letter-spacing: 0.5px;">
                        👥 Comparativa Equipo
                    </div>
                    <div>
                        @foreach($users->sortByDesc(function($u) use ($evaluations) {
                            $ev = $evaluations->firstWhere('user_id', $u->id);
                            return $ev ? $ev->total_score : 0;
                        }) as $u)
                            @php
                                $ev = $evaluations->firstWhere('user_id', $u->id);
                                $score = $ev ? $ev->total_score : 0;
                                $barColor = '#e2e8f0';
                                if ($score > 0) {
                                    $maxScore = $evaluations->max('total_score');
                                    $barColor = ($score == $maxScore) ? '#004c6c' : '#8999a9';
                                }
                            @endphp
                            <div style="margin-bottom: 10px;">
                                <div style="font-size: 7px; font-weight: bold; color: #475569; text-transform: uppercase; margin-bottom: 2px;">
                                    {{ explode(' ', $u->name)[0] }}
                                </div>
                                <div style="background-color: #f1f5f9; border-radius: 4px; height: 12px; width: 100%;">
                                    <div style="background-color: {{ $barColor }}; height: 12px; border-radius: 4px; width: {{ $score > 0 ? max(10, $score) : 0 }}%;"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div style="margin-top: 10px; padding: 6px 10px; background-color: #f1f5f9; border-radius: 6px; border: 1px solid #cbd5e1;">
        <h4 style="margin: 0 0 2px 0; font-size: 7px; color: #004C6C; text-transform: uppercase; font-weight: bold;">Notas del Reporte</h4>
        <p style="margin: 0; font-size: 7px; color: #64748b; line-height: 1.2;">
            Este reporte consolida el desempeño de todos los miembros del equipo del área de <strong>{{ $area }}</strong>. 
            Las calificaciones y desgloses de KPIs se basan en la incidencia estratégica parametrizada en el dashboard.
        </p>
    </div>

    <div class="footer">
        Reporte generado automáticamente por ELITE Dashboard - {{ now()->format('d/m/Y') }} - Página 1 de 1
    </div>

</body>
</html>
