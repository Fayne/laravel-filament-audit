<?php

namespace Fayne\Audit\Jobs;

use Fayne\Audit\Drivers\DriverManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class WriteAudit implements ShouldQueue
{
    use Queueable;
    public int $tries;
    public array $backoff;
    public function __construct(public array $entry)
    {
        $this->tries = (int) config('filament-audit.queue.tries', 5);
        $this->backoff = config('filament-audit.queue.backoff', [10, 60, 300]);
        $this->onConnection(config('filament-audit.queue.connection'));
        $this->onQueue(config('filament-audit.queue.name', 'audit'));
    }
    public function handle(DriverManager $drivers): void
    {
        if (! config('filament-audit.enabled')) {
            return;
        }
        $drivers->driver()->write($this->entry);
    }
}
