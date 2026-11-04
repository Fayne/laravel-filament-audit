<?php

namespace Fayne\Audit\Drivers;

use Illuminate\Support\Manager;

final class DriverManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('filament-audit.driver', 'database');
    }
    protected function createDatabaseDriver(): DatabaseDriver
    {
        return new DatabaseDriver;
    }
}
