<?php

namespace Fayne\Audit\Filament;

use Fayne\Audit\Models\AuditEntry;
use Fayne\Audit\Support\AuditAccess;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AuditResource extends Resource
{
    protected static ?string $model = AuditEntry::class;
    protected static bool $isScopedToTenant = false;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    public static function getModelLabel(): string
    {
        return '审计日志';
    }
    public static function getPluralModelLabel(): string
    {
        return '审计日志';
    }
    public static function canViewAny(): bool
    {
        return app(AuditAccess::class)->allowed();
    }
    public static function canView(Model $record): bool
    {
        return app(AuditAccess::class)->query()->whereKey($record->getKey())->exists();
    }
    public static function canCreate(): bool
    {
        return false;
    }
    public static function canEdit(Model $record): bool
    {
        return false;
    }
    public static function canDelete(Model $record): bool
    {
        return false;
    }
    public static function canRestore(Model $record): bool
    {
        return false;
    }
    public static function canForceDelete(Model $record): bool
    {
        return false;
    }
    public static function canDeleteAny(): bool
    {
        return false;
    }
    public static function getEloquentQuery(): Builder
    {
        return app(AuditAccess::class)->query();
    }
    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('occurred_at')->label('操作时间')->dateTime()->sortable(),
            TextColumn::make('operation')->label('操作')->searchable(),
            TextColumn::make('actor.name')->label('操作者')->searchable(),
            TextColumn::make('actor.id')->label('操作者编号')->searchable(),
            TextColumn::make('target.type')->label('对象类型')->searchable()->toggleable(),
            TextColumn::make('target.id')->label('对象编号')->searchable(),
            TextColumn::make('tenant_id')->label('租户')->searchable(),
            TextColumn::make('source')->label('来源')->searchable(),
        ])->defaultSort('occurred_at', 'desc')->filters([
            Filter::make('period')->schema([DatePicker::make('from')->label('开始日期'), DatePicker::make('until')->label('结束日期')])
                ->query(fn (Builder $query, array $data) => $query->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('occurred_at', '>=', $date))->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('occurred_at', '<=', $date))),
        ])->recordActions([\Filament\Actions\ViewAction::make()])->toolbarActions([]);
    }
    public static function infolist(Schema $schema): Schema
    {
        $fields = [];
        foreach (['event_id' => '事件编号', 'operation' => '操作', 'occurred_at' => '操作时间', 'recorded_at' => '入库时间', 'source' => '来源', 'tenant_id' => '租户'] as $key => $label) {
            $fields[] = TextEntry::make($key)->label($label);
        }
        foreach (['actor' => '操作者', 'target' => '目标', 'payload' => '提交内容', 'metadata' => '结果与上下文'] as $key => $label) {
            $fields[] = TextEntry::make($key)->label($label)->state(fn (AuditEntry $record) => json_encode($record->{$key}, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))->columnSpanFull();
        }

        return $schema->components($fields);
    }
    public static function getPages(): array
    {
        return ['index' => Pages\ListAudits::route('/'), 'view' => Pages\ViewAudit::route('/{record}')];
    }
}
