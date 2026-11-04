<?php

namespace Fayne\Audit\Filament\Pages;

final class ListAudits extends \Filament\Resources\Pages\ListRecords
{
    protected static string $resource = \Fayne\Audit\Filament\AuditResource::class;
    protected function getHeaderActions(): array
    {
        return [];
    }
}
