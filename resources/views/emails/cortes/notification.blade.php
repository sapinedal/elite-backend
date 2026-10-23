<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $headline }}</title>
</head>
<body style="margin:0;padding:24px;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#334155;">
    <div style="max-width:680px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;">
        <div style="background:#004C6C;padding:24px;color:#ffffff;">
            <h1 style="margin:0;font-size:20px;">{{ $headline }}</h1>
            <p style="margin:6px 0 0;font-size:14px;opacity:.85;">{{ $corte->project?->name }} · Registro de Corte</p>
        </div>
        <div style="padding:24px;">
            <p style="margin-top:0;font-size:14px;">{{ $intro }}</p>

            @if ($comment)
                <div style="margin:16px 0;padding:12px 16px;border-radius:10px;font-size:13px;background:{{ $isReturned ? '#fef2f2' : '#f0f9ff' }};border-left:4px solid {{ $isReturned ? '#dc2626' : '#004C6C' }};">
                    <strong>{{ $isReturned ? 'Motivo de la devolución' : 'Comentario' }}:</strong><br>
                    {!! nl2br(e($comment)) !!}
                </div>
            @endif

            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <tbody>
                    <tr>
                        <td style="padding:8px;border-bottom:1px solid #f1f5f9;color:#94a3b8;width:40%;">Código del corte</td>
                        <td style="padding:8px;border-bottom:1px solid #f1f5f9;font-weight:bold;">{{ $corte->code }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px;border-bottom:1px solid #f1f5f9;color:#94a3b8;">Proyecto / Torre</td>
                        <td style="padding:8px;border-bottom:1px solid #f1f5f9;">{{ $corte->project?->name }}{{ $corte->tower ? ' · ' . $corte->tower->name : '' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px;border-bottom:1px solid #f1f5f9;color:#94a3b8;">Proveedor</td>
                        <td style="padding:8px;border-bottom:1px solid #f1f5f9;">{{ $corte->contractor?->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px;border-bottom:1px solid #f1f5f9;color:#94a3b8;">Contrato</td>
                        <td style="padding:8px;border-bottom:1px solid #f1f5f9;">{{ $corte->contract?->contract_code ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px;border-bottom:1px solid #f1f5f9;color:#94a3b8;">Radicado por</td>
                        <td style="padding:8px;border-bottom:1px solid #f1f5f9;">{{ $corte->creator?->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px;border-bottom:1px solid #f1f5f9;color:#94a3b8;">Estado</td>
                        <td style="padding:8px;border-bottom:1px solid #f1f5f9;font-weight:bold;">{{ $statusLabel }}</td>
                    </tr>
                </tbody>
            </table>
            <p style="margin:24px 0 0;">
                <a href="{{ $appUrl }}" style="display:inline-block;background:#004C6C;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:10px;font-size:14px;">Ver corte</a>
            </p>
        </div>
    </div>
</body>
</html>
