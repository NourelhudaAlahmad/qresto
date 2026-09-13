<?php

return [
    'service_charge_pct' => 0,

    'tip_presets' => [
        0,
        10,
        12.5,
        15,
    ],

    'sla' => [
        'warn_minutes' => 14,
        'late_minutes' => 25,
    ],
    'live' => [
        'waiter_interval' => 5,
        'floor_interval' => 8,
        'kds_interval' => 4,
        'guest_status_interval' => 10,
    ],

    'payment_methods' => [
        'card',
        'wallet',
        'cash',
        'pos',
    ],

    'undo_window_seconds' => 30,

    'table_session_idle_minutes' => 30,
];
