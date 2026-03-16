<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CRM Quotation - Currency (Single Currency)
    |--------------------------------------------------------------------------
    |
    | Multi-currency is intentionally NOT supported for CRM quotations.
    | Configure the single currency used across the quotation module here.
    |
    */
    'currency_code'   => 'INR',
    'currency_symbol' => '₹',

    /*
    |--------------------------------------------------------------------------
    | CRM Quotation - Default Cost Breakup Heads
    |--------------------------------------------------------------------------
    |
    | Used as a quick template for rate analysis / cost breakup. Users can still
    | add/remove/edit heads per quotation line.
    |
    */
    'quotation_cost_heads' => [
        ['code' => 'FAB_LAB',   'name' => 'Fabrication labour'],
        ['code' => 'CONS',      'name' => 'Consumables'],
        ['code' => 'PAINT_LAB', 'name' => 'Painting labour'],
        ['code' => 'PAINT_MAT', 'name' => 'Paint material'],
        ['code' => 'TRANSPORT', 'name' => 'Transport'],
        ['code' => 'OTHER',     'name' => 'Other'],
    ],

    /*
    |--------------------------------------------------------------------------
    | CRM Lead Scoring
    |--------------------------------------------------------------------------
    |
    | Lightweight, derived scoring so the CRM can prioritise follow-up without
    | introducing a separate scoring engine or changing persisted lead logic.
    |
    */
    'lead_scoring' => [
        'large_value_threshold' => 500000,
        'recent_activity_days' => 14,
        'expected_close_window_days' => 30,
        'due_soon_hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | CRM Activity SLAs
    |--------------------------------------------------------------------------
    |
    | When users do not provide a due date, CRM activities get a default SLA
    | target so follow-ups remain actionable and can participate in reminders.
    |
    */
    'activity_slas' => [
        'call' => 24,
        'meeting' => 48,
        'email' => 8,
        'note' => 72,
        'task' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | CRM Follow-up Reminders
    |--------------------------------------------------------------------------
    |
    | The reminder job only targets overdue, open CRM activities and throttles
    | repeat reminders per activity.
    |
    */
    'follow_up_reminders' => [
        'daily_at' => '09:30',
        'repeat_after_hours' => 24,
        'escalate_after_hours' => 48,
        'escalation_repeat_after_hours' => 48,
    ],

];
