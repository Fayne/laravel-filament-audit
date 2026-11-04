<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300"></p>

# Laravel Filament Audit

[简体中文](../README.md) | English

For **PHP 8.4+, Laravel 13 and Filament 5**.

## Installation

Configure authorized Composer sources for this private package and its required `fayne/*` dependencies in the host first.

```bash
composer require fayne/laravel-filament-audit
php artisan vendor:publish --tag=filament-audit-config
php artisan migrate
```

Laravel discovers the service provider automatically. Field-only packages need no Panel plugin. See [composer.json](../composer.json) for exact dependencies; do not copy private credentials from a development host.

## Features, usage and boundaries

Enable `FILAMENT_AUDIT_ENABLED=true`; publish and review `config/filament-audit.php`. Run `php artisan audit:permissions`, explicitly assign `platform.audit.view` / `tenant.audit.view` in the appropriate authorization scope, then run `php artisan audit:check`.

Use an asynchronous connection (default `database`) and a dedicated `audit` worker: `php artisan queue:work database --queue=audit`. Redis/Horizon must explicitly consume this queue. Configure Laravel scheduler for daily `audit:prune`. Default `retention_days=null` keeps records indefinitely; a positive value permanently prunes expired records by operation time. Timestamps are UTC. Default `visibility=platform_only`; `tenant_scoped` allows authorized tenant administrators to see their tenant records.

Business packages depend on CMS Core operations, not Audit. Use Core `Filament\Operations\CreateRecord` / `EditRecord` pages; standard Filament create/edit/delete/force-delete/restore/replicate actions are adapted centrally. Core bulk operation components aggregate successful targets. Custom operations use `Operations::run()` or call `completed()` after success at the outer business boundary. No model observer fallback is supplied.

Models may implement `operationAuditPayload(array $payload): array` to allowlist form/action audit fields before enqueueing. Passwords and tokens are filtered recursively; extend `excluded_fields` with names or dot-path wildcards. Objects, uploaded files and resources are excluded. Oversized payloads are rejected and reported without failing the business operation. Successful login/logout events are captured automatically.

Extend `Fayne\Audit\Drivers\DriverManager` with `extend('custom', fn ($app) => $app->make(CustomDriver::class))`; implement `Fayne\Audit\Contracts\AuditDriver::write(array $entry): void`, use the event ID for idempotency, and set `filament-audit.driver=custom`. Throw failures for job retry. UI queries and pruning currently support the database driver only.

Dispatch failures are logged without payload and do not fail committed business operations. Job write failures use Laravel retries/failed_jobs. A loss window exists between commit and queue acceptance: this is not an outbox or a strong delivery guarantee. Disabled collection dispatches nothing, skips queued writes and pruning. Before uninstalling, disable collection and drain or clear the dedicated queue. Refresh configuration caches and restart long-lived workers after configuration changes.

## Testing and deployment

From the development repository root:

```bash
php packages/laravel-filament-cms-core/vendor/bin/phpunit -c packages/laravel-filament-audit/phpunit.xml.dist
```

Tests use Core’s Testbench environment without migrating the host business database. Accept the real queue worker and production retention policy separately.

## Cooperation and contributions

Open an issue with reproduction steps, versions and expected behavior. Include relevant checks and outstanding environment acceptance in pull requests. Never submit credentials or sensitive business data. For commercial cooperation and licensing, contact [Fayne Guo](mailto:fayne.guo@gmail.com).

## License

[Proprietary license](../LICENSE.md). Copying, distribution and use require authorization. Third-party dependencies retain their own licenses.

## Development and security

[Development and CI](../CONTRIBUTING.md) · [Security reports](../SECURITY.md). Run `composer check` for syntax, formatting and available standalone tests. Do not commit `composer.lock`.

`composer test` runs standalone boot tests; the full business integration suite above still runs in the development host. These are separate acceptance scopes.
