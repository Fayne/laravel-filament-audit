<?php

namespace Fayne\Audit\Console;

use Fayne\Audit\Models\AuditEntry;
use Illuminate\Console\Command;

final class PruneAudit extends Command
{
    protected $signature = 'audit:prune';
    protected $description = 'Delete audit entries older than the configured retention period';
    public function handle(): int
    {
        $days = config('filament-audit.retention_days');
        if (! config('filament-audit.enabled') || $days === null || $days === '') {
            return self::SUCCESS;
        }
        if (filter_var($days, FILTER_VALIDATE_INT) === false || (int) $days < 1) {
            $this->error('retention_days must be a positive integer or null.');

            return self::FAILURE;
        }
        $batch = max(1, min(10000, (int) config('filament-audit.prune_batch_size', 500)));
        $cutoff = now()->utc()->subDays((int) $days);
        do {
            $ids = AuditEntry::where('occurred_at', '<', $cutoff)->orderBy('id')->limit($batch)->pluck('id');
            if ($ids->isNotEmpty()) {
                AuditEntry::whereKey($ids)->delete();
            }
        } while ($ids->count() === $batch);

        return self::SUCCESS;
    }
}
