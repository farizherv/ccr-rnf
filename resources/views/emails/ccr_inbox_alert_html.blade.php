@php
    $data = is_array($payload ?? null) ? $payload : [];
    $mailHeading = (string) ($data['mail_heading'] ?? 'CCR');
    $title = (string) ($data['title'] ?? 'CCR Notification');
    $status = strtoupper(trim((string) ($data['status'] ?? 'INFO')));
    $summary = (string) ($data['summary'] ?? '-');
    $component = (string) ($data['component'] ?? '-');
    $customer = (string) ($data['customer'] ?? '-');
    $actor = (string) ($data['actor'] ?? '-');
    $actorLabel = (string) ($data['actor_label'] ?? 'Submitted by');
    $timeText = (string) ($data['time_text'] ?? '-');
    $openUrl = (string) ($data['open_url'] ?? '');
    $logoUrl = (string) ($data['logo_url'] ?? '');

    $badgeText = $status !== '' ? $status : 'INFO';
    $badgeBg = '#eef2ff';
    $badgeFg = '#1f3a8a';
    $badgeBd = '#c7d2fe';

    if (str_contains($status, 'APPROV')) {
        $badgeBg = '#ecfdf3';
        $badgeFg = '#15803d';
        $badgeBd = '#86efac';
    } elseif (str_contains($status, 'REJECT')) {
        $badgeBg = '#fef2f2';
        $badgeFg = '#b91c1c';
        $badgeBd = '#fca5a5';
    } elseif (str_contains($status, 'WAIT')) {
        $badgeBg = '#fffbeb';
        $badgeFg = '#a16207';
        $badgeBd = '#fcd34d';
    }

    $hasCustomer = trim($customer) !== '' && trim($customer) !== '-';
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,sans-serif;color:#0f172a;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#f5f7fb;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width:640px;background:#ffffff;border:1px solid #dbe5f3;border-radius:14px;overflow:hidden;">
                <tr>
                    <td style="padding:20px 22px;border-bottom:1px solid #e8eef8;">
                        @if($logoUrl !== '')
                            <img src="{{ $logoUrl }}" alt="CCR Logo" style="height:42px;width:auto;display:block;margin-bottom:12px;">
                        @endif
                        <div style="font-size:18px;line-height:1.2;font-weight:800;color:#334155;margin:0 0 8px 0;">{{ $mailHeading }}</div>
                        <div style="font-size:24px;line-height:1.2;font-weight:800;color:#0f172a;margin:0 0 10px 0;">{{ $title }}</div>
                        <span style="display:inline-block;padding:6px 12px;border-radius:999px;border:1px solid {{ $badgeBd }};background:{{ $badgeBg }};color:{{ $badgeFg }};font-size:12px;font-weight:800;letter-spacing:.2px;">{{ $badgeText }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:18px 22px 8px 22px;">
                        <div style="font-size:14px;line-height:1.6;color:#334155;margin:0 0 14px 0;">{{ $summary }}</div>
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border-collapse:collapse;">
                            <tr>
                                <td style="padding:6px 0;color:#64748b;font-size:13px;">Component</td>
                                <td style="padding:6px 0;color:#0f172a;font-size:13px;font-weight:700;" align="right">{{ $component }}</td>
                            </tr>
                            @if($hasCustomer)
                                <tr>
                                    <td style="padding:6px 0;color:#64748b;font-size:13px;">Customer</td>
                                    <td style="padding:6px 0;color:#0f172a;font-size:13px;font-weight:700;" align="right">{{ $customer }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td style="padding:6px 0;color:#64748b;font-size:13px;">{{ $actorLabel }}</td>
                                <td style="padding:6px 0;color:#0f172a;font-size:13px;font-weight:700;" align="right">{{ $actor }}</td>
                            </tr>
                            <tr>
                                <td style="padding:6px 0;color:#64748b;font-size:13px;">Time</td>
                                <td style="padding:6px 0;color:#0f172a;font-size:13px;font-weight:700;" align="right">{{ $timeText }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:14px 22px 22px 22px;">
                        @if($openUrl !== '')
                            <a href="{{ $openUrl }}"
                               style="display:inline-block;padding:12px 18px;border-radius:10px;background:#ef4444;color:#ffffff;text-decoration:none;font-size:14px;font-weight:800;">
                                Open CCR
                            </a>
                            <div style="margin-top:10px;font-size:12px;color:#64748b;line-height:1.5;">
                                If the button does not work, copy this link:<br>
                                <a href="{{ $openUrl }}" style="color:#2563eb;word-break:break-all;">{{ $openUrl }}</a>
                            </div>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
