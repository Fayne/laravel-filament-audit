<?php

namespace Fayne\Audit\Support;

use Throwable;

final class FailureReporter
{
    public static function report(Throwable $exception): void
    {
        // Avoid logging exception text or arguments which may contain credentials.
        try {
            logger()->error('Operation audit failed', ['exception_type' => $exception::class]);
        } catch (Throwable) {
        }
    }
}
