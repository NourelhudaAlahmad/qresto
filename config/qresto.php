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

    'payments' => [
        'driver' => env('QRESTO_PAYMENT_DRIVER', 'fake'),

        'enabled_methods' => [
            'card',
            'wallet',
            'cash',
            'pos',
        ],

        'tip_presets' => [
            0,
            10,
            12.5,
            15,
        ],

        'fake' => [
            'latency_ms' => (int) env(
                'QRESTO_FAKE_PAYMENT_LATENCY_MS',
                0,
            ),
        ],
    ],

    'undo_window_seconds' => 6,

    'table_session_idle_minutes' => 30,

    'table_session_cookie' => 'qresto_table_session',
];
