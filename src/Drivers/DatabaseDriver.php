<?php

namespace Fayne\Audit\Drivers;

use Fayne\Audit\Contracts\AuditDriver;
use Fayne\Audit\Models\AuditEntry;

final class DatabaseDriver implements AuditDriver
{
    public function write(array $entry): void
    {
        AuditEntry::query()->firstOrCreate(['event_id' => $entry['event_id']], [...$entry, 'recorded_at' => now()]);
    }
}
