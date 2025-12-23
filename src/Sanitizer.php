<?php

declare(strict_types=1);

namespace Andriichuk\HttpLogger;

final readonly class Sanitizer
{
    public function sanitize(array $data, int $maxLength = 100): array
    {
        $sensitive = [
            'token',
            'code',
            'refresh_token',
        ];

        foreach ($data as $key => $value) {
            if (in_array($key, $sensitive, true)) {
                $data[$key] = '***';

                continue;
            }

            if (is_string($value)) {
                $data[$key] = mb_strlen($value) > $maxLength
                    ? mb_substr($value, 0, $maxLength).'…'
                    : $value;
            } elseif (! is_scalar($value)) {
                $data[$key] = $this->sanitize((array) $value, $maxLength);
            }
        }

        return $data;
    }
}
