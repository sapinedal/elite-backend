{{--
    Plantilla base de los correos de Elite (Inverconstrucción).
    Tablas y estilos en línea: es lo único que Gmail / Outlook respetan de forma consistente.

    Variables: $headline, $preheader, $badge, $tone / $toneSoft (color del estado y su fondo), $ctaUrl, $ctaLabel.
    El logo va incrustado (CID) al enviar; al previsualizar con render() se usa data URI.
--}}
@php
    $logoPath = resource_path('images/email/logo-inverconstruccion.png');
    $logoSrc = isset($message)
        ? $message->embed($logoPath)
        : 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
    $tone = $tone ?? '#004C6C';
    $toneSoft = $toneSoft ?? '#e6f0f5';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>{{ $headline }}</title>
</head>
<body style="margin:0;padding:0;background:#eef2f6;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#334155;-webkit-font-smoothing:antialiased;">
    {{-- Texto de vista previa en la bandeja de entrada --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preheader ?? '' }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f6;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 6px 24px rgba(15,23,42,0.06);">
                    {{-- Franja de marca --}}
                    <tr>
                        <td style="padding:0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="height:6px;background:#004C6C;font-size:0;line-height:0;">&nbsp;</td>
                                    <td width="110" style="height:6px;background:#EE9D4C;font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Logo --}}
                    <tr>
                        <td align="center" style="padding:28px 32px 8px;">
                            <img src="{{ $logoSrc }}" width="150" alt="Inverconstrucción" style="display:block;width:150px;max-width:150px;height:auto;border:0;">
                        </td>
                    </tr>

                    {{-- Encabezado del mensaje --}}
                    <tr>
                        <td align="center" style="padding:16px 40px 8px;">
                            @if (!empty($badge))
                                <span style="display:inline-block;padding:6px 14px;border-radius:999px;background:{{ $toneSoft }};color:{{ $tone }};font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;">
                                    {{ $badge }}
                                </span>
                            @endif
                            <h1 style="margin:16px 0 0;font-size:22px;line-height:1.35;font-weight:800;color:#0f3b52;">{{ $headline }}</h1>
                        </td>
                    </tr>

                    {{-- Contenido --}}
                    <tr>
                        <td style="padding:12px 40px 8px;">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- Botón --}}
                    @if (!empty($ctaUrl))
                        <tr>
                            <td align="center" style="padding:20px 40px 32px;">
                                <a href="{{ $ctaUrl }}" style="display:inline-block;background:#004C6C;color:#ffffff;text-decoration:none;padding:13px 28px;border-radius:12px;font-size:14px;font-weight:700;">
                                    {{ $ctaLabel ?? 'Abrir en Elite' }}
                                </a>
                            </td>
                        </tr>
                    @endif

                    {{-- Pie --}}
                    <tr>
                        <td style="padding:0 40px;">
                            <div style="height:1px;background:#e2e8f0;line-height:1px;font-size:0;">&nbsp;</div>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:18px 40px 28px;font-size:11px;line-height:1.6;color:#94a3b8;">
                            Mensaje automático de <strong style="color:#64748b;">Elite</strong> · Inverconstrucción S.A.S.<br>
                            Por favor no respondas a este correo.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
