<?php

namespace AuditTests;

use Fayne\Audit\AuditServiceProvider;
use Fayne\Audit\Filament\AuditResource;
use Fayne\CMS\Support\Operations\Operations;
use Fayne\CMS\Support\Operations\OperationCompleted;
use Illuminate\Support\Facades\Queue;

class DisabledAuditTest extends \Tests\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), AuditServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('filament-audit.enabled', false);
    }

    public function test_disabled_boot_needs_no_table_and_does_not_register_capture(): void
    {
        Queue::fake();
        $this->assertFalse(app('events')->hasListeners(OperationCompleted::class));
        $this->assertSame(42, app(Operations::class)->run('example.write', fn () => 42));
        Queue::assertNothingPushed();
        $this->assertNotContains(AuditResource::class, \Filament\Facades\Filament::getPanel('admin')->getResources());
        $this->artisan('audit:prune')->assertSuccessful();
    }
}
