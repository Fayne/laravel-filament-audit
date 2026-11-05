<?php

namespace Fayne\Audit;

use Fayne\Audit\Drivers\DriverManager;
use Fayne\Audit\Listeners\RecordOperation;
use Fayne\CMS\Support\Operations\OperationCompleted;
use Illuminate\Support\ServiceProvider;

final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/filament-audit.php', 'filament-audit');
        $this->app->singleton(DriverManager::class);
        $this->app->scoped(\Fayne\Audit\Listeners\FilamentOperations::class);
        \Filament\Panel::configureUsing(function (\Filament\Panel $panel): void {
            if (config('filament-audit.enabled') && config('filament-audit.filament', true)) {
                $panel->resources([\Fayne\Audit\Filament\AuditResource::class]);
            }
        });
    }
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->publishes([__DIR__ . '/../config/filament-audit.php' => config_path('filament-audit.php')], ['filament-audit-config', 'cms-module-assets']);
        if ($this->app->runningInConsole()) {
            $this->commands([\Fayne\Audit\Console\PruneAudit::class, \Fayne\Audit\Console\CheckAudit::class, \Fayne\Audit\Console\InstallPermissions::class]);
        }
        if (! config('filament-audit.enabled')) {
            return;
        }
        $this->app->afterResolving(\Illuminate\Console\Scheduling\Schedule::class, function ($schedule): void {
            $schedule->command('audit:prune')->daily()->withoutOverlapping();
        });
        foreach ([\Illuminate\Auth\Events\Login::class, \Illuminate\Auth\Events\Logout::class] as $event) {
            $this->app['events']->listen($event, [\Fayne\Audit\Listeners\Authentication::class, 'handle']);
        }
        foreach ([
            \Filament\Resources\Events\RecordCreated::class => 'saved',
            \Filament\Resources\Events\RecordUpdated::class => 'saved',
            \Filament\Actions\Events\ActionCalling::class => 'starting',
            \Filament\Actions\Events\ActionCalled::class => 'action',
        ] as $event => $method) {
            // Filament dispatches class names with positional payloads, not always event objects.
            $this->app['events']->listen($event, function (...$arguments) use ($event, $method): void {
                try {
                    $instance = $arguments[0] instanceof $event ? $arguments[0] : new $event(...$arguments);
                    $this->app->make(\Fayne\Audit\Listeners\FilamentOperations::class)->{$method}($instance);
                } catch (\Throwable $exception) {
                    \Fayne\Audit\Support\FailureReporter::report($exception);
                }
            });
        }
        $this->app['events']->listen(OperationCompleted::class, [RecordOperation::class, 'handle']);
    }
}
