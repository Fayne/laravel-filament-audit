<?php

return [
    'enabled' => env('FILAMENT_AUDIT_ENABLED', false),
    'driver' => 'database',
    'database' => ['connection' => null],
    'queue' => ['connection' => env('FILAMENT_AUDIT_QUEUE_CONNECTION', 'database'), 'name' => 'audit', 'tries' => 5, 'backoff' => [10, 60, 300]],
    // Null disables automatic deletion until a retention period is explicitly chosen.
    'retention_days' => env('FILAMENT_AUDIT_RETENTION_DAYS'),
    'prune_batch_size' => 500,
    'visibility' => 'platform_only', // platform_only | tenant_scoped
    'excluded_fields' => ['password', '*password*', '*token*', '*secret*', '*cookie*', 'authorization', '*credential*', '*private_key*', '*recovery_code*', '_token', '_method'],
    'max_payload_bytes' => 262144,
    'filament' => true,
];
