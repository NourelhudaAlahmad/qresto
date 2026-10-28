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

        // Estimated gateway fee on in-app payments, for "net of fees" on
        // the admin dashboard. Real fees arrive with the live gateway.
        'gateway_fee_pct' => (float) env('QRESTO_GATEWAY_FEE_PCT', 2.9),

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

    // Restaurant settings (#29): what an admin may pick.
    'locales' => [
        'en' => 'English',
        'ar' => 'العربية',
    ],

    'currencies' => ['USD', 'EUR', 'GBP', 'TRY', 'SAR', 'AED', 'QAR', 'KWD', 'JOD', 'EGP', 'LBP'],

    'staff' => [
        // Length of "tonight's shift" when a manager adds someone.
        'default_shift_hours' => 8,

        // Roles a manager may create, assign and deactivate. Manager and
        // admin accounts are deliberately absent.
        'manageable_roles' => ['waiter', 'runner', 'cashier', 'kitchen'],
    ],

    'table_session_idle_minutes' => 30,
    'table_session_cookie_minutes' => 1440,
    'table_session_cookie' => 'qresto_table_session',
];
