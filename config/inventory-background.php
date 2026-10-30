<?php

return [
    // A killed worker can be replaced after its lease expires. The lease must
    // exceed the configured timeout of each broadcast/search HTTP request.
    'lease_seconds' => (int) env('INVENTORY_TASK_LEASE_SECONDS', 300),
    'max_attempts' => (int) env('INVENTORY_TASK_MAX_ATTEMPTS', 8),
    'retry_delays' => [5, 15, 60, 180, 600, 1800, 3600],
];
