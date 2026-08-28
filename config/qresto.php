<?php

return [
    'service_charge_pct' => 10,

    'tip_presets' => [0, 10, 12.5, 15],

    'sla' => [
        'warn_minutes' => 14,
        'late_minutes' => 25,
    ],

    'payment_methods' => [
        'card',
        'wallet',
        'cash',
        'pos',
    ],

    'undo_window_seconds' => 30,
];
