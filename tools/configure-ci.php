<?php

// Repository credentials belong in COMPOSER_AUTH, never in this file.
$path = dirname(__DIR__) . '/composer.json';
$composer = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
$repositories = array_values(array_filter($composer['repositories'] ?? [], fn (array $repository): bool => ($repository['type'] ?? null) !== 'path'));
$extra = getenv('PACKAGE_REPOSITORIES');
if ($extra !== false && $extra !== '') {
    $extra = json_decode($extra, true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($extra) || ! array_is_list($extra)) {
        throw new RuntimeException('PACKAGE_REPOSITORIES must be a JSON list of Composer repositories.');
    }
    $repositories = array_merge($extra, $repositories);
}
if ($repositories === [] && array_filter(array_keys($composer['require'] ?? []), fn (string $name): bool => str_starts_with($name, 'fayne/'))) {
    throw new RuntimeException('Configure PACKAGE_REPOSITORIES and COMPOSER_AUTH for private fayne dependencies.');
}
if ($repositories !== []) {
    $composer['repositories'] = $repositories;
} else {
    unset($composer['repositories']);
}
file_put_contents($path, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
