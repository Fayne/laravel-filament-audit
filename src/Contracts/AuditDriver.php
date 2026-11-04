<?php

namespace Fayne\Audit\Contracts;

interface AuditDriver
{
    public function write(array $entry): void;
}
