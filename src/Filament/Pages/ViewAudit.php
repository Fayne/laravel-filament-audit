<?php

namespace Fayne\Audit\Filament\Pages;

final class ViewAudit extends \Filament\Resources\Pages\ViewRecord
{
    protected static string $resource = \Fayne\Audit\Filament\AuditResource::class;
    protected function getHeaderActions(): array
    {
        return [];
    }
}
