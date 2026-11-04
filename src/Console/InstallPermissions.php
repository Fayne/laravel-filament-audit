<?php

namespace Fayne\Audit\Console;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class InstallPermissions extends Command
{
    protected $signature = 'audit:permissions';
    protected $description = 'Create audit permission definitions without granting access';
    public function handle(): int
    {
        foreach (['platform', 'tenant'] as $scope) {
            Permission::firstOrCreate(['name' => $scope . '.audit.view', 'guard_name' => config('filament-cms.guard', 'admin')], ['scope' => $scope]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->info('Audit permissions created. Assign them through scoped role management.');

        return self::SUCCESS;
    }
}
