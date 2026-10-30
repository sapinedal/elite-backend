@extends('emails.layouts.inverconstruccion')

{{-- Enlace para restablecer la contraseña. Variables: $name, $minutes, $ctaUrl --}}

@section('content')
    <p style="margin:0 0 16px;font-size:15px;line-height:1.65;color:#475569;text-align:center;">
        Hola, <strong style="color:#0f3b52;">{{ $name }}</strong>. Recibimos una solicitud para restablecer la contraseña de tu cuenta en Elite.
    </p>
    <p style="margin:0 0 20px;font-size:15px;line-height:1.65;color:#475569;text-align:center;">
        Usa el botón para crear una nueva contraseña. El enlace vence en <strong style="color:#0f3b52;">{{ $minutes }} minutos</strong> y solo se puede usar una vez.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 8px;border-radius:12px;background:#f8fafc;">
        <tr>
            <td style="padding:14px 18px;font-size:13px;line-height:1.6;color:#64748b;">
                Si no fuiste tú, ignora este correo: tu contraseña actual seguirá funcionando.
                <br>¿El botón no funciona? Copia este enlace en tu navegador:
                <br><span style="word-break:break-all;color:#004C6C;">{{ $ctaUrl }}</span>
            </td>
        </tr>
    </table>
@endsection
