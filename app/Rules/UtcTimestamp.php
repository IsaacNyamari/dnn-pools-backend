<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * An ISO 8601 instant in UTC, such as 2026-11-26T14:03:11.000Z.
 */
class UtcTimestamp implements ValidationRule
{
    private const PATTERN = '/^(\d{4})-(\d{2})-(\d{2})T([01]\d|2[0-3]):[0-5]\d:[0-5]\d(\.\d{1,6})?(Z|\+00:00)$/D';

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid($value)) {
            $fail('The :attribute must be an ISO 8601 UTC timestamp such as 2026-11-26T14:03:11.000Z.');
        }
    }

    public static function isValid(mixed $value): bool
    {
        return is_string($value)
            && preg_match(self::PATTERN, $value, $parts) === 1
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }
}
