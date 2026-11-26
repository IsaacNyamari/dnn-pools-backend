<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;

/**
 * An expected API failure, rendered as {"message": "...", "code": "..."}.
 */
class ApiException extends Exception implements ShouldntReport
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
    ) {
        parent::__construct($message);
    }

    public static function invalidCredentials(): self
    {
        return new self('invalid_credentials', 'The email or password is incorrect.', 422);
    }

    public static function twoFactorRequired(): self
    {
        return new self('two_factor_required', 'Enter your two-factor authentication code.', 422);
    }

    public static function twoFactorInvalid(): self
    {
        return new self('two_factor_invalid', 'The two-factor authentication code is wrong or already used.', 422);
    }

    public static function noInstitution(): self
    {
        return new self('no_institution', 'This account has no active institution.', 403);
    }

    public static function forbiddenInstitution(): self
    {
        return new self('forbidden_institution', 'You cannot upload data for this institution.', 403);
    }

    public function render(): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage(), 'code' => $this->errorCode], $this->status);
    }
}
