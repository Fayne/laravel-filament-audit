<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('filament-audit.database.connection');
    }
    public function up(): void
    {
        Schema::connection($this->getConnection())->create('audit_entries', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('event_id')->unique();
            $table->string('operation')->index();
            $table->string('source', 32);
            $table->string('tenant_id')->nullable();
            $table->json('actor');
            $table->json('target')->nullable();
            $table->json('payload');
            $table->json('metadata');
            $table->timestamp('occurred_at', 6)->index();
            $table->timestamp('recorded_at', 6);
            $table->index(['tenant_id', 'occurred_at']);
        });
    }
    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('audit_entries');
    }
};
