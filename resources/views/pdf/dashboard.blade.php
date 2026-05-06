<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard por Área - {{ $area }}</title>
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
        }
        .report-title {
            text-align: right;
            font-size: 14px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
        }
        .summary-box {
            background-color: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 1px solid #e2e8f0;
        }
        .area-name {
            font-size: 18px;
            font-weight: black;
            color: #004C6C;
            margin: 0;
        }
        .period {
            font-size: 10px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        table.stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table.stats-table th {
            background-color: #f1f5f9;
            color: #475569;
            text-align: left;
            padding: 10px;
            font-size: 9px;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
        }
        table.stats-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #f1f5f9;
        }

        .user-name {
            font-weight: bold;
            color: #1e293b;
            font-size: 11px;
        }
        .user-position {
            font-size: 9px;
            color: #64748b;
        }

        .score-pill {
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: bold;
            display: inline-block;
        }
        .score-high { background-color: #dcfce7; color: #166534; }
        .score-mid { background-color: #fef9c3; color: #854d0e; }
        .score-low { background-color: #fee2e2; color: #991b1b; }

        .progress-container {
            width: 100%;
            height: 6px;
            background-color: #f1f5f9;
            border-radius: 3px;
            margin-top: 5px;
        }
        .progress-bar {
            height: 100%;
            border-radius: 3px;
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

    <div class="header">
        <table>
            <tr>
                <td class="brand">ELITE</td>
                <td class="report-title">Informe por Área</td>
            </tr>
        </table>
    </div>

    <div class="summary-box">
        <table width="100%">
            <tr>
                <td>
                    <div class="period">Reporte Mensual</div>
                    <h1 class="area-name">{{ $area }}</h1>
                    <div style="color: #64748b; font-size: 10px; margin-top: 5px;">
                        @php
                            $months = [1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'];
                        @endphp
                        Periodo: {{ $months[$month] }} {{ $year }}
                    </div>
                </td>
                <td align="right">
                    <div style="text-align: right;">
                        <div style="font-size: 8px; text-transform: uppercase; color: #94a3b8; font-weight: bold;">Promedio del Área</div>
                        @php
                            $avg = $evaluations->count() > 0 ? $evaluations->avg('total_score') : 0;
                        @endphp
                        <div style="font-size: 28px; font-weight: black; color: #004C6C;">{{ number_format($avg, 1) }}%</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <h3 style="text-transform: uppercase; font-size: 10px; color: #475569; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;">
        Desempeño Individual de Colaboradores
    </h3>

    <table class="stats-table">
        <thead>
            <tr>
                <th>Colaborador</th>
                <th width="40%">Análisis de Cumplimiento</th>
                <th width="15%" style="text-align: center;">Calificación</th>
            </tr>
        </thead>
        <tbody>
            @forelse($evaluations as $eval)
                <tr>
                    <td>
                        <div class="user-name">{{ $eval->user->name }}</div>
                        <div class="user-position">{{ $eval->user->position ?? 'Colaborador' }}</div>
                    </td>
                    <td>
                        <div style="font-size: 9px; color: #64748b; margin-bottom: 4px;">
                            {{ $eval->results->count() }} KPIs evaluados este periodo
                        </div>
                        <div class="progress-container">
                            @php
                                $score = $eval->total_score;
                                $color = $score >= 90 ? '#10b981' : ($score >= 70 ? '#f59e0b' : '#ef4444');
                            @endphp
                            <div class="progress-bar" style="width: {{ $score }}%; background-color: {{ $color }};"></div>
                        </div>
                    </td>
                    <td align="center">
                        @php
                            $class = $score >= 90 ? 'score-high' : ($score >= 70 ? 'score-mid' : 'score-low');
                        @endphp
                        <div class="score-pill {{ $class }}">
                            {{ number_format($score, 1) }}%
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" align="center" style="padding: 40px; color: #94a3b8;">
                        No se encontraron evaluaciones finalizadas para esta área en el periodo seleccionado.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top: 40px; padding: 20px; background-color: #f1f5f9; border-radius: 8px;">
        <h4 style="margin: 0 0 10px 0; font-size: 10px; color: #004C6C; text-transform: uppercase;">Notas del Reporte</h4>
        <p style="margin: 0; font-size: 9px; color: #64748b;">
            Este reporte consolida el desempeño de todos los miembros del equipo del área de  <strong>{{ $area }}</strong>. 
            Las calificaciones se basan en el cumplimiento de KPIs estratégicos definidos para el periodo {{ $months[$month] }} {{ $year }}.
        </p>
    </div>

    <div class="footer">
        Reporte generado automáticamente por ELITE Dashboard - {{ now()->format('d/m/Y') }} - Página 1 de 1
    </div>

</body>
</html>
