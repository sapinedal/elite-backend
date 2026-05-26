<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Reporte de Evaluación - {{ $evaluation->user->name }}</title>
    <style>
        @page {
            margin: 1cm;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #334155;
            line-height: 1.4;
            font-size: 11px;
        }

        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #004C6C;
            padding-bottom: 10px;
        }

        .header table {
            width: 100%;
        }

        .brand {
            font-size: 24px;
            font-weight: bold;
            color: #004C6C;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .report-title {
            text-align: right;
            font-size: 14px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
        }

        .meta-section {
            background-color: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .meta-grid {
            width: 100%;
        }

        .meta-label {
            color: #94a3b8;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8px;
            margin-bottom: 2px;
        }

        .meta-value {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
        }

        .score-box {
            background-color: #004C6C;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-align: center;
        }

        .score-value {
            font-size: 24px;
            font-weight: bold;
        }

        .score-label {
            font-size: 8px;
            text-transform: uppercase;
            opacity: 0.8;
        }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #004C6C;
            margin-bottom: 10px;
            text-transform: uppercase;
            border-left: 4px solid #004C6C;
            padding-left: 8px;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table.data-table th {
            background-color: #f1f5f9;
            color: #475569;
            text-align: left;
            padding: 8px;
            font-size: 9px;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
        }

        table.data-table td {
            padding: 8px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .kpi-row {
            background-color: #f8fafc;
        }

        .kpi-name {
            font-weight: bold;
            color: #334155;
        }

        .indicator-block {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }

        .indicator-header {
            margin-bottom: 8px;
        }

        .indicator-title {
            font-size: 11px;
            font-weight: bold;
            color: #1e293b;
        }

        .indicator-formula {
            font-size: 9px;
            color: #64748b;
            font-style: italic;
        }

        .level-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            color: white;
            display: inline-block;
            min-width: 60px;
            text-align: center;
        }

        /* Colores del Semáforo - Mapeo Extendido */
        .level-excelente,
        .level-excellent,
        .level-optimal,
        .level-óptimo,
        .level-alto {
            background-color: #10b981;
        }

        .level-aceptable,
        .level-acceptable,
        .level-medio,
        .level-bueno {
            background-color: #f59e0b;
        }

        .level-riesgo,
        .level-at_risk,
        .level-alerta {
            background-color: #f97316;
        }

        .level-deficiente,
        .level-deficient,
        .level-inadequate,
        .level-bajo,
        .level-crítico {
            background-color: #ef4444;
        }

        .level-na,
        .level-no_aplica,
        .level-no-aplica,
        .level-pendiente {
            background-color: #94a3b8;
        }

        .indicator-na {
            background-color: #f1f5f9;
            border-color: #e2e8f0;
            color: #94a3b8;
        }

        .indicator-na .indicator-title {
            color: #94a3b8;
            text-decoration: line-through;
        }

        .ai-analysis {
            background-color: #eff6ff;
            padding: 10px;
            border-left: 4px solid #3b82f6;
            font-style: italic;
            color: #1e40af;
            margin-top: 5px;
            font-size: 10px;
        }

        /* Tabla de Detalle */
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 8px;
            border: 1px solid #e2e8f0;
        }

        .detail-table th {
            background-color: #f8fafc;
            color: #64748b;
            padding: 4px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }

        .detail-table td {
            padding: 4px;
            border: 1px solid #e2e8f0;
        }

        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
            padding: 10px 0;
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

        if (!function_exists('replaceEmojis')) {
            function replaceEmojis($text) {
                $emojis = [
                    '🟢' => '<span style="color: #10b981; font-size: 11px;">●</span>',
                    '🟡' => '<span style="color: #f59e0b; font-size: 11px;">●</span>',
                    '🔴' => '<span style="color: #ef4444; font-size: 11px;">●</span>'
                ];
                $text = str_replace('(?)', '', $text);
                return str_replace(array_keys($emojis), array_values($emojis), $text);
            }
        }
    @endphp

    <div class="header">
        <table>
            <tr>
                <td class="brand" style="vertical-align: middle;">
                    @if(!empty($logoBase64))
                        <img src="data:image/svg+xml;base64,{{ $logoBase64 }}" style="height: 35px; vertical-align: middle; margin-right: 10px;" />
                    @endif
                    <span style="vertical-align: middle;">ELITE</span>
                </td>
                <td class="report-title" style="vertical-align: middle;">Reporte de Desempeño</td>
            </tr>
        </table>
    </div>

    <div class="meta-section">
        <table class="meta-grid">
            <tr>
                <td width="40%">
                    <div class="meta-label">Colaborador</div>
                    <div class="meta-value">{{ $evaluation->user->name }}</div>
                    <div style="margin-top: 10px;">
                        <div class="meta-label">Cargo / Área</div>
                        <div class="meta-value" style="font-size: 10px;">
                            {{ is_object($evaluation->user->position) ? $evaluation->user->position->name : ($evaluation->user->position ?? 'Colaborador') }}</div>
                    </div>
                </td>
                <td width="30%">
                    <div class="meta-label">Periodo Evaluado</div>
                    <div class="meta-value">
                        @php
                            $months = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
                        @endphp
                        {{ $months[$evaluation->month] }} {{ $evaluation->year }}
                    </div>
                    <div style="margin-top: 10px;">
                        <div class="meta-label">Fecha de Generación</div>
                        <div class="meta-value" style="font-size: 10px;">{{ now()->format('d/m/Y h:i A') }}</div>
                    </div>
                </td>
                <td width="30%" align="right">
                    <div class="score-box">
                        <div class="score-label">Puntaje Global</div>
                        <div class="score-value">{{ number_format($evaluation->total_score, 2) }}%</div>
                    </div>
                    <div style="margin-top: 10px; text-align: right;">
                        <div class="meta-label">Evaluación elaborada por</div>
                        <div class="meta-value" style="font-size: 9px; color: #64748b;">
                            {{ isset($evaluation->evaluador) && is_object($evaluation->evaluador->position) ? $evaluation->evaluador->position->name : (isset($evaluation->evaluador) && $evaluation->evaluador->position ? $evaluation->evaluador->position : 'Sistema ELITE') }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">Resumen de KPIs</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Nombre del KPI</th>
                <th width="15%">Peso</th>
                <th width="15%">Meta</th>
                <th width="15%">Real</th>
                <th width="15%">Cumplimiento</th>
            </tr>
        </thead>
        <tbody>
            @foreach($evaluation->results as $result)
                <tr class="kpi-row">
                    <td class="kpi-name">{{ $result->kpi_name }}</td>
                    <td>{{ number_format($result->kpi_weight, 2) }}%</td>
                    <td>{{ number_format($result->kpi_target, 2) }}%</td>
                    <td>{{ number_format($result->real_value, 2) }}%</td>
                    <td style="font-weight: bold; color: #004C6C;">{{ number_format($result->score, 2) }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">Detalle de Indicadores</div>
    @foreach($evaluation->results as $result)
        <div style="margin-top: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;">
            <span style="font-weight: bold; color: #475569; font-size: 10px;">KPI: {{ $result->kpi_name }}</span>
        </div>

        @if(isset($result->details['indicator_results']))
            @foreach($result->details['indicator_results'] as $ind)
                @php
                    $isNa = isset($ind['not_applicable']) && $ind['not_applicable'];
                @endphp
                <div class="indicator-block {{ $isNa ? 'indicator-na' : '' }}">
                    <table width="100%">
                        <tr>
                            <td width="70%">
                                <div class="indicator-header">
                                    <div class="indicator-title">
                                        {{ $ind['indicator_name'] }}
                                        @if($isNa) (NO APLICA) @endif
                                    </div>
                                    <div class="indicator-formula">Fórmula: {{ $ind['formula'] }}</div>
                                </div>
                            </td>
                            <td width="30%" align="right">
                                @php
                                    $level = $ind['level'] ?? '';
                                    if ($isNa)
                                        $level = 'na';
                                    $levelClass = strtolower(str_replace([' ', '_'], '-', $level));
                                @endphp
                                <div class="level-badge level-{{ $levelClass }}">
                                    {{ $isNa ? 'N/A' : ($ind['qualification'] ?: ($ind['level'] ?: 'Pendiente')) }}
                                </div>
                                <div style="font-size: 10px; font-weight: bold; margin-top: 4px; color: #004C6C;">
                                    @if(!$isNa)
                                        {{ number_format($ind['calculated_value'] ?? 0, 2) }}{{ $ind['unit'] ?? '%' }}
                                    @else
                                        -
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </table>

                    @if(!$isNa && isset($ind['variables']))
                        <div style="margin-top: 5px; font-size: 9px; color: #64748b;">
                            <strong>Variables:</strong>
                            @foreach($ind['variables'] as $name => $val)
                                <span style="margin-right: 15px;">{{ str_replace('_', ' ', $name) }}: <strong>{{ $val }}</strong></span>
                            @endforeach
                        </div>
                    @endif

                    @if(!$isNa && isset($ind['tablaDetalle']) && isset($ind['tablaDetalle']['headers']) && count($ind['tablaDetalle']['rows']) > 0)
                        <table class="detail-table">
                            <thead>
                                <tr>
                                    @foreach($ind['tablaDetalle']['headers'] as $header)
                                        <th>{{ $header }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(array_slice($ind['tablaDetalle']['rows'], 0, 15) as $row) {{-- Límite de 15 filas para no
                                    romper el PDF --}}
                                    <tr>
                                        @foreach($row as $cell)
                                            <td>{{ $cell }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                                @if(count($ind['tablaDetalle']['rows']) > 15)
                                    <tr>
                                        <td colspan="{{ count($ind['tablaDetalle']['headers']) }}"
                                            style="text-align: center; color: #94a3b8; font-style: italic;">
                                            ... mostrando 15 de {{ count($ind['tablaDetalle']['rows']) }} registros ...
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    @endif

                     @if(isset($ind['ai_analysis']))
                        <div class="ai-analysis">
                            <strong>Análisis de Resultados:</strong> {!! replaceEmojis($ind['ai_analysis']) !!}
                        </div>
                    @endif
                </div>
            @endforeach
        @endif

        @if($result->ai_analysis)
            <div
                style="margin: 10px 0; padding: 10px; background-color: #f8fafc; border-radius: 6px; border: 1px dashed #cbd5e1;">
                <div class="meta-label">Interpretación Estratégica (KPI)</div>
                <div style="font-size: 10px; color: #475569; font-style: italic;">"{!! replaceEmojis($result->ai_analysis) !!}"</div>
            </div>
        @endif
    @endforeach

    @if($evaluation->general_analysis)
        <div style="page-break-before: auto; margin-top: 30px;">
            <div class="section-title">Análisis General de la Evaluación</div>
            <div style="padding: 15px; background-color: #f1f5f9; border-radius: 8px; font-size: 10px; line-height: 1.6;">
                {!! replaceEmojis($evaluation->general_analysis) !!}
            </div>
        </div>
    @endif

    <div class="footer">
        Este documento es un reporte automático generado por el Sistema de Gestión de Desempeño ELITE. Página 1 de 1
    </div>

</body>

</html>