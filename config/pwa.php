<?php

return [
    'push' => [
        'enabled' => (bool) env('PWA_PUSH_ENABLED', true),
        // Server dispatch uses minishlink/web-push when present in vendor.
        'vapid_public_key' => (string) env('VAPID_PUBLIC_KEY', ''),
        'vapid_private_key' => (string) env('VAPID_PRIVATE_KEY', ''),
        'vapid_subject' => (string) env('VAPID_SUBJECT', ''),
        'ttl' => (int) env('PWA_PUSH_TTL', 300),
        'urgency' => (string) env('PWA_PUSH_URGENCY', 'normal'),
        'queue' => (string) env('PWA_PUSH_QUEUE', 'default'),
        'prune_days' => (int) env('PWA_PUSH_PRUNE_DAYS', 90),
        'delete_disabled_after_days' => (int) env('PWA_PUSH_DELETE_DISABLED_AFTER_DAYS', 180),
        'worker_fallback_enabled' => (bool) env('PWA_QUEUE_WORKER_FALLBACK', true),
        'worker_fallback_connection' => (string) env('PWA_QUEUE_WORKER_CONNECTION', env('QUEUE_CONNECTION', 'database')),
        'worker_fallback_queues' => (string) env('PWA_QUEUE_WORKER_QUEUES', 'default'),
    ],

    'background_sync' => [
        'enabled' => (bool) env('PWA_BACKGROUND_SYNC', true),

        // Forms posting to these prefixes are auto-treated as critical when offline.
        'critical_form_path_prefixes' => [
            '/machines',
            '/machine-assignments',
            '/machine-calibrations',
            '/maintenance',
            '/store-issues',
            '/store-returns',
            '/store-requisitions',
            '/material-receipts',
            '/purchase-orders',
            '/purchase-indents',
        ],
    ],
];
