<?php

namespace AuditTests;

use Fayne\Audit\AuditServiceProvider;
use Fayne\Audit\Jobs\WriteAudit;
use Fayne\Audit\Listeners\FilamentOperations;
use Fayne\QrCode\Models\Qrcode;
use Fayne\QrCode\Support\QrcodeOperation;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;

class QrcodeAuditTest extends \Tests\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), AuditServiceProvider::class];
    }
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('filament-audit.enabled', true);
    }
    protected function setUp(): void
    {
        parent::setUp();
        (require __DIR__ . '/../database/migrations/2026_10_25_000001_create_audit_entries.php')->up();
        Queue::fake();
    }
    private function record(string $type = 'web_url'): Qrcode
    {
        $record = new Qrcode;
        $record->setRawAttributes(['id' => 17, 'tenant_id' => 7, 'type' => $type, 'internal_name' => 'QR'], true);
        $record->exists = true;

        return $record;
    }
    public function test_standard_create_and_update_filter_qr_payload_before_enqueue(): void
    {
        $record = $this->record();
        $payload = ['internal_name' => 'QR', 'target_url' => 'https://example.com?token=secret', 'scene' => 'secret', 'additional_data' => ['private' => 'secret'], 'default_data_url' => 'secret'];
        $page = \Mockery::mock(\Filament\Resources\Pages\Page::class);
        app(FilamentOperations::class)->saved(new \Filament\Resources\Events\RecordCreated($record, $payload, $page));
        $record->internal_name = 'Changed';
        $record->syncChanges();
        app(FilamentOperations::class)->saved(new \Filament\Resources\Events\RecordUpdated($record, [...$payload, 'internal_name' => 'Changed'], $page));
        Queue::assertPushed(WriteAudit::class, 2);
        foreach (Queue::pushed(WriteAudit::class) as $job) {
            $this->assertSame(7, $job->entry['tenant_id']);
            $this->assertArrayHasKey('internal_name', $job->entry['payload']);
            $this->assertStringNotContainsString('secret', serialize($job));
        }
    }
    public function test_generation_records_both_types_once_and_respects_transaction_rollback(): void
    {
        QrcodeOperation::generated($this->record(), ['width' => 430, 'disk' => 'public', 'target_url' => 'secret']);
        QrcodeOperation::generated($this->record('wechat_mini_app'), ['width' => 430, 'scene' => 'secret']);
        Queue::assertPushed(WriteAudit::class, 2);
        $this->assertSame(['qrcode.generate_web', 'qrcode.generate_mini_app'], Queue::pushed(WriteAudit::class)->map(fn ($job) => $job->entry['operation'])->all());
        foreach (Queue::pushed(WriteAudit::class) as $job) {
            $this->assertStringNotContainsString('secret', serialize($job));
        }
        Queue::fake();
        DB::beginTransaction();
        QrcodeOperation::generated($this->record(), ['width' => 430]);
        DB::rollBack();
        Queue::assertNothingPushed();
    }
    public function test_standard_delete_is_recorded_once_with_filtered_payload(): void
    {
        $action = \Filament\Actions\DeleteAction::make()->record($this->record());
        $action->success();
        app(FilamentOperations::class)->action(new \Filament\Actions\Events\ActionCalled($action));
        Queue::assertPushed(WriteAudit::class, 1);
        $job = Queue::pushed(WriteAudit::class)->first();
        $this->assertSame('qrcode.delete', $job->entry['operation']);
        $job->handle(app(\Fayne\Audit\Drivers\DriverManager::class));
        $entry = \Fayne\Audit\Models\AuditEntry::firstOrFail();
        $this->assertSame('17', $entry->target['id']);
        $this->assertEquals(7, $entry->tenant_id);
    }

    public function test_disabled_audit_does_not_queue_generation(): void
    {
        config(['filament-audit.enabled' => false]);
        QrcodeOperation::generated($this->record(), ['width' => 430]);
        Queue::assertNothingPushed();
    }
}
