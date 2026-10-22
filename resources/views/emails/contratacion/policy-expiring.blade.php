<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pólizas próximas a vencer</title>
</head>
<body style="margin:0;padding:24px;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#334155;">
    <div style="max-width:680px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;">
        <div style="background:#004C6C;padding:24px;color:#ffffff;">
            <h1 style="margin:0;font-size:20px;">Pólizas próximas a vencer</h1>
            <p style="margin:6px 0 0;font-size:14px;opacity:.85;">{{ $project->name }} · Módulo Contratación</p>
        </div>
        <div style="padding:24px;">
            <p style="margin-top:0;font-size:14px;">
                Las siguientes pólizas vencen en los próximos {{ $alertDays }} días (o ya vencieron). Gestiona su renovación y actualiza la fecha de vigencia en la aplicación.
            </p>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:#f8fafc;text-align:left;">
                        <th style="padding:8px;border-bottom:1px solid #e2e8f0;">Contrato</th>
                        <th style="padding:8px;border-bottom:1px solid #e2e8f0;">Contratista</th>
                        <th style="padding:8px;border-bottom:1px solid #e2e8f0;">Póliza</th>
                        <th style="padding:8px;border-bottom:1px solid #e2e8f0;">Vigencia</th>
                        <th style="padding:8px;border-bottom:1px solid #e2e8f0;">Días</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($policies as $policy)
                        @php($days = $policy->daysToExpire())
                        <tr>
                            <td style="padding:8px;border-bottom:1px solid #f1f5f9;">
                                {{ $policy->contract->contract_code }}
                                @if ($policy->contract->tower)
                                    <br><span style="color:#94a3b8;">{{ $policy->contract->tower->name }}</span>
                                @endif
                            </td>
                            <td style="padding:8px;border-bottom:1px solid #f1f5f9;">{{ $policy->contract->contractor_name }}</td>
                            <td style="padding:8px;border-bottom:1px solid #f1f5f9;">
                                {{ $policy->policy_type }}
                                @if ($policy->policy_number)
                                    <br><span style="color:#94a3b8;">N° {{ $policy->policy_number }}{{ $policy->insurer ? ' · ' . $policy->insurer : '' }}</span>
                                @endif
                            </td>
                            <td style="padding:8px;border-bottom:1px solid #f1f5f9;">{{ $policy->expires_at->format('d/m/Y') }}</td>
                            <td style="padding:8px;border-bottom:1px solid #f1f5f9;font-weight:bold;color:{{ $days < 0 ? '#dc2626' : '#ea580c' }};">
                                {{ $days < 0 ? 'Vencida' : $days }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p style="margin:24px 0 0;">
                <a href="{{ $appUrl }}" style="display:inline-block;background:#004C6C;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:10px;font-size:14px;">Ver contratos</a>
            </p>
        </div>
    </div>
</body>
</html>
