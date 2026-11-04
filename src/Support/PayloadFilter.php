<?php

namespace Fayne\Audit\Support;

use Illuminate\Support\Str;

final class PayloadFilter
{
    public function filter(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $path = ltrim($prefix . '.' . $key, '.');
            foreach (config('filament-audit.excluded_fields', []) as $pattern) {
                if (Str::is(strtolower($pattern), strtolower((string) $key)) || Str::is(strtolower($pattern), strtolower($path))) {
                    continue 2;
                }
            }
            // Do not serialize arbitrary objects, uploaded files or binary streams.
            if (is_array($value)) {
                $result[$key] = $this->filter($value, $path);
            } elseif (is_scalar($value) || $value === null) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
