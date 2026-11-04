<?php

namespace Fayne\Audit\Listeners;

use Fayne\CMS\Support\Operations\Operations;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

final class Authentication
{
    public function handle(Login|Logout $event): void
    {
        if (! $event->user) {
            return;
        }
        app(Operations::class)->completed($event instanceof Login ? 'auth.login' : 'auth.logout', [], context: [
            'tenant_id' => null,
            'actor' => ['type' => $event->user::class, 'id' => (string) $event->user->getAuthIdentifier(), 'name' => $event->user->name ?? null],
            'guard' => $event->guard,
            'source' => 'auth',
        ]);
    }
}
