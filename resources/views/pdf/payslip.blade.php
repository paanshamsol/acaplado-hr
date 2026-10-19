@php
    $staff = $payslip->user;
    $brand = $company->brandColor();
    $logo = $company->logoDataUri();
    $money = fn ($value) => number_format((float) $value, 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('Payslip') }} - {{ $payslip->periodLabel() }}</title>
    <style>
        @page { margin: 26px 30px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .brand-bar { height: 5px; background: {{ $brand }}; margin-bottom: 18px; }
        .header td { vertical-align: top; }
        .logo { max-height: 54px; max-width: 170px; }
        .company-name { font-size: 17px; font-weight: bold; color: #111827; margin-bottom: 3px; }
        .company-meta { font-size: 10px; color: #4b5563; line-height: 1.45; }
        .doc-title { text-align: right; }
        .doc-title .label { font-size: 15px; font-weight: bold; letter-spacing: 2px; color: #111827; }
        .doc-title .period { margin-top: 3px; font-size: 11px; color: #4b5563; }
        .section-title { margin: 18px 0 6px; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; }
        .details td { padding: 3px 0; font-size: 11px; vertical-align: top; }
        .details .label { width: 90px; color: #6b7280; }
        .details .value { color: #111827; }
        .panel { border: 1px solid #e5e7eb; border-radius: 4px; padding: 10px 12px; }
        .lines th, .lines td { padding: 7px 8px; font-size: 11px; }
        .lines thead th { background: #f9fafb; border-bottom: 1px solid #e5e7eb; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.6px; color: #6b7280; text-align: left; }
        .lines tbody td { border-bottom: 1px solid #f3f4f6; }
        .lines tfoot td { padding: 9px 8px; font-weight: bold; font-size: 12px; color: {{ $brand }}; border-top: 2px solid {{ $brand }}; }
        .right { text-align: right; }
        .muted { color: #6b7280; font-size: 10px; }
        .footer { margin-top: 22px; border-top: 1px solid #e5e7eb; padding-top: 8px; font-size: 9.5px; color: #6b7280; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="brand-bar"></div>

    <table class="header">
        <tr>
            <td style="width: 60%;">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $company->name }}" class="logo">
                    <div class="company-name" style="margin-top: 6px;">{{ $company->name }}</div>
                @else
                    <div class="company-name">{{ $company->name }}</div>
                @endif
                <div class="company-meta">
                    @if (filled($company->address))<div>{{ $company->address }}</div>@endif
                    @if (filled($company->phone))<div>{{ __('Tel:') }} {{ $company->phone }}</div>@endif
                    @if (filled($company->email))<div>{{ $company->email }}</div>@endif
                </div>
            </td>
            <td class="doc-title" style="width: 40%;">
                <div class="label">{{ __('PAYSLIP') }}</div>
                <div class="period">{{ $payslip->periodLabel() }}</div>
                <div class="muted" style="margin-top: 6px;">
                    {{ __('Generated:') }} {{ $payslip->updated_at?->format('d M Y') ?? now()->format('d M Y') }}
                </div>
            </td>
        </tr>
    </table>

    <div class="section-title">{{ __('Employee') }}</div>
    <table class="details">
        <tr>
            <td class="label">{{ __('Name') }}</td>
            <td class="value">{{ $staff?->name ?? __('—') }}</td>
            <td class="label">{{ __('Staff ID') }}</td>
            <td class="value">{{ $staff?->employee_id ?? __('—') }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('Email') }}</td>
            <td class="value">{{ $staff?->email ?? __('—') }}</td>
            <td class="label">{{ __('Period') }}</td>
            <td class="value">{{ $payslip->periodLabel() }}</td>
        </tr>
    </table>

    <div class="section-title">{{ __('Salary Adjustment') }}</div>
    <table class="lines">
        <thead>
            <tr>
                <th style="width: 45%;">{{ __('Description') }}</th>
                <th class="right" style="width: 35%;">{{ __('Rate / Quantity') }}</th>
                <th class="right" style="width: 20%;">{{ __('Amount (RM)') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ __('Basic pay (rate × hours × days)') }}</td>
                <td class="right">
                    RM {{ $money($payslip->hourly_rate) }} {{ __('per hour') }}
                    × {{ (float) $payslip->hours_of_work }} {{ __('hrs') }}
                    × {{ (int) $payslip->days_of_work }} {{ __('days') }}
                </td>
                <td class="right">{{ $money($payslip->basic) }}</td>
            </tr>
            <tr>
                <td>{{ __('Allowances') }}</td>
                <td class="right">—</td>
                <td class="right">{{ $money($payslip->allowances) }}</td>
            </tr>
            <tr>
                <td>{{ __('Other deductions') }}</td>
                <td class="right">—</td>
                <td class="right">-{{ $money($payslip->other_deductions) }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2">{{ __('Net pay') }}</td>
                <td class="right">RM {{ $money($payslip->net) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        {{ __('Salary adjustment saved by') }}: {{ $payslip->generator?->name ?? __('System') }}
        @if ($payslip->updated_at)
            &nbsp;·&nbsp; {{ $payslip->updated_at->format('d M Y H:i') }}
        @endif
        <br>
        {{ $company->name }} — {{ __('This is a system-generated payslip and does not require a signature.') }}
    </div>
</body>
</html>
