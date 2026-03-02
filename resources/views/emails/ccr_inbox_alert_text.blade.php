@php
    $data = is_array($payload ?? null) ? $payload : [];
    $mailHeading = (string) ($data['mail_heading'] ?? 'CCR');
    $title = (string) ($data['title'] ?? 'CCR Notification');
    $status = (string) ($data['status'] ?? 'INFO');
    $summary = (string) ($data['summary'] ?? '-');
    $component = (string) ($data['component'] ?? '-');
    $customer = (string) ($data['customer'] ?? '-');
    $actor = (string) ($data['actor'] ?? '-');
    $actorLabel = (string) ($data['actor_label'] ?? 'Submitted by');
    $timeText = (string) ($data['time_text'] ?? '-');
    $openUrl = (string) ($data['open_url'] ?? '');
@endphp
{{ $mailHeading }}
{{ $title }}
Status: {{ $status }}

{{ $summary }}

Component: {{ $component }}
@if(trim($customer) !== '' && trim($customer) !== '-')
Customer: {{ $customer }}
@endif
{{ $actorLabel }}: {{ $actor }}
Time: {{ $timeText }}

@if($openUrl !== '')
Open CCR: {{ $openUrl }}
@endif
