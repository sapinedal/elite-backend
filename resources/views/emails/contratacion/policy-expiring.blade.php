@extends('emails.layouts.inverconstruccion')

{{-- Alerta de pólizas de contratos próximas a vencer. Variables: $project, $policies, $alertDays --}}

@section('content')
    <p style="margin:0 0 20px;font-size:15px;line-height:1.65;color:#475569;text-align:center;">
        Estas pólizas de <strong style="color:#0f3b52;">{{ $project->name }}</strong> vencen en los próximos {{ $alertDays }} días o ya vencieron.
        Gestiona su renovación y actualiza la fecha de vigencia del contrato en Elite.
    </p>

    @foreach ($policies as $policy)
        @php
            $days = $policy->daysToExpire();
            $expired = $days < 0;
            $urgent = $days <= 15;
            $chipBg = $expired ? '#dc2626' : ($urgent ? '#fdecec' : '#fdf1e4');
            $chipFg = $expired ? '#ffffff' : ($urgent ? '#b91c1c' : '#c2701f');
        @endphp
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 10px;border:1px solid #e2e8f0;border-radius:14px;">
            <tr>
                <td style="padding:14px 16px;">
                    <p style="margin:0;font-size:14px;font-weight:700;color:#0f3b52;">
                        {{ $policy->contract->contract_code }}
                        <span style="font-weight:600;color:#64748b;"> · {{ $policy->contract->contractor_name }}</span>
                    </p>
                    <p style="margin:4px 0 0;font-size:12px;line-height:1.5;color:#64748b;">
                        {{ $policy->policy_type }}
                        @if ($policy->policy_number)
                            · N.º {{ $policy->policy_number }}
                        @endif
                        @if ($policy->insurer)
                            · {{ $policy->insurer }}
                        @endif
                        @if ($policy->contract->tower)
                            · {{ $policy->contract->tower->name }}
                        @endif
                    </p>
                    <p style="margin:4px 0 0;font-size:12px;color:#94a3b8;">Vigencia hasta {{ $policy->expires_at->format('d/m/Y') }}</p>
                </td>
                <td width="96" align="center" valign="middle" style="padding:14px 16px 14px 0;">
                    <span style="display:inline-block;padding:6px 10px;border-radius:10px;background:{{ $chipBg }};color:{{ $chipFg }};font-size:12px;font-weight:700;white-space:nowrap;">
                        {{ $expired ? 'Vencida' : ($days === 0 ? 'Vence hoy' : $days . ' días') }}
                    </span>
                </td>
            </tr>
        </table>
    @endforeach
@endsection
