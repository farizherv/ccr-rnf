<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Channels
    |--------------------------------------------------------------------------
    */
    'mail_enabled' => filter_var(env('CCR_NOTIFY_MAIL_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'web_push_enabled' => filter_var(env('CCR_NOTIFY_WEB_PUSH_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Queue Hardening
    |--------------------------------------------------------------------------
    */
    'queue' => env('CCR_NOTIFY_QUEUE_NAME', 'ccr-notify'),
    'cooldown_seconds' => max(3, (int) env('CCR_NOTIFY_COOLDOWN_SECONDS', 8)),
    'lock_seconds' => max(30, (int) env('CCR_NOTIFY_LOCK_SECONDS', 120)),

    /*
    |--------------------------------------------------------------------------
    | Email Hardening
    |--------------------------------------------------------------------------
    */
    'mail_subject_prefix' => trim((string) env('CCR_NOTIFY_MAIL_SUBJECT_PREFIX', '[CCR-RNF]')),
    'mail_allow_local_test' => filter_var(env('CCR_NOTIFY_MAIL_ALLOW_LOCAL_TEST', false), FILTER_VALIDATE_BOOLEAN),
    'mail_global_recipients_enabled' => filter_var(env('CCR_NOTIFY_MAIL_GLOBAL_RECIPIENTS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'mail_global_max_recipients' => max(1, (int) env('CCR_NOTIFY_MAIL_GLOBAL_MAX_RECIPIENTS', 40)),
    'logo_url' => trim((string) env('CCR_NOTIFY_LOGO_URL', '')),

    /*
    |--------------------------------------------------------------------------
    | Web Push (VAPID)
    |--------------------------------------------------------------------------
    */
    'web_push_public_key' => trim((string) env('WEB_PUSH_VAPID_PUBLIC_KEY', '')),
    'web_push_private_key' => trim((string) env('WEB_PUSH_VAPID_PRIVATE_KEY', '')),
    'web_push_subject' => trim((string) env('WEB_PUSH_VAPID_SUBJECT', 'mailto:admin@example.com')),
    'web_push_ttl' => max(60, (int) env('WEB_PUSH_TTL_SECONDS', 300)),
    'web_push_urgency' => trim((string) env('WEB_PUSH_URGENCY', 'normal')),
    'web_push_max_failures' => max(1, (int) env('WEB_PUSH_MAX_FAILURES', 3)),
    'web_push_icon' => trim((string) env('WEB_PUSH_ICON', '/favicon-32.png')),
    'web_push_badge' => trim((string) env('WEB_PUSH_BADGE', '/favicon-16.png')),
];
