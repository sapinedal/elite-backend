@extends('emails.layouts.inverconstruccion')

{{--
    Aviso de factura cargada para registrarla en Multifox (CorteInvoiceMail).
    Variables: $intro, $observation, $isAdjustment, $invoiceDetails, $corteDetails, $attached
--}}

@section('content')
    <p style="margin:0 0 20px;font-size:15px;line-height:1.65;color:#475569;text-align:center;">{{ $intro }}</p>

    @if ($observation)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 22px;">
            <tr>
                <td style="padding:14px 18px;border-radius:12px;background:{{ $isAdjustment ? '#fdf1e4' : '#f0f7fa' }};border-left:4px solid {{ $isAdjustment ? '#EE9D4C' : '#004C6C' }};">
                    <p style="margin:0 0 6px;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:{{ $isAdjustment ? '#c2701f' : '#004C6C' }};">
                        {{ $isAdjustment ? 'Motivo del cambio' : 'Observación' }}
                    </p>
                    <p style="margin:0;font-size:14px;line-height:1.6;color:#334155;">{!! nl2br(e($observation)) !!}</p>
                </td>
            </tr>
        </table>
    @endif

    @foreach ([['Documento a registrar', $invoiceDetails, '#004C6C'], ['Corte', $corteDetails, '#94a3b8']] as [$title, $rows, $titleColor])
        <p style="margin:0 0 8px;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:{{ $titleColor }};">{{ $title }}</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;border-radius:14px;background:#f8fafc;">
            <tr>
                <td style="padding:6px 18px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">
                        @foreach ($rows as $label => $value)
                            <tr>
                                <td style="padding:10px 0;color:#94a3b8;width:42%;{{ $loop->last ? '' : 'border-bottom:1px solid #e9eef4;' }}">{{ $label }}</td>
                                <td style="padding:10px 0;color:#1e293b;font-weight:600;{{ $loop->last ? '' : 'border-bottom:1px solid #e9eef4;' }}">{{ $value }}</td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        </table>
    @endforeach

    <p style="margin:4px 0 0;font-size:12px;line-height:1.6;color:#94a3b8;text-align:center;">
        @if ($attached)
            &#128206; El archivo de la factura va adjunto a este correo.
        @else
            Descarga el archivo de la factura desde el corte en Elite.
        @endif
    </p>
@endsection
