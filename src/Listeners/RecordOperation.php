<?php

namespace Fayne\Audit\Listeners;

use Fayne\Audit\Jobs\WriteAudit;
use Fayne\Audit\Support\FailureReporter;
use Fayne\Audit\Support\PayloadFilter;
use Fayne\CMS\Support\Operations\OperationCompleted;
use Fayne\CMS\Support\Authorization\AuthorizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class RecordOperation
{
    public function handle(OperationCompleted $event): void
    {
        if (! config('filament-audit.enabled')) {
            return;
        }
        try {
            $context = $event->context;
            $actor = $context['actor'] ?? $this->actor();
            $target = $event->target;
            $scope = app(AuthorizationContext::class)->id();
            $entry = [
                'event_id' => (string) Str::uuid(), 'operation' => $event->name,
                'source' => $context['source'] ?? (\Filament\Facades\Filament::isServing() ? 'filament' : (app()->runningInConsole() ? 'cli' : 'api')),
                'tenant_id' => array_key_exists('tenant_id', $context) ? $context['tenant_id'] : ($target ? ($target->getAttribute('tenant_id') ?? ($target->getAttribute('authorization_scope_id') > 0 ? $target->getAttribute('authorization_scope_id') : null)) : ($scope > 0 ? $scope : null)),
                'actor' => $actor,
                'target' => $target ? ['type' => $target->getMorphClass(), 'id' => (string) $target->getKey(), 'name' => $target->getAttribute('name') ?? $target->getAttribute('title')] : null,
                'payload' => $event->payload,
                'metadata' => array_diff_key($context, array_flip(['actor', 'tenant_id', 'source'])),
                'occurred_at' => now()->utc()->format('Y-m-d H:i:s.u'),
            ];
            $entry = app(PayloadFilter::class)->filter($entry);
            if (strlen(json_encode($entry, JSON_THROW_ON_ERROR)) > config('filament-audit.max_payload_bytes', 262144)) {
                throw new \LengthException('Audit payload exceeds configured limit.');
            }
            $send = static function () use ($entry): void {
                if (! config('filament-audit.enabled')) {
                    return;
                }
                try {
                    $connection = config('filament-audit.queue.connection');
                    if (in_array(config("queue.connections.{$connection}.driver"), ['sync', 'null', null], true)) {
                        throw new \LogicException('Audit requires an asynchronous queue.');
                    }
                    app('queue')->connection($connection)->push(new WriteAudit($entry), '', config('filament-audit.queue.name', 'audit'));
                } catch (Throwable $exception) {
                    FailureReporter::report($exception);
                }
            };
            $db = DB::connection($event->connection);
            if ($db->transactionLevel() > 0) {
                $db->afterCommit($send);
            } else {
                $send();
            }
        } catch (Throwable $exception) {
            FailureReporter::report($exception);
        }
    }

    private function actor(): array
    {
        $user = auth()->user() ?? auth(config('filament-cms.guard', 'admin'))->user();

        return $user ? ['type' => $user->getMorphClass(), 'id' => (string) $user->getAuthIdentifier(), 'name' => $user->name] : ['type' => 'system', 'id' => null, 'name' => 'system'];
    }
}
