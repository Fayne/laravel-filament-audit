<?php

namespace Fayne\Audit\Listeners;

use Fayne\Audit\Support\FailureReporter;
use Fayne\CMS\Support\Operations\Operations;
use Filament\Actions\Events\ActionCalled;
use Filament\Actions\Enums\ActionStatus;
use Filament\Resources\Events\RecordCreated;
use Filament\Resources\Events\RecordUpdated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

final class FilamentOperations
{
    private \WeakMap $started;

    public function __construct()
    {
        $this->started = new \WeakMap;
    }

    public function starting(\Filament\Actions\Events\ActionCalling $event): void
    {
        $this->started[$event->getAction()] = app(Operations::class)->sequence();
    }

    public function saved(RecordCreated|RecordUpdated $event): void
    {
        try {
            $record = $event->getRecord();
            if ($event instanceof RecordUpdated && ! \Fayne\CMS\Support\Operations\RecordChanges::changed($record, $event->getData())) {
                return;
            }
            app(Operations::class)->completed(Str::snake(class_basename($record)) . ($event instanceof RecordCreated ? '.create' : '.update'),
                method_exists($record, 'operationAuditPayload') ? $record->operationAuditPayload($event->getData()) : $event->getData(), $record, ['source' => 'filament']);
        } catch (Throwable $exception) {
            FailureReporter::report($exception);
        }
    }

    public function action(ActionCalled $event): void
    {
        try {
            $action = $event->getAction();
            $sequence = $this->started[$action] ?? null;
            unset($this->started[$action]);
            if ($sequence !== null && $sequence !== app(Operations::class)->sequence()) {
                return;
            }
            // Custom actions must use the common operation boundary, never infer writes from their names.
            $verb = match (true) {
                $action instanceof \Filament\Actions\CreateAction => 'create',
                $action instanceof \Filament\Actions\EditAction => 'update',
                $action instanceof \Filament\Actions\ForceDeleteAction => 'force_delete',
                $action instanceof \Filament\Actions\DeleteAction => 'delete',
                $action instanceof \Filament\Actions\RestoreAction => 'restore',
                $action instanceof \Filament\Actions\ReplicateAction => 'replicate',
                default => null,
            };
            if (! $verb || $action->getStatus() !== ActionStatus::Success) {
                return;
            }
            $source = $action->getRecord();
            $record = $action instanceof \Filament\Actions\ReplicateAction ? $action->getReplica() : $source;
            if (! $record instanceof Model) {
                return;
            }
            app(Operations::class)->completed(Str::snake(class_basename($record)) . '.' . $verb, method_exists($record, 'operationAuditPayload') ? $record->operationAuditPayload($action->getData()) : $action->getData(), $record, ['source' => 'filament', ...($action instanceof \Filament\Actions\ReplicateAction ? ['source_id' => (string) $source?->getKey()] : [])]);
        } catch (Throwable $exception) {
            FailureReporter::report($exception);
        }
    }
}
