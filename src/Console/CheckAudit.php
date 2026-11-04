<?php

namespace Fayne\Audit\Console;

use Fayne\Audit\Models\AuditEntry;
use Illuminate\Console\Command;
use Throwable;

final class CheckAudit extends Command
{
    protected $signature = 'audit:check';
    protected $description = 'Check audit configuration and database availability';
    public function handle(): int
    {
        if (! config('filament-audit.enabled')) {
            $this->info('Audit disabled.');

            return self::SUCCESS;
        }
        try {
            $connection = config('filament-audit.queue.connection');
            $queueDriver = config("queue.connections.{$connection}.driver");
            if (! $queueDriver || in_array($queueDriver, ['sync', 'null'])) {
                throw new \RuntimeException('Configure an asynchronous, durable queue connection.');
            }
            if (! in_array(config('filament-audit.visibility'), ['platform_only', 'tenant_scoped'])) {
                throw new \RuntimeException('Invalid audit visibility.');
            }
            if (config('filament-audit.driver') === 'database') {
                AuditEntry::query()->limit(1)->get();
            }
            $days = config('filament-audit.retention_days');
            if ($days !== null && $days !== '' && (filter_var($days, FILTER_VALIDATE_INT) === false || (int) $days < 1)) {
                throw new \RuntimeException('Invalid retention_days.');
            }
            $this->info('Audit configuration and database check passed; worker delivery requires an integration check.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Audit check failed: ' . $exception::class);

            return self::FAILURE;
        }
    }
}
