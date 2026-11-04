<?php

namespace Fayne\Audit\Support;

use Fayne\Audit\Models\AuditEntry;
use Fayne\CMS\Support\Authorization\Authorization;
use Fayne\CMS\Support\Authorization\AuthorizationContext;
use Illuminate\Database\Eloquent\Builder;

final class AuditAccess
{
    public function allowed(): bool
    {
        if (! config('filament-audit.enabled')) {
            return false;
        }
        $user = auth(config('filament-cms.guard', 'admin'))->user();
        $scope = app(AuthorizationContext::class)->id();
        if ($scope === 0) {
            return Authorization::platform($user, 'platform.audit.view');
        }

        return $scope > 0 && config('filament-audit.visibility') === 'tenant_scoped' && Authorization::allows($user, 'tenant.audit.view');
    }
    public function query(): Builder
    {
        $query = AuditEntry::query();
        if (! $this->allowed()) {
            return $query->whereRaw('1 = 0');
        }
        $scope = app(AuthorizationContext::class)->id();

        return $scope === 0 ? $query : $query->where('tenant_id', (string) $scope);
    }
}
