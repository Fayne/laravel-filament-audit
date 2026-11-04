<?php

require __DIR__ . '/../../laravel-filament-cms-core/tests/bootstrap.php';
spl_autoload_register(function ($class) {
    foreach (['Fayne\\QrCode\\' => __DIR__ . '/../../laravel-filament-qrcode/src/', 'Fayne\\Page\\' => __DIR__ . '/../../laravel-filament-page/src/', 'Fayne\\Audit\\' => __DIR__ . '/../src/', 'AuditTests\\' => __DIR__ . '/'] as $prefix => $root) {
        if (str_starts_with($class, $prefix)) {
            $path = $root . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($path)) {
                require $path;
            }
        }
    }
}, true, true);
