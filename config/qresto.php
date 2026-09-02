<?php

return [
    'table_session_idle_minutes' => (int) env(
        'QRESTO_TABLE_SESSION_IDLE_MINUTES',
        30,
    ),
];
