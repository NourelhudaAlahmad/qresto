<?php

return [
    /*
     * The currency a restaurant row falls back to when none is set, and the
     * default for restaurants.currency and orders.currency in the database.
     * Money itself has no default: every Money names its currency (#49).
     */
    'currency' => env('QRESTO_CURRENCY', 'USD'),

    'auth' => [
        /*
         * How long a staff session lasts when "Keep me signed in on this
         * device" is ticked (#51): a 12-hour shift plus an hour of slack.
         * An unticked session keeps the stock SESSION_LIFETIME.
         */
        'remembered_session_minutes' => (int) env('QRESTO_REMEMBERED_SESSION_MINUTES', 13 * 60),
    ],

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

        // #35: QRESTO_PAYMENT_DRIVER=stripe switches the gateway. The test
        // suite always runs on the fake driver (phpunit.xml).
        'stripe' => [
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            // Seconds a signed webhook stays valid (replay window).
            'webhook_tolerance' => (int) env('STRIPE_WEBHOOK_TOLERANCE', 300),
            // Country for Apple Pay / Google Pay sheets.
            'country' => env('STRIPE_COUNTRY', 'US'),
        ],
    ],

    'undo_window_seconds' => 6,

    // Menu management (#31): dish photos.
    'menu' => [
        'photo_disk' => env('QRESTO_MENU_PHOTO_DISK', 'public'),
        'photo_min_width' => 1200,
        'thumb_size' => 240,
    ],

    // System health (#30). A heartbeat older than this reads "unknown".
    // KDS screens post every 30s and printers every minute; the gateway is
    // probed every five minutes by qresto:probe-gateway.
    'integrations' => [
        'stale_after_seconds' => [
            'kds' => 120,
            'printer' => 300,
            'gateway' => 900,
        ],
    ],

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

    /*
     * How long operational rows outlive their use (#57); `model:prune`
     * deletes them daily. Days are counted from the guest's last activity
     * (sessions, carts) or from receipt (webhook events). The audit trail
     * (order_events, permission_audits, staff_audits), orders, payments and
     * any session with an order are kept: a session with an order carries
     * the party size that covers are counted from.
     */
    /*
     * In production, refuse to serve while App\Support\DeploymentCheck finds
     * a development default (#59). Turn off only for a deliberate exception.
     */
    'deploy' => [
        'enforce' => (bool) env('QRESTO_ENFORCE_DEPLOY_CHECKS', true),
    ],

    'retention' => [
        // A scan that never became an order.
        'table_sessions_days' => (int) env('QRESTO_RETAIN_TABLE_SESSIONS_DAYS', 30),
        // A cart is dead once its session has gone quiet; orders copy what
        // they need into order_lines.
        'carts_days' => (int) env('QRESTO_RETAIN_CARTS_DAYS', 7),
        // A replay guard, not a record: Stripe retries for three days and
        // keeps events resendable for thirty.
        'webhook_events_days' => (int) env('QRESTO_RETAIN_WEBHOOK_EVENTS_DAYS', 30),
    ],
];
