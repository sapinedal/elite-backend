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
        $isComercialArea = trim(strtolower($area)) === 'comercial' || strpos(strtolower($area), 'comercial') !== false;
        if ($isComercialArea) {
            $userSara = $users->first(function($u) {
                $pos = is_object($u->position) ? $u->position->name : ($u->position ?? '');
                return strpos(strtolower($pos), 'directora comercial') !== false || strpos(strtolower($pos), 'director comercial') !== false;
            });
            $userIngrid = $users->first(function($u) {
                $pos = is_object($u->position) ? $u->position->name : ($u->position ?? '');
                return strpos(strtolower($pos), 'lider de sala') !== false || strpos(strtolower($pos), 'líder de sala') !== false || strpos(strtolower($pos), 'sala de ventas') !== false;
            });
            $userNatalia = $users->first(function($u) {
                $pos = is_object($u->position) ? $u->position->name : ($u->position ?? '');
                $name = strtolower($u->name);
                return (strpos(strtolower($pos), 'asesora comercial') !== false || strpos(strtolower($pos), 'asesor comercial') !== false) 
                       && (strpos($name, 'natalia') !== false || strpos($name, 'posada') !== false);
            });
            $userPaola = $users->first(function($u) {
                $pos = is_object($u->position) ? $u->position->name : ($u->position ?? '');
                $name = strtolower($u->name);
                return (strpos(strtolower($pos), 'asesora comercial') !== false || strpos(strtolower($pos), 'asesor comercial') !== false) 
                       && (strpos($name, 'paola') !== false || strpos($name, 'arenas') !== false);
            });

            $evalSara = $userSara ? $evaluations->firstWhere('user_id', $userSara->id) : null;
            $evalIngrid = $userIngrid ? $evaluations->firstWhere('user_id', $userIngrid->id) : null;
            $evalPaola = $userPaola ? $evaluations->firstWhere('user_id', $userPaola->id) : null;
            $evalNatalia = $userNatalia ? $evaluations->firstWhere('user_id', $userNatalia->id) : null;

            $scoreSara = $evalSara ? floatval($evalSara->total_score) : 0;
            $scoreIngrid = $evalIngrid ? floatval($evalIngrid->total_score) : 0;
            $scorePaola = $evalPaola ? floatval($evalPaola->total_score) : 0;
            $scoreNatalia = $evalNatalia ? floatval($evalNatalia->total_score) : 0;

            $kpiData = [
                ['label' => 'Clientes/Leads', 'incid' => 70, 'calif' => 70 * ($scoreNatalia / 100)],
                ['label' => 'Op/Ctrl Ventas', 'incid' => 25, 'calif' => 25 * ($scoreNatalia / 100)],
                ['label' => 'Relaciones/Serv', 'incid' => 5, 'calif' => 5 * ($scoreNatalia / 100)],
                
                ['label' => 'Clientes/Leads (A)', 'incid' => 60, 'calif' => 60 * ($scorePaola / 100)],
                ['label' => 'Op/Ctrl Ventas (A)', 'incid' => 40, 'calif' => 40 * ($scorePaola / 100)],
                
                ['label' => 'Planif. Comercial', 'incid' => 30, 'calif' => 30 * ($scoreSara / 100)],
                ['label' => 'Doc. y Procesos', 'incid' => 10, 'calif' => 10 * ($scoreSara / 100)],
                ['label' => 'Op/Ctrl Ventas (D)', 'incid' => 30, 'calif' => 30 * ($scoreSara / 100)],
                ['label' => 'Liderazgo/Gestión', 'incid' => 30, 'calif' => 30 * ($scoreSara / 100)],
                
                ['label' => 'Clientes/Leads (L)', 'incid' => 70, 'calif' => 70 * ($scoreIngrid / 100)],
                ['label' => 'Op/Ctrl Ventas (L)', 'incid' => 25, 'calif' => 25 * ($scoreIngrid / 100)],
                ['label' => 'Relaciones/Serv (L)', 'incid' => 5, 'calif' => 5 * ($scoreIngrid / 100)],
            ];

            $chartLabels = [];
            $incidData = [];
            $califData = [];
            foreach ($kpiData as $item) {
                $chartLabels[] = $item['label'];
                $incidData[] = round($item['incid'], 1);
                $califData[] = round($item['calif'], 1);
            }

            $chartConfig = [
                'type' => 'line',
                'data' => [
                    'labels' => $chartLabels,
                    'datasets' => [
                        [
                            'label' => 'Incid.',
                            'data' => $incidData,
                            'borderColor' => '#004c6c',
                            'borderWidth' => 2,
                            'fill' => false,
                            'pointBackgroundColor' => '#004c6c',
                            'pointRadius' => 2
                        ],
                        [
                            'label' => 'Calif.',
                            'data' => $califData,
                            'borderColor' => '#e65f2b',
                            'borderWidth' => 2,
                            'fill' => false,
                            'pointBackgroundColor' => '#e65f2b',
                            'pointRadius' => 2
                        ]
                    ]
                ],
                'options' => [
                    'legend' => [
                        'position' => 'bottom',
                        'labels' => ['fontSize' => 6, 'boxWidth' => 6]
                    ],
                    'scales' => [
                        'yAxes' => [[
                            'gridLines' => ['color' => '#f1f5f9'],
                            'ticks' => ['min' => 0, 'max' => 100, 'stepSize' => 25, 'fontSize' => 6, 'fontColor' => '#94a3b8']
                        ]],
                        'xAxes' => [[
                            'gridLines' => ['display' => false],
                            'ticks' => ['fontSize' => 4.5, 'fontColor' => '#94a3b8', 'autoSkip' => false]
                        ]]
                    ]
                ]
            ];
            $chartUrl = 'https://quickchart.io/chart?w=300&h=200&c=' . urlencode(json_encode($chartConfig));
        } else {
            $chartConfig = [
                'type' => 'line',
                'data' => [
                    'labels' => ['Ene', 'Feb', 'Mar'],
                    'datasets' => [
                        [
                            'data' => [80, 85, round($avg, 1)],
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
            $chartUrl = 'https://quickchart.io/chart?w=300&h=200&c=' . urlencode(json_encode($chartConfig));
        }

        // Helper para obtener etiqueta de cargo y nombre corto
        $getCargoLabel = function ($user) {
            $posName = (is_object($user->position) ? $user->position->name : ($user->position ?? '')) ?: '';
            $posUpper = mb_strtoupper($posName, 'UTF-8');
            
            $nameParts = explode(' ', trim($user->name));
            $firstName = !empty($user->first_name) ? explode(' ', trim($user->first_name))[0] : ($nameParts[0] ?? '');
            $lastName = !empty($user->last_name) ? explode(' ', trim($user->last_name))[0] : ($nameParts[1] ?? '');
            
            $firstName = mb_strtoupper($firstName, 'UTF-8');
            $lastName = mb_strtoupper($lastName, 'UTF-8');
            
            $shortName = $lastName ? "{$firstName} {$lastName}" : $firstName;

            if (strpos($posUpper, 'DIRECTORA COMERCIAL') !== false || strpos($posUpper, 'DIRECTOR COMERCIAL') !== false) {
                return "DIRECTORA COMERCIAL - {$shortName}";
            }
            if (strpos($posUpper, 'LÍDER DE SALA') !== false || strpos($posUpper, 'LIDER DE SALA') !== false || strpos($posUpper, 'SALA DE VENTAS') !== false) {
                return "LIDER SALA DE VENTAS - {$shortName}";
            }
            if (strpos($posUpper, 'ASESORA COMERCIAL') !== false || strpos($posUpper, 'ASESOR COMERCIAL') !== false) {
                return "ASESORA COMERCIAL - {$shortName}";
            }
            return "{$posUpper} - {$shortName}";
        };

        $cargoOrder = [
            'ASESORA COMERCIAL - PAOLA ARENAS',
            'ASESORA COMERCIAL - NATALIA POSADA',
            'LIDER SALA DE VENTAS - INGRID OSPICIO',
            'DIRECTORA COMERCIAL - SARA MORENO'
        ];

        // Ordenar usuarios por jerarquía si es comercial
        if ($isComercialArea) {
            $sortedUsers = $users->sortBy(function($u) use ($getCargoLabel, $cargoOrder) {
                $lbl = $getCargoLabel($u);
                $idx = array_search($lbl, $cargoOrder);
                return $idx !== false ? $idx : 999;
            });
        } else {
            $sortedUsers = $users->sortBy('name');
        }

        $monthsConfig = [
            ['key' => 'Agosto', 'label' => 'Ago', 'color' => '#1a5b78', 'month' => 8, 'year' => 2025],
            ['key' => 'Septiembre', 'label' => 'Sep', 'color' => '#e65f2b', 'month' => 9, 'year' => 2025],
            ['key' => 'Octubre', 'label' => 'Oct', 'color' => '#1b5e20', 'month' => 10, 'year' => 2025],
            ['key' => 'Noviembre', 'label' => 'Nov', 'color' => '#0288d1', 'month' => 11, 'year' => 2025],
            ['key' => 'Diciembre-Enero', 'label' => 'Dic-Ene', 'color' => '#9c27b0', 'month' => 12, 'year' => 2025],
            ['key' => 'Febrero', 'label' => 'Feb', 'color' => '#55a630', 'month' => 2, 'year' => 2026],
            ['key' => 'Marzo', 'label' => 'Mar', 'color' => '#0b2c3d', 'month' => 3, 'year' => 2026],
        ];

        $findScore = function ($userId, $m, $y) use ($allEvaluations) {
            $evals = isset($allEvaluations) ? $allEvaluations : collect();
            $eval = $evals->where('user_id', $userId)
                          ->where('month', $m)
                          ->where('year', $y)
                          ->first();
            return $eval ? floatval($eval->total_score) : null;
        };

        $getUserHistory = function ($user) use ($monthsConfig, $findScore) {
            $history = [];
            foreach ($monthsConfig as $m) {
                if ($m['key'] === 'Diciembre-Enero') {
                    $score = $findScore($user->id, 12, 2025);
                    if ($score === null) {
                        $score = $findScore($user->id, 1, 2026);
                    }
                } else {
                    $score = $findScore($user->id, $m['month'], $m['year']);
                }

                if ($score !== null) {
                    $history[] = [
                        'label' => $m['label'],
                        'color' => $m['color'],
                        'score' => $score
                    ];
                }
            }
            return $history;
        };
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

    <!-- SECCIÓN: TARJETAS DE COLABORADORES - ANCHO COMPLETO EN PÁGINA 1 -->
    <div style="width: 100%;">
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
                    <div style="font-size: 10px; font-weight: bold; color: #004c6c; text-transform: uppercase; margin-bottom: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px; letter-spacing: 0.5px;">
                        {{ $isComercialArea ? 'Incidencia vs Calificación' : 'Evolución Mensual' }}
                    </div>
                    <div style="text-align: center; padding: 6px 0;">
                        <img src="{{ $chartUrl }}" style="width: 100%; max-width: 280px; height: auto; display: block; margin: 0 auto;" alt="{{ $isComercialArea ? 'Incidencia vs Calificación' : 'Evolución Mensual' }}" />
                    </div>
                </div>
            </td>
            
            <!-- PANEL 2: COMPARATIVA EQUIPO (52% ancho) -->
            <td style="width: 52%; vertical-align: top; padding-left: 12px;">
                <div style="background-color: #ffffff; border-radius: 12px; border: 1px solid #cbd5e1; padding: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); min-height: 280px;">
                    <div style="font-size: 10px; font-weight: bold; color: #004c6c; text-transform: uppercase; margin-bottom: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px; letter-spacing: 0.5px;">
                         Comparativa Equipo
                    </div>
                    <div>
                        @foreach($sortedUsers as $u)
                            @php
                                $lbl = $getCargoLabel($u);
                                $parts = explode(' - ', $lbl);
                                $cargoName = $parts[0] ?? '';
                                $name = $parts[1] ?? '';
                                $history = $getUserHistory($u);
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
            Este reporte consolida el desempeño de todos los miembros del equipo del área de<strong>{{ $area }}</strong>. 
            Las calificaciones y desgloses de KPIs se basan en la incidencia estratégica parametrizada en el dashboard.
        </p>
    </div>

    <div class="footer">
        Reporte generado automáticamente por ELITE Dashboard - {{ now()->format('d/m/Y') }} - Página 1 de 1
    </div>

</body>
</html>
