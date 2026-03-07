<?php

declare(strict_types=1);

namespace Andriichuk\HttpLogger;

/**
 * Sanitizes request/response data for safe logging: masks sensitive keys
 * and truncates long strings.
 */
final readonly class Sanitizer
{
    private const DEFAULT_SENSITIVE_KEYS = [
        'token',
        'refresh_token',
        'password',
        'confirm_password',
        'access_token',
        'api_key',
    ];

    private const DEFAULT_MAX_LENGTH = 100;

    /**
     * Sanitize an array for logging: replace sensitive keys with *** and truncate long strings.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string>|null  $sensitiveFields  Keys to mask. Null = use default list.
     * @param  int|null  $maxLength  Max string length before truncation. Null = use default.
     * @return array<string, mixed>
     */
    public function sanitize(array $data, ?array $sensitiveFields = null, ?int $maxLength = null): array
    {
        $sensitive = $sensitiveFields ?? self::DEFAULT_SENSITIVE_KEYS;
        $max = $maxLength ?? self::DEFAULT_MAX_LENGTH;

        foreach ($data as $key => $value) {
            if (in_array($key, $sensitive, true)) {
                $data[$key] = '***';

                continue;
            }

            if (is_string($value)) {
                $data[$key] = mb_strlen($value) > $max
                    ? mb_substr($value, 0, $max).'…'
                    : $value;
            } elseif (! is_scalar($value)) {
                $data[$key] = $this->sanitize((array) $value, $sensitiveFields, $maxLength);
            }
        }

        return $data;
    }
}
