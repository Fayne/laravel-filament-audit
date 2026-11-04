<?php

// Check package PHP sources without depending on a host application or vendor directory.
$root = dirname(__DIR__);
$paths = ['src', 'config', 'database', 'resources/lang', 'routes', 'tests', 'tools'];
$failed = false;
foreach ($paths as $path) {
    if (! is_dir($root . '/' . $path)) {
        continue;
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $path, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        passthru(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()), $status);
        $failed = $failed || $status !== 0;
    }
}
exit($failed ? 1 : 0);
