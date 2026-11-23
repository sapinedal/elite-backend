@extends('emails.layouts.inverconstruccion')

{{--
    Aprobación de Contabilidad (común a cortes, órdenes, anticipos y cuentas de cobro). Textos y color: AccountingApprovalMail.
    Variables: $intro, $comment, $commentLabel, $isReturned, $details
--}}

@section('content')
    <p style="margin:0 0 20px;font-size:15px;line-height:1.65;color:#475569;text-align:center;">{{ $intro }}</p>

    @if ($comment)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 22px;">
            <tr>
                <td style="padding:14px 18px;border-radius:12px;background:{{ $isReturned ? '#fef2f2' : '#f0f7fa' }};border-left:4px solid {{ $isReturned ? '#dc2626' : '#004C6C' }};">
                    <p style="margin:0 0 6px;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:{{ $isReturned ? '#b91c1c' : '#004C6C' }};">{{ $commentLabel }}</p>
                    <p style="margin:0;font-size:14px;line-height:1.6;color:#334155;">{!! nl2br(e($comment)) !!}</p>
                </td>
            </tr>
        </table>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-radius:14px;background:#f8fafc;">
        <tr>
            <td style="padding:6px 18px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">
                    @foreach ($details as $label => $value)
                        <tr>
                            <td style="padding:10px 0;color:#94a3b8;width:42%;{{ $loop->last ? '' : 'border-bottom:1px solid #e9eef4;' }}">{{ $label }}</td>
                            <td style="padding:10px 0;color:#1e293b;font-weight:600;{{ $loop->last ? '' : 'border-bottom:1px solid #e9eef4;' }}">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>
@endsection
