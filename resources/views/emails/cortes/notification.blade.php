@extends('emails.layouts.inverconstruccion')

{{--
    Correo del flujo de Registro de Corte. Los textos, el color y el botón los define CorteNotificationMail según el evento.
    Variables: $corte, $intro, $comment, $commentLabel, $isReturned, $statusLabel, $progress, $details
--}}

@section('content')
    <p style="margin:0 0 20px;font-size:15px;line-height:1.65;color:#475569;text-align:center;">{{ $intro }}</p>

    {{-- Comentario del revisor / residente --}}
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

    {{-- Línea de tiempo: Radicado -> Interventor -> Director de Obra -> Gerencia --}}
    @php
        $stepStyles = [
            'aprobado' => ['bg' => '#16a34a', 'fg' => '#ffffff', 'icon' => '&#10003;', 'text' => '#15803d', 'label' => 'Aprobado'],
            'devuelto' => ['bg' => '#dc2626', 'fg' => '#ffffff', 'icon' => '&#8617;', 'text' => '#b91c1c', 'label' => 'Devuelto'],
            'actual' => ['bg' => '#EE9D4C', 'fg' => '#ffffff', 'icon' => '&#9679;', 'text' => '#c2701f', 'label' => 'En revisión'],
            'pendiente' => ['bg' => '#e2e8f0', 'fg' => '#94a3b8', 'icon' => '&#9675;', 'text' => '#94a3b8', 'label' => 'Pendiente'],
        ];
        $steps = array_merge(
            [['label' => 'Radicado', 'state' => 'aprobado', 'user' => $corte->creator?->name]],
            array_map(fn ($p) => ['label' => $p['label'], 'state' => $p['state'], 'user' => $p['user']], $progress)
        );
        $steps[0]['stateLabel'] = $corte->creator?->name ?? 'Completado';
    @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 22px;border:1px solid #e2e8f0;border-radius:14px;">
        <tr>
            @foreach ($steps as $step)
                @php $style = $stepStyles[$step['state']]; @endphp
                <td align="center" valign="top" width="25%" style="padding:16px 4px 14px;">
                    <div style="width:30px;height:30px;line-height:30px;border-radius:50%;background:{{ $style['bg'] }};color:{{ $style['fg'] }};font-size:14px;font-weight:700;text-align:center;margin:0 auto;">{!! $style['icon'] !!}</div>
                    <p style="margin:8px 0 2px;font-size:12px;font-weight:700;color:#0f3b52;">{{ $step['label'] }}</p>
                    <p style="margin:0;font-size:11px;font-weight:600;color:{{ $style['text'] }};">{{ $step['stateLabel'] ?? $style['label'] }}</p>
                </td>
            @endforeach
        </tr>
    </table>

    {{-- Datos del corte --}}
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
