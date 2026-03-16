<?php

return [
    'channels' => [
        'database' => 'In-app',
        'mail' => 'Email',
        'push' => 'Browser Push',
    ],

    'default_preferences' => [
        'channels' => [
            'database' => true,
            'mail' => true,
            'push' => true,
        ],
    ],

    'types' => [
        'system_alert' => [
            'label' => 'System Alerts',
            'description' => 'General ERP alerts and operator-triggered test notifications.',
        ],
        'approval' => [
            'label' => 'Approvals',
            'description' => 'Approval requests, completion, and rejection notices.',
        ],
        'crm.follow_up.reminder' => [
            'label' => 'CRM Follow-up Reminders',
            'description' => 'Assigned CRM follow-ups that are due or overdue.',
        ],
        'crm.follow_up.escalation' => [
            'label' => 'CRM Follow-up Escalations',
            'description' => 'Escalated overdue CRM follow-ups.',
        ],
        'machinery.maintenance' => [
            'label' => 'Maintenance Alerts',
            'description' => 'Upcoming maintenance due notifications.',
        ],
        'machinery.calibration' => [
            'label' => 'Calibration Alerts',
            'description' => 'Calibration due and overdue notifications.',
        ],
        'pwa_push_report_test' => [
            'label' => 'Push Report Tests',
            'description' => 'Push test alerts sent from the delivery report.',
        ],
    ],

    'retention' => [
        'read_days' => 90,
        'daily_at' => '03:45',
    ],
];
