<?php

namespace Fayne\Audit\Tests\Standalone;

use Fayne\Audit\AuditServiceProvider;
use Orchestra\Testbench\TestCase;

class PackageBootTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AuditServiceProvider::class];
    }

    public function test_package_registers_its_resources_without_a_host_panel(): void
    {
        $this->assertFalse(config('filament-audit.enabled'));
        $this->assertInstanceOf(\Fayne\Audit\Drivers\DriverManager::class, app(\Fayne\Audit\Drivers\DriverManager::class));
        $this->assertFalse(app('events')->hasListeners(\Fayne\CMS\Support\Operations\OperationCompleted::class));
    }
}
