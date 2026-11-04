<?php

namespace AuditTests;

use Fayne\Audit\AuditServiceProvider;
use Fayne\Audit\Jobs\WriteAudit;
use Fayne\Audit\Models\AuditEntry;
use Fayne\CMS\Support\Operations\Operations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class AuditTest extends \Tests\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), AuditServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('filament-audit.enabled', true);
        $app['config']->set('filament-audit.queue.connection', 'database');
    }

    protected function setUp(): void
    {
        parent::setUp();
        (require __DIR__ . '/../database/migrations/2026_10_25_000001_create_audit_entries.php')->up();
        Queue::fake();
    }

    public function test_outer_commit_and_rollback(): void
    {
        DB::beginTransaction();
        DB::transaction(fn () => app(Operations::class)->run('page.update', fn () => 42, ['title' => 'new']));
        Queue::assertNothingPushed();
        DB::commit();
        Queue::assertPushed(WriteAudit::class, 1);
        Queue::fake();
        DB::beginTransaction();
        app(Operations::class)->run('page.update', fn () => 42);
        DB::rollBack();
        Queue::assertNothingPushed();
    }

    public function test_filters_before_queue_and_writes_idempotently(): void
    {
        app(Operations::class)->run('page.update', fn () => 42, ['title' => 'new', 'nested' => ['password' => 'secret', 'access_token' => 'token']]);
        $job = Queue::pushed(WriteAudit::class)->first();
        $this->assertStringNotContainsString('secret', serialize($job));
        $this->assertStringNotContainsString('token', serialize($job));
        $job->handle(app(\Fayne\Audit\Drivers\DriverManager::class));
        $job->handle(app(\Fayne\Audit\Drivers\DriverManager::class));
        $this->assertSame(1, AuditEntry::count());
        $this->assertSame('new', AuditEntry::first()->payload['title']);
    }

    public function test_disabled_and_storage_failure_do_not_affect_business(): void
    {
        config(['filament-audit.enabled' => false]);
        $this->assertSame(42, app(Operations::class)->run('page.update', fn () => 42));
        Queue::assertNothingPushed();
        config(['filament-audit.enabled' => true]);
        app(Operations::class)->run('page.update', fn () => 42);
        $job = Queue::pushed(WriteAudit::class)->first();
        config(['filament-audit.enabled' => false]);
        $job->handle(app(\Fayne\Audit\Drivers\DriverManager::class));
        $this->assertSame(0, AuditEntry::count());
    }

    public function test_dispatch_failure_is_isolated_after_commit(): void
    {
        Queue::swap(new class
        {
            public function connection($name)
            {
                throw new \RuntimeException('queue down');
            }
        });
        $result = DB::transaction(fn () => app(Operations::class)->run('page.update', fn () => 42));
        $this->assertSame(42, $result);
        $this->assertSame(0, DB::transactionLevel());
    }
    public function test_authentication_and_filament_success_events(): void
    {
        $admin = new \Fayne\CMS\Models\Admin(['name' => 'Operator']);
        $admin->id = 123;
        event(new \Illuminate\Auth\Events\Login('admin', $admin, false));
        event(new \Illuminate\Auth\Events\Logout('admin', $admin));
        Queue::assertPushed(WriteAudit::class, 2);
        $this->assertSame('123', Queue::pushed(WriteAudit::class)->first()->entry['actor']['id']);
        Queue::fake();
        $record = new \Fayne\CMS\Models\Tag(['name' => 'Example', 'tenant_id' => 7]);
        $record->id = 9;
        $record->tenant_id = 7;
        $action = \Filament\Actions\DeleteAction::make()->record($record);
        $action->success();
        event(new \Filament\Actions\Events\ActionCalled($action));
        Queue::assertPushed(WriteAudit::class, 1);
        $this->assertSame('7', (string) Queue::pushed(WriteAudit::class)->first()->entry['tenant_id']);
        Queue::fake();
        $action->failure();
        event(new \Filament\Actions\Events\ActionCalled($action));
        Queue::assertNothingPushed();
    }

    public function test_retention_is_explicit_and_uses_operation_time(): void
    {
        $entry = ['event_id' => (string) \Illuminate\Support\Str::uuid(), 'operation' => 'page.update', 'source' => 'cli', 'actor' => [], 'target' => null, 'tenant_id' => null, 'payload' => [], 'metadata' => [], 'occurred_at' => now()->subDays(40), 'recorded_at' => now()];
        AuditEntry::create($entry);
        $this->artisan('audit:prune')->assertSuccessful();
        $this->assertSame(1, AuditEntry::count());
        config(['filament-audit.retention_days' => 30]);
        $this->artisan('audit:prune')->assertSuccessful();
        $this->assertSame(0, AuditEntry::count());
    }

    public function test_visibility_is_scoped_and_global_records_stay_private(): void
    {
        \Tests\Support\PackageDatabase::prepare();
        \Fayne\CMS\Support\Authorization\PermissionCatalog::seed();
        $this->artisan('audit:permissions')->assertSuccessful();
        $tenant = \Fayne\CMS\Models\Tenant::factory()->create();
        $admin = \Fayne\CMS\Models\Admin::factory()->create();
        $admin->tenants()->attach($tenant, ['is_active' => true]);
        $scope = app(\Fayne\CMS\Support\Authorization\AuthorizationContext::class);
        $scope->activate((int) $tenant->id);
        $admin->givePermissionTo('tenant.audit.view');
        $this->actingAs($admin, 'admin');
        foreach ([$tenant->id, $tenant->id + 1, null] as $tenantId) {
            AuditEntry::create(['event_id' => (string) \Illuminate\Support\Str::uuid(), 'operation' => 'test.write', 'source' => 'api', 'tenant_id' => $tenantId, 'actor' => [], 'payload' => [], 'metadata' => [], 'occurred_at' => now(), 'recorded_at' => now()]);
        }
        $access = app(\Fayne\Audit\Support\AuditAccess::class);
        $this->assertFalse($access->allowed());
        config(['filament-audit.visibility' => 'tenant_scoped']);
        $this->assertTrue($access->allowed());
        $this->assertSame(1, $access->query()->count());
        $this->assertFalse($access->query()->whereNull('tenant_id')->exists());
        $this->assertFalse($access->query()->where('tenant_id', $tenant->id + 1)->exists());
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        \Livewire\Livewire::test(\Fayne\Audit\Filament\Pages\ListAudits::class)->assertSuccessful()->assertCanSeeTableRecords($access->query()->get());
        $foreign = AuditEntry::where('tenant_id', $tenant->id + 1)->firstOrFail();
        $this->assertFalse(\Fayne\Audit\Filament\AuditResource::canView($foreign));
        \Livewire\Livewire::test(\Fayne\Audit\Filament\Pages\ViewAudit::class, ['record' => (string) $foreign->id])->assertNotFound();

    }

    public function test_real_database_queue_round_trip(): void
    {
        \Illuminate\Support\Facades\Schema::create('jobs', function ($table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        config(['queue.connections.database' => ['driver' => 'database', 'connection' => 'sqlite', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90]]);
        $this->app->forgetInstance('queue');
        Queue::clearResolvedInstance('queue');
        app(Operations::class)->run('file.write', fn () => 'ok', ['path' => 'example.txt'], context: ['source' => 'queue', 'actor' => ['type' => 'admin', 'id' => '12', 'name' => 'Original actor']]);
        $this->assertSame(1, DB::table('jobs')->count());
        $job = app('queue')->connection('database')->pop('audit');
        $job->fire();
        $this->assertSame(1, AuditEntry::count());
        $this->assertSame('12', AuditEntry::first()->actor['id']);
        $this->assertSame('queue', AuditEntry::first()->source);
    }

    public function test_account_form_is_one_event_with_role_assignments(): void
    {
        \Tests\Support\PackageDatabase::prepare();
        \Fayne\CMS\Support\Authorization\PermissionCatalog::seed();
        app(\Fayne\CMS\Support\Authorization\AuthorizationContext::class)->activate(0);
        $admin = \Fayne\CMS\Models\Admin::factory()->create();
        $admin->assignRole(\Spatie\Permission\Models\Role::where('name', 'super-admin')->firstOrFail());
        $this->actingAs($admin, 'admin');
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        Queue::fake();
        \Livewire\Livewire::test(\Fayne\CMS\Filament\Resources\AdminResource\Pages\CreateAdmin::class)
            ->fillForm(['name' => 'New account', 'email' => 'audit-new@example.test', 'password' => 'do-not-store', 'password_confirmation' => 'do-not-store', 'is_active' => true])
            ->call('create')->assertHasNoFormErrors();
        Queue::assertPushed(WriteAudit::class, 1);
        $job = Queue::pushed(WriteAudit::class)->first();
        $this->assertSame('admin.create', $job->entry['operation']);
        $this->assertStringNotContainsString('do-not-store', serialize($job));
        $this->assertSame('New account', $job->entry['payload']['name']);
    }

    public function test_driver_failure_is_retryable_and_other_drivers_can_be_registered(): void
    {
        app(Operations::class)->run('example.write', fn () => 'business succeeded');
        $job = Queue::pushed(WriteAudit::class)->first();
        $manager = app(\Fayne\Audit\Drivers\DriverManager::class);
        $manager->extend('unavailable', fn () => new class implements \Fayne\Audit\Contracts\AuditDriver
        {
            public function write(array $entry): void
            {
                throw new \RuntimeException('storage unavailable');
            }
        });
        config(['filament-audit.driver' => 'unavailable']);
        try {
            $job->handle($manager);
            $this->fail('Storage failure must reach the worker for retry.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('storage unavailable', $exception->getMessage());
        }
        config(['filament-audit.driver' => 'database']);
        $job->handle($manager);
        $this->assertSame(1, AuditEntry::count());
    }

    public function test_validation_failure_does_not_emit_and_action_service_does_not_duplicate(): void
    {
        try {
            app(Operations::class)->run('failed.write', fn () => throw \Illuminate\Validation\ValidationException::withMessages(['title' => 'invalid']));
        } catch (\Illuminate\Validation\ValidationException) {
        }
        Queue::assertNothingPushed();
        $record = new \Fayne\CMS\Models\Admin(['name' => 'target']);
        $record->id = 4;
        $action = \Filament\Actions\DeleteAction::make()->record($record);
        event(\Filament\Actions\Events\ActionCalling::class, $action);
        app(Operations::class)->completed('account.delete', [], $record);
        $action->success();
        event(\Filament\Actions\Events\ActionCalled::class, $action);
        Queue::assertPushed(WriteAudit::class, 1);
    }

    public function test_worker_scope_does_not_reuse_actor_and_missing_table_does_not_undo_business(): void
    {
        app(Operations::class)->run('first.write', fn () => true, context: ['actor' => ['type' => 'admin', 'id' => '99']]);
        app(Operations::class)->run('second.write', fn () => true);
        $jobs = Queue::pushed(WriteAudit::class);
        $this->assertSame('99', $jobs[0]->entry['actor']['id']);
        $this->assertSame('system', $jobs[1]->entry['actor']['type']);
        \Illuminate\Support\Facades\Schema::drop('audit_entries');
        $this->assertSame(42, DB::transaction(fn () => app(Operations::class)->run('third.write', fn () => 42)));
        try {
            $jobs[0]->handle(app(\Fayne\Audit\Drivers\DriverManager::class));
            $this->fail('Missing table must be retryable.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertSame(0, DB::transactionLevel());
        }
    }

    public function test_filament_halt_rolls_back_without_audit(): void
    {
        \Tests\Support\PackageDatabase::prepare();
        \Fayne\CMS\Support\Authorization\PermissionCatalog::seed();
        app(\Fayne\CMS\Support\Authorization\AuthorizationContext::class)->activate(0);
        $admin = \Fayne\CMS\Models\Admin::factory()->create();
        $admin->assignRole(\Spatie\Permission\Models\Role::where('name', 'super-admin')->firstOrFail());
        $this->actingAs($admin, 'admin');
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        app('events')->listen(\Filament\Resources\Events\RecordCreated::class, fn () => throw (new \Filament\Support\Exceptions\Halt)->rollBackDatabaseTransaction());
        Queue::fake();
        \Livewire\Livewire::test(\Fayne\CMS\Filament\Resources\AdminResource\Pages\CreateAdmin::class)
            ->fillForm(['name' => 'Rollback', 'email' => 'audit-rollback@example.test', 'password' => 'do-not-store', 'password_confirmation' => 'do-not-store', 'is_active' => true])->call('create');
        $this->assertFalse(\Fayne\CMS\Models\Admin::where('email', 'audit-rollback@example.test')->exists());
        Queue::assertNothingPushed();
    }

    public function test_bulk_delete_records_actual_partial_success_once(): void
    {
        \Illuminate\Support\Facades\Schema::create('audit_test_items', function ($table) {
            $table->id();
            $table->string('name');
        });
        $ok = AuditTestItem::create(['name' => 'ok']);
        $rejected = AuditTestItem::create(['name' => 'reject']);
        $action = \Fayne\CMS\Filament\Operations\DeleteBulkAction::make();
        $action->process(null, ['records' => new \Illuminate\Database\Eloquent\Collection([$ok, $rejected])]);
        Queue::assertPushed(WriteAudit::class, 1);
        $entry = Queue::pushed(WriteAudit::class)->first()->entry;
        $this->assertSame([(string) $ok->id], $entry['payload']['successful_ids']);
        $this->assertSame(1, $entry['metadata']['failed_count']);
        $this->assertSame(1, AuditTestItem::count());
    }
    public function test_replication_targets_replica_and_empty_update_is_not_logged(): void
    {
        \Illuminate\Support\Facades\Schema::create('audit_test_items', function ($table) {
            $table->id();
            $table->string('name');
        });
        $source = AuditTestItem::create(['name' => 'source']);
        $replica = AuditTestItem::create(['name' => 'replica']);
        $action = \Filament\Actions\ReplicateAction::make()->record($source);
        (new \ReflectionProperty($action, 'replica'))->setValue($action, $replica);
        event(\Filament\Actions\Events\ActionCalled::class, $action);
        Queue::assertPushed(WriteAudit::class, 1);
        $entry = Queue::pushed(WriteAudit::class)->first()->entry;
        $this->assertSame((string) $replica->id, $entry['target']['id']);
        $this->assertSame((string) $source->id, $entry['metadata']['source_id']);
        Queue::fake();
        $source->save();
        event(\Filament\Resources\Events\RecordUpdated::class, ['record' => $source, 'data' => ['name' => 'source'], 'page' => new \Fayne\CMS\Filament\Resources\TagResource\Pages\EditTag]);
        Queue::assertNothingPushed();
    }
    public function test_revision_restore_is_a_write_but_empty_stash_is_not(): void
    {
        \Tests\Support\PackageDatabase::prepare();
        config(['filament-page' => require __DIR__ . '/../../laravel-filament-page/config/filament-page.php']);
        foreach (glob(__DIR__ . '/../../laravel-filament-page/database/migrations/*.php') as $migration) {
            (require $migration)->up();
        }
        $tenant = \Fayne\CMS\Models\Tenant::factory()->create();
        app(\Fayne\CMS\Support\TenantContext::class)->set($tenant);
        $page = \Fayne\Page\Models\Page::create(['name' => 'Original', 'path' => '/test', 'locale' => 'en']);
        $revision = $page->revisions()->create([...$page->contentSnapshot(), 'is_stash' => false, 'released_at' => now()]);
        $service = app(\Fayne\Page\Services\PageRevisionService::class);
        $this->assertNull($service->stash($page));
        $service->restoreStash($page);
        Queue::assertNothingPushed();
        $page->update(['name' => 'Changed']);
        $service->restore($page, $revision);
        $this->assertSame('Original', $page->fresh()->name);
        Queue::assertPushed(WriteAudit::class, 1);
        $this->assertSame($revision->id, Queue::pushed(WriteAudit::class)->first()->entry['payload']['revision_id']);
    }

}

class AuditTestItem extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'audit_test_items';
    protected $guarded = [];
    public $timestamps = false;
    public function delete()
    {
        return $this->name === 'reject' ? false : parent::delete();
    }
}
